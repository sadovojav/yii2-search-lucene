<?php

namespace sadovojav\search;

use Yii;
use yii\data\ActiveDataProvider;
use juffin_halli\dataProviderIterator\DataProviderIterator;
use Zend_Search_Lucene;
use Zend_Search_Lucene_Analysis_Analyzer;
use Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive;
use Zend_Search_Lucene_Document;
use Zend_Search_Lucene_Field;
use Zend_Search_Lucene_Search_Query_Wildcard;
use Zend_Search_Lucene_Search_QueryParser;

/**
 * Class SearchLucene
 * @package sadovoy\search
 */
class SearchLucene extends \yii\base\Component
{
    public $config;
    public $indexFiles = '@console/runtime/search';

    const FIELD_KEYWORD = 'Keyword';
    const FIELD_UN_INDEXED = 'UnIndexed';
    const FIELD_BINARY = 'Binary';
    const FIELD_TEXT = 'Text';
    const FIELD_UN_STORED = 'UnStored';

    const DOCUMENT_DOCX = '.docx';
    const DOCUMENT_XLSX = '.xlsx';
    const DOCUMENT_PPTX = '.pptx';

    private static $fieldTypes = [
        self::FIELD_KEYWORD,
        self::FIELD_UN_INDEXED,
        self::FIELD_BINARY,
        self::FIELD_TEXT,
        self::FIELD_UN_STORED,
    ];

    private static $documents = [
        self::DOCUMENT_DOCX,
        self::DOCUMENT_XLSX,
    ];

    /**
     * Create Zend Lucene index
     */
    public function createIndex()
    {
        setlocale(LC_CTYPE, 'ru_RU.UTF-8');
        Zend_Search_Lucene_Analysis_Analyzer::setDefault(
            new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());

        $index = new Zend_Search_Lucene(Yii::getAlias($this->indexFiles), true);

        $config = $this->config;

        foreach ($config as $key => $value) {
            $doc = new Zend_Search_Lucene_Document();

            $dataProvider = new ActiveDataProvider($value['dataProviderOptions']);
            $iterator = new DataProviderIterator($dataProvider);

            foreach ($iterator as $model) {
                if (isset($value['pk']) && isset($model->$value['pk'])) {
                    $this->indexField($doc, 'pk', $model->$value['pk'], self::FIELD_UN_INDEXED);
                }

                if (isset($value['type'])) {
                    $this->indexField($doc, 'type', $value['type'], self::FIELD_UN_INDEXED);
                }

                foreach ($value['attributesSearch'] as $name => $val) {
                    if (is_array($val)) {

                        if (isset($val['fieldType'])) {
                            $fieldType = in_array($val['fieldType'], self::$fieldTypes) ? $val['fieldType'] : self::FIELD_TEXT;
                        } else {
                            $fieldType = self::FIELD_TEXT;
                        }

                        $attributeValue = $this->getAttributeValue($model, $val);

                        $this->indexField($doc, $name, $attributeValue, $fieldType);
                    } else {
                        $attributeValue = $this->getAttributeValue($model, $val);

                        $this->indexField($doc, $name, $attributeValue);
                    }
                }

                if (!method_exists($model, 'getUrl')) {
                    throw new \yii\base\InvalidValueException('The identity object must implement PageLink.');
                }

                $link = $model->getUrl();
                $this->indexField($doc, 'url', $link);
                $index->addDocument($doc);
            }
        }

        $index->optimize();
        $index->commit();
    }

    /**
     * Search
     * @param $q
     * @return array
     * @throws \Zend_Search_Lucene_Exception
     */
    public function search($q)
    {
        setlocale(LC_CTYPE, 'ru_RU.UTF-8');
        Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(0);
        Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());

        if (($term = $q) !== null) {
            $index = new Zend_Search_Lucene(Yii::getAlias($this->_indexFiles));
            $results = $index->find($term);
            $query = Zend_Search_Lucene_Search_QueryParser::parse($term);

            return [$index, $results, $query];
        } else {
            return null;
        }
    }

    /**
     * Make index field
     * @param $document
     * @param $field
     * @param $data
     * @param string $fieldType
     * @param string $encoding
     * @return mixed
     */
    protected function indexField($document, $field, $data, $fieldType = self::FIELD_TEXT, $encoding = 'UTF-8')
    {
        $strip_tags_data = strip_tags($data);
        $document->addField(Zend_Search_Lucene_Field::$fieldType($field, $strip_tags_data, $encoding));

        return $document;
    }

    /**
     * Get attribute value
     * @param $model
     * @param $attributeValue
     * @return null|string
     */
    private function getAttributeValue($model, $attributeValue)
    {
        $result = $value = null;

        $attribute = isset($attributeValue['attribute']) ? $attributeValue['attribute'] : $attributeValue;

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
            case self::DOCUMENT_DOCX : $result = $this->readDocx($attributeValue, $value); break;
            case self::DOCUMENT_XLSX : $result = $this->readXlsx($attributeValue, $value); break;
            case self::DOCUMENT_PPTX : $result = $this->readPptx($attributeValue, $value); break;
        }

        return $result;
    }

    /**
     * Get File path
     * @param $basePath
     * @param $value
     * @return bool|string
     */
    private function getFilePath($basePath, $value) {
        return Yii::getAlias($basePath . $value);
    }

    /**
     * Read .docx
     * @param $attributeValue
     * @param $value
     * @return null|string
     * @throws \Zend_Search_Lucene_Document_Exception
     */
    private function readDocx($attributeValue, $value) {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return \Zend_Search_Lucene_Document_Docx::loadDocxFile($filePath)->body;
    }

    /**
     * Read .xlsx files
     * @param $attributeValue
     * @param $value
     * @return null|string
     */
    private function readXlsx($attributeValue, $value) {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return \Zend_Search_Lucene_Document_Xlsx::loadXlsxFile($filePath)->body;
    }

    /**
     * Read Pptx files
     * @param $attributeValue
     * @param $value
     * @return null|string
     */
    private function readPptx($attributeValue, $value) {
        $filePath = $this->getFilePath($attributeValue['basePath'], $value);

        if (!file_exists($filePath)) {
            return null;
        }

        return \Zend_Search_Lucene_Document_Pptx::loadPptxFile($filePath)->body;
    }
}
