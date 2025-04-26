multilingual
===========================

Multilingual package for Yii2 projects
> Note: This package translates dynamic data in the project's Database.
>

Installation
------------

Either run

```sh
composer require yunusbek/multilingual
```

or add

```json
"yunusbek/multilingual": "^1.0",
```

to the ```require``` section of your composer.json.

Usage
------------

Once the library is installed, add the following to your project settings:

```php
# Add the following code to controllerMap
[
    #...
    'controllerMap' => [
        'ml-migration' => 'Yunusbek\Multilingual\commands\Migrations',
        'ml-extract' => 'Yunusbek\Multilingual\commands\Messages',
    ],
    #...
]
```

The next thing you need to do is updating your database schema by applying the migration of table ```language_list```:

```sh
php yii ml-migration
```

The next important processing steps in the project settings.

```php
# for yii2 basic - config/web.php
# for yii2 advanced - config/main.php
[
    #...
    'bootstrap' => ['log', 'Yunusbek\Multilingual\components\MultilingualBootstrap'],
    #...
    'modules' => [
        'multilingual' => [
            'class' => 'Yunusbek\Multilingual\Module',
        ],
    ]
    #...
    'components' => [
        #...
        'i18n' => [
            'class' => 'Yunusbek\Multilingual\components\MultilingualI18N',
            'translations' => [
                'app*' => [
                    'class' => 'Yunusbek\Multilingual\components\DbMessageSource', // You should apply this class to other categories as well.
                ],
            ],
        ],
        #...
    ]
    #...
]
```

Inheritance from the ```BaseLanguageList``` class and additional settings for the class created for the ```language_list``` table:
Create a class (model) for the ```language_list``` table that extends the ```BaseLanguageList``` class, and add additional rules to the ```rules()``` method:

````php
use Yunusbek\Multilingual\models\BaseLanguageList

class LanguageList extends BaseLanguageList
{
    public static function tableName()
    {
        return 'language_list';
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            #...self rules of model
        ]);
    }
}
````
Then, generate CRUD for the completed model and add a new language.

Add this array to the ```params.php``` file
```php
# params.php
[
    #...
    'language_list' => [
        'en' => [
            'name' => 'Default language', # Enter the current default language in your project.
            'short_name' => 'Def', # Enter the current default language short name.
            'image' => '/path/to/default/language/flag.jpg', # Enter the path to the current default language flag image. 
            'active' => false,
        ],
        // The list of dynamically added languages is visible from the continuation of this array.
    ],
    #...
]
```
>All added dynamic languages are stored as an array in the ```Yii::$app->params['language_list']``` variable. You can extract languages from this variable to use them in the navbar,
>and you get the current language from the ```Yii::$app->params['active_language']``` variable.
> 
> To make the added languages available in the system, place the following widget in the header section of your web project.
````php
echo \Yunusbek\Multilingual\widgets\MultilingualLanguageList::widget() //['options' => ['class' => '...']] if you want
````
>![All added dynamic languages.](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/language_list.jpg)

The models to be translated are inherited from the ```Multilingual``` model.

```php
use Yunusbek\Multilingual\models\Multilingual;

class ReferenceModel extends Multilingual
{
    #...model settings.
}
```

The part to be applied in the form page:

```php
<?php $form = ActiveForm::begin(); ?>
    #...
    <?php echo \Yunusbek\Multilingual\widgets\MultilingualAttributes::widget([
        'form' => $form,
        'model' => $model,
        'table_name' => 'model_table_name', # set the model table name to output model attributes to the lang_* table.
        'attribute' => 'attribute_name', # or add multiple like ['attribute_name', 'second_attribute_name']
    ]) ?>
    #...
<?php ActiveForm::end(); ?>
```
> All added languages will automatically be displayed on the form page. From here you can type in the translation of all your newly added languages.
>1) Default language;
>2) Newly added language;
>
>![All added languages will be displayed on the form page.](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/form.jpg)


Run the following commands to extract the attributes of the models and the static information of the project to the ```lang_*``` table.:

```sh
php yii ml-extract/i18n
php yii ml-extract/attributes
```

Necessary additions
===========================

>Not only can you translate new languages one by one on the form page, but you can also do it by translating a single Excel file in bulk.

Useful buttons to install
------------
Add the following button to the top of the created CRUD index page which will take you to the general translations page.

````php
echo Html::a(Yii::t('multilingual', 'All columns'), ['/multilingual/language/index', 'is_static' => 0], ['target' => '_blank']);  // it will take you to all dynamic translations
echo Html::a(Yii::t('multilingual', 'All i18n'), ['/multilingual/language/index', 'is_static' => 1], ['target' => '_blank']);     // it will take you to all static translations
````

and add the following button to the actions section of each language row, which will download an Excel file of all translations for that language.

````php
echo Html::a(Yii::t('multilingual', 'Export i18n'), ['/multilingual/language/export-to-excel', 'table_name' => $model->table, 'is_static' => true], ['data-pjax' => '0'])
echo Html::a(Yii::t('multilingual', 'Export Columns'), ['/multilingual/language/export-to-excel', 'table_name' => $model->table, 'is_static' => false], ['data-pjax' => '0'])
````


Instruction manual
------------

This is an Excel file downloaded from an existing language

![This is an Excel file downloaded from an existing language](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/excel1.jpg)

This is a translated Excel file for the language that needs to be added.

![This is a translated Excel file for the language that needs to be added.](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/excel2.jpg)

> When adding a new language, you can save the path of the translated Excel file above to the ```import_excel``` attribute in the ```language_list``` table.
> 
> Result: all translations for the newly added language will be saved, automatically saved from the Excel file to the new ```lang_*``` table.
>

Result:
------------

![This is a translated Excel file for the language that needs to be added.](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/result.jpg)
