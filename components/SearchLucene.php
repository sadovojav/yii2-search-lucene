<?php

namespace sadovojav\search\components;

use Yii;
use yii\helpers\FileHelper;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\Document;
use yii\data\ActiveDataProvider;
use ZendSearch\Lucene\Index\Term as IndexTerm;
use ZendSearch\Lucene\Document\Docx;
use ZendSearch\Lucene\Document\Pptx;
use ZendSearch\Lucene\Document\Xlsx;
use ZendSearch\Lucene\Document\Field;
use ZendSearch\Lucene\Search\QueryParser;
use ZendSearch\Lucene\Search\Query\Wildcard;
use ZendSearch\Lucene\Search\Query\MultiTerm;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8Num;
use juffin_halli\dataProviderIterator\DataProviderIterator;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8Num\CaseInsensitive;

/**
 * Class SearchLucene
 * @package sadovojav\search\components
 */
class SearchLucene extends \yii\base\Component
{
    /**
     * @var
     */
    public $models;

    /**
     * @var string alias or directory path
     */
    public $indexDirectory = '@app/runtime/search';

    /**
     * @var bool
     */
    public $caseSensitivity = false;

    /**
     * @var int Minimum term prefix length (number of minimum non-wildcard characters)
     */
    public $minPrefixLength = 3;

    /**
     * @var int 0 means no limit
     */
    public $resultsLimit = 0;

    /**
     * @var \ZendSearch\Lucene\Index
     */
    private $luceneIndex;

    const FIELD_KEYWORD = 'Keyword';
    const FIELD_UN_INDEXED = 'UnIndexed';
    const FIELD_BINARY = 'Binary';
    const FIELD_TEXT = 'Text';
    const FIELD_UN_STORED = 'UnStored';

    const DOCUMENT_DOCX = '.docx';
    const DOCUMENT_XLSX = '.xlsx';
    const DOCUMENT_PPTX = '.pptx';

    private static $documents = [
        self::DOCUMENT_DOCX,
        self::DOCUMENT_XLSX,
    ];

    public function init()
    {
        QueryParser::setDefaultEncoding('UTF-8');

        if ($this->caseSensitivity) {
            Analyzer::setDefault(new Utf8Num());
        } else {
            Analyzer::setDefault(new CaseInsensitive());
        }

        $this->indexDirectory = FileHelper::normalizePath(Yii::getAlias($this->indexDirectory));
        $this->luceneIndex = $this->getLuceneIndex($this->indexDirectory);
    }

    /**
     * @param string $directory
     * @return \ZendSearch\Lucene\SearchIndexInterface
     */
    protected function getLuceneIndex($directory)
    {
        if (file_exists($directory . DIRECTORY_SEPARATOR . 'segments.gen')) {
            return Lucene::open($directory);
        } else {
            return Lucene::create($directory);
        }
    }

    /**
     * Create Zend Lucene index
     */
    public function index()
    {
        foreach ($this->models as $key => $value) {
            $dataProvider = new ActiveDataProvider($value['dataProviderOptions']);
            $iterator = new DataProviderIterator($dataProvider);

            if (isset($value['attributes']['lang'])) {
                $hits = $this->luceneIndex->find('lang:' . $value['attributes']['lang']);

                foreach ($hits as $hit) {
                    $this->luceneIndex->delete($hit->id);
                }
            }

            foreach ($iterator as $model) {
                $document = $this->createDocument($model, $value['attributes']);

                $this->luceneIndex->addDocument($document);
            }
        }

        $this->luceneIndex->optimize();
        $this->luceneIndex->commit();

        return true;
    }

    public function optimize()
    {
        $this->luceneIndex->optimize();
    }

    /**
     * Create document
     *
     * @param $model
     * @param $attributes
     * @return Document
     */
    private function createDocument($model, $attributes)
    {
        $document = new Document();

        foreach ($attributes as $name => $value) {
            if (is_array($value)) {
                list($attribute, $fieldType) = [key($value), current($value)];

                $value = $this->getAttributeValue($model, $attribute);
            } else {
                $fieldType = self::FIELD_TEXT;
            }

            $document->addField(Field::$fieldType($name, strip_tags($value)));
        }

        if (!method_exists($model, 'getUrl')) {
            throw new \yii\base\InvalidValueException('The identity object must implement PageLink.');
        }

        $document->addField(Field::Text('url', $model->getUrl()));

        return $document;
    }

    /**
     * Search page for the term in the index.
     *
     * @param $term
     * @param array $fields
     * @return array|\ZendSearch\Lucene\Search\QueryHit
     */
    public function search($term, $fields = [])
    {
        Wildcard::setMinPrefixLength($this->minPrefixLength);
        Lucene::setResultSetLimit($this->resultsLimit);

        $query = new MultiTerm();

        if (count($fields)) {
            foreach ($fields as $field => $value) {
                $query->addTerm(new IndexTerm($value, $field), true);
            }
        }

        $subTerm = explode(' ', $term);

        foreach ($subTerm as $value) {
            $query->addTerm(new IndexTerm($value), true);
        }

        return $this->luceneIndex->find($query);
    }

    /**
     * Get attribute value
     *
     * @param $model
     * @param $attributeValue
     * @return null|string
     */
    private function getAttributeValue($model, $attribute)
    {
        $result = $value = null;

        if (strpos($attribute, '.')) {
            $arr = explode('.', $attribute);

            foreach ($arr as $val) {
                if (is_null($model->$val)) {
                    break;
                }

                if (is_object($model->$val)) {
                    $model = $model->$val;
                } else {
                    $value = $model->$val;
                }
            }
        } else {
            $value = $model->$attribute;
        }

        $fileExt = strrchr($value, '.');

        if (!in_array($fileExt, self::$documents)) {
            return $value;
        }

        switch ($fileExt) {
            case self::DOCUMENT_DOCX :
                $result = $this->readDocx($attribute, $value);
                break;
            case self::DOCUMENT_XLSX :
                $result = $this->readXlsx($attribute, $value);
                break;
            case self::DOCUMENT_PPTX :
                $result = $this->readPptx($attribute, $value);
                break;
        }

        return $result;
    }

    /**
     * Get File path
     *
     * @param $basePath
     * @param $value
     * @return bool|string
     */
    private function getFilePath($basePath, $value)
    {
        return Yii::getAlias($basePath . $value);
    }

    /**
     * Read .docx
     *
     * @param $attributeValue
     * @param $value
     * @return null|string
     * @throws \Zend_Search_Lucene_Document_Exception
     */
    private function readDocx($attributeValue, $value)
    {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return Docx::loadDocxFile($filePath)->body;
    }

    /**
     * Read .xlsx files
     *
     * @param $attributeValue
     * @param $value
     * @return null|string
     */
    private function readXlsx($attributeValue, $value)
    {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return Xlsx::loadXlsxFile($filePath)->body;
    }

    /**
     * Read Pptx files
     *
     * @param $attributeValue
     * @param $value
     * @return null|string
     */
    private function readPptx($attributeValue, $value)
    {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return Pptx::loadPptxFile($filePath)->body;
    }
}
