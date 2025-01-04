<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class LanguageManager
{
    public static function getAllLanguages(string $key): array|\yii\db\ActiveRecord|null
    {
        try {
            $Multilingual = BaseLanguageList::find()->where(['status' => 1])->asArray()->all();
            $result = ArrayHelper::map($Multilingual, 'key', function ($model) use ($key) {
                return [
                    'name' => $model['name'],
                    'short_name' => $model['short_name'],
                    'image' => $model['image'],
                    'table' => $model['table'],
                    'active' => $key === $model['key'],
                ];
            });
            $hasActive = false;
            foreach ($result as $item) {
                if (!empty($item['active'])) {
                    $hasActive = true;
                    break;
                }
            }
            if (empty($result) || !$hasActive) {
                foreach (Yii::$app->params['language_list'] as $key => $default_lang) {
                    $default_lang['active'] = true;
                    Yii::$app->params['language_list'][$key] = $default_lang;
                }
            }
            return array_merge(Yii::$app->params['language_list'], $result);
        } catch (Exception $e) {
            foreach (Yii::$app->params['language_list'] as $key => $default_lang) {
                $default_lang['active'] = true;
                Yii::$app->params['language_list'][$key] = $default_lang;
            }
            return Yii::$app->params['language_list'];
        }
    }
}