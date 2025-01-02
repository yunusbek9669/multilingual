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
'controllerMap' => [
    #...
    'multilingual-migration' => 'Yunusbek\Multilingual\commands\Migrations',
    #...
],
```

The next thing you need to do is updating your database schema by applying the migration of table ```language_list```:

```
php yii multilingual-migration/generate
```

Inheritance from the ```BaseLanguageList``` class and additional settings for the class created for the ```language_list``` table:

````php
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

The models to be translated are inherited from the ```MultilingualModel``` model.

```php
class ReferenceModel extends MultilingualModel
{
    #...model settings.
}
```

The part to be applied in the form page:

```php
<?php echo \Yunusbek\Multilingual\widgets\SetLanguageAttributes::widget([
    'form' => $form,
    'model' => $model,
    'attribute' => 'attribute_name',
]) ?>
```
