<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\db\Query;
use yii\db\Exception;
use yii\i18n\MessageSource;
use Yunusbek\Multilingual\components\traits\SqlRequestTrait;

class DbMessageSource extends MessageSource
{
    use SqlRequestTrait;

    private $_messages = [];

    /**
     * Tarjima ma’lumotlarini joriy til jadvalidan bir marta yuklash va keshga saqlash.
     * @throws Exception
     */
    protected function loadMessages($category, $language)
    {
        $this->_messages[$language][$category] = json_decode(Yii::$app->params['_i18n'][$category] ?? "", true);
        return $this->_messages[$language][$category] ?? $this->_messages[$language] ?? [];
    }
}