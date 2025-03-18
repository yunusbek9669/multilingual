<?php

namespace Yunusbek\Multilingual\components;

use yii\i18n\I18N;

class MultilingualI18N extends I18N
{
    public function init()
    {
        parent::init();
        if (!isset($this->translations['multilingual']) && !isset($this->translations['multilingual*'])) {
            $this->translations['multilingual'] = [
                'class' => 'Yunusbek\Multilingual\models\DbMessageSource'
            ];
        }
    }
}