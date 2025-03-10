<?php
namespace Yunusbek\Multilingual;

use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap = array_merge($app->controllerMap, [
                'ml-extract-i18n' => [
                    'class' => 'Yunusbek\Multilingual\commands\MessagesController',
                    'configFile' => '@vendor/yunusbek/multilingual/src/config/i18n.php',
                ],
                'ml-extract-attributes' => [
                    'class' => 'Yunusbek\Multilingual\commands\MessagesController',
                    'configFile' => '@vendor/yunusbek/multilingual/src/config/attributes.php',
                ],
            ]);
        }
    }
}