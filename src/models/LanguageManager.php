<?php

namespace Yunusbek\Multilingual\models;

use yii\helpers\ArrayHelper;

class LanguageManager
{
    public static function getDefaultLanguage(): array
    {
        return [
            'en' => [
                'name' => 'Inglish',
                'short_name' => 'Ing',
                'image' => '/asset/images/flags/en.jpg',
                'table' => null,
            ],
        ];
    }

    public static function getModelLanguages(): array|\yii\db\ActiveRecord|null
    {
        $languageModel = MultiLanguage::find()->where(['status' => 1])->asArray()->all();
        return ArrayHelper::map($languageModel, 'key', function ($model) {
            return [
                'name' => $model['name'],
                'short_name' => $model['short_name'],
                'image' => $model['image'],
                'table' => $model['table'],
            ];
        });
    }

    public static function getAllLanguages($key = null): array|\yii\db\ActiveRecord|null
    {
        $languageList = array_merge(self::getDefaultLanguage(), self::getModelLanguages());
        if (!is_null($key)){
            return $languageList[$key];
        }
        return $languageList;
    }
}