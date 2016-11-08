# Yii2 Zend Lucene Search

This is a fork [SergeiGulin/yii2-search-lucene](https://github.com/SergeiGulin/yii2-search-lucene)

#### Features:
- Easy to use
- Search in models
- MutliTerm
- Search by numbers
- Search in documents (xlsx, docx, pptx)
- Relation value

### Composer

The preferred way to install this extension is through [Composer](http://getcomposer.org/).

Either run ```php composer.phar require sadovojav/yii2-search-lucene "dev-master"```

or add ```"sadovojav/yii2-search-lucene": "dev-master"``` to the require section of your ```composer.json```

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
            'caseSensitivity' => true,
            'indexDirectory' => '@console/runtime/search',
            'models' => [
                [
                    'dataProviderOptions' => [
                        'query' => common\modules\catalog\models\Product::find()
                            ->localized('en')
                            ->active()
                    ],
                    'attributes' => [
                        'lang' => 'en',
                        'name' => [
                            'name' => SearchLucene::FIELD_TEXT
                        ],
                        'image' => [
                            'image' => SearchLucene::FIELD_UN_INDEXED,
                        ],
                        'type_id' => [
                            'type_id' => SearchLucene::FIELD_UN_INDEXED,
                        ],
                        'vendor_code' => [
                            'vendor_code' => SearchLucene::FIELD_UN_STORED,
                        ],
                        'description' => [
                            'description' => SearchLucene::FIELD_UN_STORED,
                        ],
                    ],
                ],
            ],
        ],
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
    const ITEMS_PER_PAGE = 24;

    public function actionIndex($q)
    {
        $query = html_entity_decode(trim($q));

        $results = Yii::$app->search->search($query);

        $dataProvider = new ArrayDataProvider([
            'allModels' => $results,
            'pagination' => [
                'defaultPageSize' => self::ITEMS_PER_PAGE,
                'forcePageParam' => false
            ],
        ]);

        return $this->render('index', [
            'query' => $query,
            'dataProvider' => $dataProvider->models,
        ]);
    }

    public function actionCreateIndex()
    {
        $search = Yii::$app->search;

        $search->index();
    }
}
```
* Create search index

In console:

```php yii search/search/index```
