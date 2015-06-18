<?php

namespace sadovojav\search\controllers;

use Yii;

/**
 * Class SearchController
 * @package sadovojav\search\controllers
 */
class SearchController extends \yii\console\Controller
{
    public function actionIndex()
    {
        /* @var $search \sadovojav\search\components\SearchLucene */
        $search = Yii::$app->search;

        $search->createIndex();
    }
}