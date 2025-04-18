<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\db\Query;
use yii\i18n\MessageSource;

class DbMessageSource extends MessageSource
{
    private $_messages = [];

    /**
     * Tarjima ma’lumotlarini joriy til jadvalidan bir marta yuklash va keshga saqlash.
     */
    protected function loadMessages($category, $language)
    {
        $this->_messages[$language] = $this->fetchAllMessages($language);
        return $this->_messages[$language][$category] ?? $this->_messages[$language] ?? [];
    }

    /**
     * Barcha tarjimalarni **bitta so‘rov bilan** bazadan olib kelish va keshga saqlash.
     */
    private function fetchAllMessages($language)
    {
        $tableName = "lang_{$language}";
        if (Yii::$app->params['table_available']) {
            return Yii::$app->cache->getOrSet($tableName, function () use ($tableName) {
                $rows = (new Query())
                    ->select(['table_name', 'value'])
                    ->from($tableName)
                    ->where(['is_static' => true])
                    ->all();

                $messages = [];

                foreach ($rows as $row) {
                    $decoded = json_decode($row['value'], true);
                    if (is_array($decoded)) {
                        $messages[$row['table_name']] = $decoded;
                    }
                }

                return $messages;
            }, 3600 * 2); // 2 soat kesh saqlash
        }
        return [];
    }
}