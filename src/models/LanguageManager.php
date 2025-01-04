<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class LanguageManager
{
    /**
     * @throws Exception
     */
    public static function getAllLanguages(string $key): array|\yii\db\ActiveRecord|null
    {
        if (empty(Yii::$app->params['language_list'])) {
            throw new Exception(<<<MESSAGE
                Please add the "language_list" parameter to the (config/params.php) file in your project.
                [
                ____'language_list' => [
                ________'en' => [
                ____________'name' => 'Default Language',
                ____________'short_name' => 'Def',
                ____________'image' => '/path/to/default/language/flag.jpg',
                ____________'active' => false,
                ________]
                ____]
                ]
                MESSAGE
            );
        }
        $hasActive = false;
        $key = Yii::$app->session->get($key);
        try {
            if (!empty($key)) {
                Yii::$app->language = $key;
                $Multilingual = BaseLanguageList::find()->where(['status' => 1])->asArray()->orderBy(['order_number' => SORT_ASC])->all();
                $result = ArrayHelper::map($Multilingual, 'key', function ($model) use ($key) {
                    return [
                        'name' => $model['name'],
                        'short_name' => $model['short_name'],
                        'image' => $model['image'],
                        'table' => $model['table'],
                        'active' => $key === $model['key'],
                    ];
                });
                foreach ($result as $item) {
                    if (!empty($item['active'])) {
                        $hasActive = true;
                        break;
                    }
                }
            } else {
                $result = [];
                Yii::$app->language = array_key_first(Yii::$app->params['language_list']);
            }
        } catch (Exception $e) {
            $result = [];
        }

        if (empty($result) || !$hasActive) {
            foreach (Yii::$app->params['language_list'] as $key => $default_lang) {
                $default_lang['active'] = true;
                Yii::$app->params['language_list'][$key] = $default_lang;
            }
        }
        return array_merge(Yii::$app->params['language_list'], $result);
    }
}