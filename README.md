Multilingual
===========================

A fully featured multilingual management package for Yii2 projects.

> ✅ Dynamically translate database content  
> ✅ Support for multiple languages with individual tables (lang_*)  
> ✅ Form-level multilingual fields  
> ✅ Static translations (i18n) integration  
> ✅ Excel-based bulk import/export of translations
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


Translation guides:
---
> ✅   The ```MultilingualTrait``` must be used in models that support multilingual (translatable) content.
```php
use Yunusbek\Multilingual\components\traits\MultilingualTrait;

class YourModel extends yii\db\ActiveRecord
{
    use MultilingualTrait;
    #...model settings.
}
```
---
> ✅   For query commands using ```(new Query())->from('table_name')```:
>
>To use multilingual support, simply replace ```new \yii\db\Query()``` with ```new \Yunusbek\Multilingual\models\MlQuery()```
```php
(new MlQuery())->from('table_name')//-> then your conditions...
```
---
> ✅   If you are writing raw SQL conditions (e.g., where, select, join, etc.) instead of using Yii2's ORM syntax, you need to include the following code to ensure that the multilingual feature works properly.
>
```sql
SELECT
    -- Replace `your_table_name.attribute_name` with a multilingual fallback expression
    COALESCE(NULLIF(your_real_table_name_lang_en.value->>'attribute_name', ''), your_table_name.attribute_name) AS attribute_name
FROM
    your_real_table_name AS your_table_name

-- Add JOIN with the multilingual table to fetch translations
LEFT JOIN lang_en AS your_real_table_name_lang_en
    ON your_real_table_name_lang_en.table_name = 'your_real_table_name'
    AND your_real_table_name_lang_en.table_iteration = your_table_name.id

WHERE
    -- Add your filtering conditions here
```
---

### Form fields:

Add MlFields widget to your form — it will auto-generate inputs for newly added languages.

```php
<?php $form = ActiveForm::begin(); ?>
    #...
    <?php echo \Yunusbek\Multilingual\widgets\MlFields::widget([
        'form' => $form,
        'model' => $model,
        'table_name' => 'model_table_name', # set the model table name to output model attributes to the lang_* table.
        'attribute' => 'attribute_name', # or add multiple like ['attribute_name', 'second_attribute_name']
        //'options' => ['class' => 'form-control'], # input options
        //'wrapperOptions' => ['class' => 'form-droup'], # parent element options
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

### Console commands

The package includes the following console commands:
```sh
php yii ml-extract/i18n
```
- `ml-extract/i18n` – Extracts static messages used in the app to the database.


```sh
php yii ml-extract/attributes
```
- `ml-extract/attributes` - It collects all the tables and columns called in the MlFields widget into a `multilingual.json` file.
````json
{
  "where": {
    "status": 1
  },
  "tables": {
    "manuals_application_type": ["name"],
    "manuals_collateral_type": ["name"],
    "manuals_department_relevant_type": ["name", "short_name"],
    ...
  }
}
````
`where` applies to all tables. You can extend this system to support per-table filters in future releases.

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


Instruction manual
------------
### Excel-based Translation Import

You can bulk-import translations for a new language via an Excel file.

Steps:
1. Download an existing translation as Excel.
2. Translate its contents for the new language.
3. Upload the translated Excel file.
4. Set the path of this Excel file to the `import_excel` attribute of the `language_list` table.

> ✅ The package will automatically parse the file and save the translations to the appropriate `lang_*` table.


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
