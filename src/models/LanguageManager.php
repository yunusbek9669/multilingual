<?php

namespace Yunusbek\Multilingual\models;

use yii\helpers\ArrayHelper;

class LanguageManager
{
    public static function getAllLanguages(string $key): array|\yii\db\ActiveRecord|null
    {
        $Multilingual = BaseLanguageList::find()->where(['status' => 1])->asArray()->all();
        return ArrayHelper::map($Multilingual, 'key', function ($model) use ($key) {
            return [
                'name' => $model['name'],
                'short_name' => $model['short_name'],
                'image' => $model['image'],
                'table' => $model['table'],
                'active' => $key === $model['key'],
            ];
        });
    }
}