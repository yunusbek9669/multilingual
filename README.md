multilingual
===========================

A multilingual package for PHP projects

Installation
------------

The preferred way to install the Yii framework is through [composer](https://getcomposer.org/download/).

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

Once the extension is installed, modify the application config/console.php as follows:

```php
'controllerMap' => [
    ...
    'multilingual-migration' => 'Yunusbek\Multilingual\commands\Migrations',
    ...
],
```

After you downloaded and configured multilingual, the next thing you need to do is updating your database schema by applying the migration:

```
php yii multilingual-migration/generate
```

The part to be applied in the form page:

```php
<?php echo \Yunusbek\Multilingual\widgets\SetLanguageAttributes::widget([
    'form' => $form,
    'model' => $model,
    'attribute' => 'attribute_name',
]) ?>
```
