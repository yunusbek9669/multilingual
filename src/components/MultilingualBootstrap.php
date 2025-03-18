<?php

namespace Yunusbek\Multilingual\components;

use yii\base\BootstrapInterface;
use Yunusbek\Multilingual\models\LanguageManager;

class MultilingualBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $app->params['language_list'] = LanguageManager::getAllLanguages('lang');
    }
}