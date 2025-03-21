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
    public static function getAllLanguages(string $session_key): array|\yii\db\ActiveRecord|null
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
        $key = Yii::$app->session->get($session_key);
        if (empty($key)) {
            $key = array_key_first(Yii::$app->params['language_list']);
            Yii::$app->session->set($session_key, $key);
        }
        try {
            Yii::$app->language = $key;
            $Multilingual = BaseLanguageList::find()->asArray()->orderBy(['name' => SORT_ASC])->all();
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
        } catch (Exception $e) {
            $result = [];
        }

        if (empty($result) || !$hasActive) {
            foreach (Yii::$app->params['language_list'] as $def_key => $default_lang) {
                $default_lang['active'] = true;
                Yii::$app->language = $def_key;
                Yii::$app->session->set($session_key, $def_key);
                Yii::$app->params['language_list'][$def_key] = $default_lang;
                break;
            }
        }

        $language_list = array_merge(Yii::$app->params['language_list'], $result);
        $filtered_languages = array_filter($language_list, fn($lang) => !empty($lang['active']));
        Yii::$app->params['active_language'] = reset($filtered_languages);
        return $language_list;
    }
}