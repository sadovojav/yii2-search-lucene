# Yii2 Zend Lucene Search

#### Features:
- Easy to use
- Search in models
- MutliTerm
- Search by numbers
- Search in documents (xlsx, docx, pptx)
- Relation value
- Interactive add/update/delete index

### Composer

The preferred way to install this extension is through [Composer](http://getcomposer.org/).

Either run ```php composer.phar require sadovojav/yii2-search-lucene "dev-master"```

or add ```"sadovojav/yii2-search-lucene": "dev-master"``` to the require section of your ```composer.json```

### Config

* If need implemented in the model class interface sadovojav\search\PageLink

```php
use yii\helpers\Url;
use sadovojav\search\PageLink;

class News extends \yii\db\ActiveRecord implements PageLink {
    public function getUrl()
    {
        return Url::to(['/news/news/view', 'id' => $this->id]);
    }
}
```

#### If you want use interactive add/update/delete index

* Attach component in your config file:

```php
'components' => [
    'search' => [
        'class' => 'sadovojav\search\components\SearchLucene',
        'indexDirectory' => '@console/runtime/search'
    ]
]
```

* Attach behavior to your model

```php
use sadovojav\search\behaviors\SearchBehavior;

class News extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
        	'search' => [
                'class' => SearchBehavior::className(),
                'attributes' => [
                    'name' => [
                        'name' => SearchLucene::FIELD_TEXT
                    ],
                    'text_intro' => [
                        'text_intro' => SearchLucene::FIELD_UN_STORED
                    ],
                    'text_full' => [
                        'text_full' => SearchLucene::FIELD_UN_STORED
                    ],
                ],
                'conditions' => [
                    'status_id' => self::STATUS_ACTIVE
                ],
                'urlManagerRule' => [
                    'news/<id:\d+>' => '/news/news/view'
                ]
            ]
        ];
    }
}
```

#### Parameters

- array `attributes` required - attributes to index
- array `conditions` - Conditions for creating search index
- string `baseUrl` = `''` - Base url
- array `urlManagerRule` -  Pretty url rules

#### Attention

```
SearchBehavior can work correctly only with one language website. Otherwise, it will be indexed only one language.
```

#### If you want use console indexing

* Attach component in your config file:

```php
'components' => [
    'search' => [
        'class' => 'sadovojav\search\components\SearchLucene',
        'indexDirectory' => '@console/runtime/search',
        'models' => [
            [
                'dataProviderOptions' => [
                    'query' => common\modules\news\models\News::find()
                        ->localized('en')
                        ->active()
                ],
                'attributes' => [
                    'lang' => 'en', // Custom fild to search
                    'name' => [
                        'name' => SearchLucene::FIELD_TEXT
                    ],
                    'text_intro' => [
                        'text_intro' => SearchLucene::FIELD_UN_STORED
                    ],
                    'text_full' => [
                        'text_full' => SearchLucene::FIELD_UN_STORED
                    ],
                ],
            ]
        ]
    ]
]
```

* Attach the module in your console config file:

```php
    'modules' => [
        'search' => 'sadovojav\search\Module'
    ]
```

* Add rules for your urlManager if you need

* Create search index

In console:

```php yii search/search/index```

* Optimize search index

In console:

```php yii search/search/optimyze```


### Use
#### Search controller

```php
use Yii;
use yii\data\ArrayDataProvider;

class SearchController extends \yii\web\Controller
{
    const ITEMS_PER_PAGE = 24;

    public function actionIndex($q)
    {
        $query = html_entity_decode(trim($q));

        // Search documents without custom conditions
        // $results = Yii::$app->search->search($query);
        
        // Search documents with custom conditions (lang)
        $results = Yii::$app->search->search($query, [
            'lang' => Yii::$app->language
        ]);

        $dataProvider = new ArrayDataProvider([
            'allModels' => $results,
            'pagination' => [
                'defaultPageSize' => self::ITEMS_PER_PAGE,
                'forcePageParam' => false
            ]
        ]);

        return $this->render('index', [
            'query' => $query,
            'dataProvider' => $dataProvider
        ]);
    }
}
```

#### Attention

Search component now use default fields
* ```class``` - model class name with namespase
* ```pk``` - model primary key


### TODO

* Create multilanguage behavior for this https://github.com/OmgDef/yii2-multilingual-behavior