<?php

namespace sadovojav\search\behaviors;

use yii\db\ActiveRecord;

/**
 * Class SearchBehavior
 * @package sadovojav\search\behaviors
 */
class SearchBehavior extends \yii\behaviors\AttributeBehavior
{
    /**
     * Conditions for adding a document to the index
     *
     * @var
     */
    public $conditions = [];

    /**
     * Base url
     *
     * @var string
     */
    public $baseUrl = '';

    /**
     * UrlManager rule
     *
     * @var array
     */
    public $urlManagerRule = [];

    public $languages = [];

    private $_search;

    private $_baseUrl;

    private $_language;

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'insert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'update',
            ActiveRecord::EVENT_AFTER_DELETE => 'delete'
        ];
    }

    public function init()
    {
        parent::init();

        $this->_baseUrl = \Yii::$app->urlManager->getBaseUrl();
        $this->_language = \Yii::$app->language;
        $this->_search = \Yii::$app->search;

        if (count($this->urlManagerRule)) {
            \Yii::$app->urlManager->addRules($this->urlManagerRule);
        }
    }

    private function insert()
    {
        if ($this->meetConditions()) {
            \Yii::$app->urlManager->setBaseUrl($this->baseUrl);

            if (count($this->languages)) {
                $class = get_class($this->owner);

                foreach ($this->languages as $language) {
                    $model = $this->getModelByLang($class, $language);

                    $this->attributes['lang'] = $language;

                    $document = $this->_search->createDocument($model, $this->attributes);

                    $this->_search->addDocumentToIndex($document);
                }
            } else {
                $document = $this->_search->createDocument($this->owner, $this->attributes);

                $this->_search->addDocumentToIndex($document);
            }

            $this->_search->optimize();

            \Yii::$app->language = $this->_language;
            \Yii::$app->urlManager->setBaseUrl($this->_baseUrl);
        }
    }

    public function update()
    {
        $this->_search->deleteDocumentFromIndex(get_class($this->owner), $this->owner->id);

        $this->insert();
    }

    public function delete()
    {
        $this->_search->deleteDocumentFromIndex(get_class($this->owner), $this->owner->id);

        $this->_search->optimize();
    }

    private function getModelByLang($class, $language)
    {
        \Yii::$app->language = $language;

        return $class::find()
            ->where(['id' => $this->owner->id])
            ->one();
    }

    private function meetConditions()
    {
        if (count($this->conditions)) {
            foreach ($this->conditions as $name => $value) {
                if ($this->owner->{$name} != $value) {
                    return false;
                }
            }
        }

        return true;
    }
}