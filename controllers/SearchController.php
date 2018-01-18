<?php

namespace sadovojav\search\controllers;

/**
 * Class SearchController
 * @package sadovojav\search\controllers
 */
class SearchController extends \yii\console\Controller
{
    public function actionIndex()
    {
        \Yii::$app->search->index();
    }

    public function actionOptimize()
    {
        \Yii::$app->search->optimize();
    }
}