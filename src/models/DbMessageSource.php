<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\i18n\MessageSource;
use yii\db\Query;

class DbMessageSource extends MessageSource
{
    private $_messages = [];

    /**
     * Tarjima ma’lumotlarini joriy til jadvalidan bir marta yuklash va keshga saqlash.
     */
    protected function loadMessages($category, $language)
    {
        if (!isset($this->_messages[$language])) {
        }
        $this->_messages[$language] = $this->fetchAllMessages($language);
        return $this->_messages[$language][$category] ?? $this->_messages[$language] ?? [];
    }

    /**
     * Barcha tarjimalarni **bitta so‘rov bilan** bazadan olib kelish va keshga saqlash.
     */
    private function fetchAllMessages($language)
    {
        $tableName = "lang_{$language}";
        $tableExists = Yii::$app->db->createCommand("SELECT to_regclass(:table) IS NOT NULL")
            ->bindValue(':table', $tableName)
            ->queryScalar();
        if ($tableExists)
        {
            return Yii::$app->cache->getOrSet($tableName, function () use ($tableName)
            {
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