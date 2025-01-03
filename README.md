multilingual
===========================

A multilingual package for PHP projects

Installation
------------

This library works through the Yii2 framework.

Either run

```
composer require yunusbek/multilingual:dev-main
```

or add

```json
"yunusbek/multilingual": "dev-main",
```

to the require section of your composer.json.

Usage
------------

Once the library is installed, add the following to your project settings:

```php
# Add the following code to controllerMap
'controllerMap' => [
    'multilingual-migration' => 'Yunusbek\Multilingual\commands\Migrations',
],


# Add the following code to modules
'modules' => [
    'multilingual' => [
        'class' => Yunusbek\Multilingual\Module::class,
    ],
]
```

The next thing you need to do is updating your database schema by applying the migration of table ```language_list```:

```
php yii multilingual-migration/generate
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

```Additional:``` Add the following button to the top of the created CRUD index page which will take you to the general translations page.

````php
echo Html::a('All translations', ['/multilingual/language/index'], ['class' => 'btn btn-primary', 'target' => '_blank']);
````

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
        'attribute' => 'attribute_name', # or add multiple like ['attribute_name', 'second_attribute_name']
    ]) ?>
    #...
<?php ActiveForm::end(); ?>
```
