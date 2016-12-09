<?php

namespace sadovojav\search\behaviors;

use Yii;
use yii\helpers\Url;
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

    private $_search;

    private $_baseUrl;

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

        $this->_baseUrl = Yii::$app->urlManager->getBaseUrl();

        if (count($this->urlManagerRule)) {
            Yii::$app->urlManager->addRules($this->urlManagerRule);
        }

        $this->_search = Yii::$app->search;
    }

    public function insert()
    {
        if ($this->meetConditions()) {
            Yii::$app->urlManager->setBaseUrl($this->baseUrl);

            $document = $this->_search->createDocument($this->owner, $this->attributes);

            $this->_search->addDocumentToIndex($document);

            $this->_search->optimize();

            Yii::$app->urlManager->setBaseUrl($this->_baseUrl);
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