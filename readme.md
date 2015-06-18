# Yii2 Zend Lucene Search

This is a fork [SergeiGulin/yii2-search-lucene](https://github.com/SergeiGulin/yii2-search-lucene)

#### Features:
- Easy to use
- Search in models
- Search in documents (xlsx, docx, pptx)
- Relation value

### Composer

The preferred way to install this extension is through [Composer](http://getcomposer.org/).

Either run ```php composer.phar require sadovojav/yii2-search-lucene ""dev-master"```

or add ```"sadovojav/yii2-search-lucene": ""dev-master"``` to the require section of your ```composer.json```

### Using

* If need Implemented in the model class interface sadovojav\search\PageLink

```php
    use sadovojav\search\PageLink;

    class News extends \yii\db\ActiveRecord implements PageLink {
        public function getUrl() {
            return ['news/view', 'id' => $this->id];
        }
    }
```

* Attach component in your frontend config file:

```php
    'components' => [
        'search' => [
            'class' => 'sadovojav\search\components\SearchLucene',
            'config' => [
                [
                    'dataProviderOptions' => [
                        'query' => sadovojav\content\models\Entry::find()
                            ->active()
                    ],
                    'pk' => 'id',
                    'type' => 'content-entry',
                    'attributesSearch' => [
                        'name' => 'name',
                        'description' => 'textIntro',
                        'category' => [
                            'attribute' => 'category.name',
                            'fieldType' => 'UnIndex'
                        ],
                        'textFull' => [
                            'attribute' => 'textFull',
                            'fieldType' => 'UnStored'
                        ],
                    ],
                ]
            ]
        ]
    ],
```
> pk and type are not required

* Attach the module in your console config file:
* 
```php
    'modules' => [
        'search' => 'sadovojav\search\Module',
    ],
```

* Search controller

```php
    use Yii;
    use yii\data\ArrayDataProvider;

    class SearchController extends \yii\web\Controller
    {
        const ITEMS_PER_PAGE = 30;

        public function actionIndex($q)
        {
            $q = html_entity_decode(trim($q));

            list($index, $results, $query) = Yii::$app->search->search($q);

            $dataProvider = new ArrayDataProvider([
                'allModels' => $results,
                'pagination' => [
                    'defaultPageSize' => self::ITEMS_PER_PAGE,
                    'forcePageParam' => false
                ],
            ]);

            return $this->render('index', array(
                'query' => $q,
                'dataProvider' => $dataProvider,
            ));
        }
    }
```
* Create search index

In console:

```php yii search/search/index```