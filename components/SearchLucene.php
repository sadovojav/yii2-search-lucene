<?php

namespace sadovojav\search\components;

use Yii;
use yii\db\ActiveRecord;
use yii\console\Exception;
use yii\helpers\FileHelper;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\Document;
use yii\data\ActiveDataProvider;
use ZendSearch\Lucene\Document\Docx;
use ZendSearch\Lucene\Document\Pptx;
use ZendSearch\Lucene\Document\Xlsx;
use ZendSearch\Lucene\Document\Field;
use ZendSearch\Lucene\Search\QueryParser;
use ZendSearch\Lucene\Search\Query\Wildcard;
use ZendSearch\Lucene\Search\Query\MultiTerm;
use ZendSearch\Lucene\Index\Term as IndexTerm;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use juffin_halli\dataProviderIterator\DataProviderIterator;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8Num\CaseInsensitive;

/**
 * Class SearchLucene
 * @package sadovojav\search\components
 */
class SearchLucene extends \yii\base\Component
{
    /**
     * Models for search
     *
     * @var array
     */
    public $models = [];

    /**
     * Directory for Index Files
     *
     * @var string alias or directory path
     */
    public $indexDirectory = '@app/runtime/search';

    /**
     * Minimum term prefix length (number of minimum non-wildcard characters)
     *
     * @var int
     */
    public $minPrefixLength = 3;

    /**
     * Search result limit
     *
     * @var int 0 means no limit
     */
    public $resultsLimit = 0;

    /**
     * @var \ZendSearch\Lucene\Index
     */
    private $_luceneIndex;

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
        Analyzer::setDefault(new CaseInsensitive());

        $this->indexDirectory = FileHelper::normalizePath(Yii::getAlias($this->indexDirectory));
        $this->_luceneIndex = $this->getIndex($this->indexDirectory);
    }

    /**
     * Open or create new index
     *
     * @param string $directory
     * @return \ZendSearch\Lucene\SearchIndexInterface
     */
    private function getIndex($directory)
    {
        if (file_exists($directory . DIRECTORY_SEPARATOR . 'segments.gen')) {
            return Lucene::open($directory);
        } else {
            return Lucene::create($directory);
        }
    }

    /**
     * Indexing of all models
     */
    public function index()
    {
        if (!count($this->models)) {
            throw new Exception('Models must be defined in the config file');
        }

        $this->deleteAllDocumentsFromIndex();

        foreach ($this->models as $key => $value) {
            $dataProvider = new ActiveDataProvider($value['dataProviderOptions']);
            $iterator = new DataProviderIterator($dataProvider);

            foreach ($iterator as $model) {
                $document = $this->createDocument($model, $value['attributes']);

                $this->addDocumentToIndex($document);
            }
        }

        $this->optimize();
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
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $query->addTerm(new IndexTerm(mb_strtolower($val, 'UTF-8'), $field), true);
                    }
                } else {
                    $query->addTerm(new IndexTerm(mb_strtolower($value, 'UTF-8'), $field), true);
                }
            }
        }

        if (!empty($term)) {
            $subTerm = explode(' ', $term);

            foreach ($subTerm as $value) {
                $query->addTerm(new IndexTerm(mb_strtolower($value, 'UTF-8')), true);
            }
        }

        return $this->_luceneIndex->find($query);
    }

    /**
     * Optimization indexes
     */
    public function optimize()
    {
        $this->_luceneIndex->optimize();
    }

    /**
     * Add document to index
     *
     * @param Document $document
     */
    public function addDocumentToIndex(Document $document)
    {
        $this->_luceneIndex->addDocument($document);
    }

    /**
     * Delete document from index
     *
     * @param $class
     * @param $pk
     * @return bool
     */
    public function deleteDocumentFromIndex($class, $pk)
    {
        $query = new MultiTerm();
        $query->addTerm(new IndexTerm($class, 'class'), true);
        $query->addTerm(new IndexTerm(strval($pk), 'pk'), true);

        $docs = $this->_luceneIndex->find($query);

        if (count($docs)) {
            $this->_luceneIndex->delete($docs[0]->id);

            $this->_luceneIndex->commit();
        }
    }

    /**
     * Delete all documents from index
     */
    private function deleteAllDocumentsFromIndex()
    {
        for ($id = 0; $id < $this->_luceneIndex->maxDoc(); $id++) {
            $this->_luceneIndex->delete($id);
        }

        $this->_luceneIndex->commit();
    }

    /**
     * Create document
     *
     * @param $model
     * @param $attributes
     * @return Document
     */
    public function createDocument(ActiveRecord $model, array $attributes)
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
            throw new Exception('The identity object must implement PageLink');
        }

        $document->addField(Field::keyword('class', get_class($model)));
        $document->addField(Field::keyword('pk', strval($model->id)));
        $document->addField(Field::text('url', $model->getUrl()));

        return $document;
    }

    /**
     * Get attribute value
     *
     * @param $model
     * @param $attributeValue
     * @return null|string
     */
    private function getAttributeValue(ActiveRecord $model, $attribute)
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
     * Get file path
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
