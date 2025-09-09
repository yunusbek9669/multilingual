<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Url;
use Yunusbek\Multilingual\components\traits\SqlRequestTrait;

class LanguageService
{
    use SqlRequestTrait;

    public static function checkTable(string $table = null): bool
    {
        return (new Query())
            ->from('information_schema.tables')
            ->where(['table_name' => $table])
            ->exists();
    }

    /** Bazadagi barcha static qatorlar */
    public static function getI18NData(array $params): array
    {
        $basePath = Yii::$app->i18n->translations ?? [];

        /** kategoriyalar bo‘yicha ro‘yxat */
        $result = [
            'header' => [
                'languages' => Yii::t('multilingual', 'Languages'),
                'categories' => Yii::t('multilingual', 'Categories')
            ],
            'tables' => []
        ];
        foreach (Yii::$app->params['language_list'] as $key => $language) {
            $table_name = $language['table'] ?? MlConstant::LANG_PREFIX.$key;
            $result['tables'][$language['name']] = $table_name;
            foreach (array_keys($basePath) as $category) {
                if ($category !== 'yii' && !str_contains($category, 'yii/')) {
                    $category = str_replace('*', '', $category);
                    $incomplete = (new Query())->select(['table_name', 'table_iteration', 'value'])->from($table_name)
                        ->where(['table_name' => $category, 'is_static' => (int)$params['is_static']])
                        ->andWhere(new Expression("EXISTS (SELECT 1 FROM json_each_text({$table_name}.value) kv WHERE COALESCE(kv.value, '') = '')"))
                        ->one();
                    $count = 0;
                    if (!empty($incomplete)) {
                        foreach (json_decode($incomplete['value']) as $row) { $count += (int)empty($row); }
                        $data = '<a href="'. Url::to(['translate-static', 'lang' => $table_name, 'category' => $category]) .'"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg> '.$category.' <span class="ml-not-translated ' . ($count > 0 ? 'has' : 'not') . '">'.$count.'</span></a>';
                    } else {
                        $exists = (new Query())->from($table_name)->where(['table_name' => $category, 'is_static' => (int)$params['is_static']])->exists();
                        if ($exists) {
                            $data = '<a href="'. Url::to(['translate-static', 'lang' => $table_name, 'category' => $category]) .'"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg> '.$category.' <span class="ml-not-translated not">'.$count.'</span></a>';
                        } else {
                            $data = Yii::t('multilingual', 'No messages to translate were found. Please run the {command} command.', ['command' => '<code style="cursor: pointer">php yii ml-extract/i18n</code>']);
                        }
                    }
                    $result['body'][$language['name']][$category] = $data;
                }
            }
        }

        return $result;
    }

    /** Ma’lum bir (lan_*) tablitsadagi tanlangan category messagelari */
    public static function getMessages(string $lang, string $category, array $params): array
    {
        $table = (new Query())->select([$lang => 'value'])->from($lang)->where(['table_name' => $category, 'is_static' => true])->one();
        $data = json_decode($table[$lang], true);
        $empty = array_filter($data, fn($v) => $v === '');
        $nonEmpty = array_filter($data, fn($v) => $v !== '');
        return [$lang => $empty + $nonEmpty];
    }

    /**  Umumiy extend olgan modellarning ma’lumotlari
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function getModelsData(array $params): array
    {
        $languages = Yii::$app->params['language_list'];
        $tableResult = [];
        $default_lang = '';
        foreach ($languages as $language) {
            if (!isset($language['table'])) { $default_lang = $language['name']; }
            $tableResult['language'][$language['name']] = 0;
        }

        $dataProvider = self::getLangTables($languages, $params);
        $result = [
            'header' => [
                'table_translated' => Yii::t('multilingual', 'Table Name'),
                'attributes' => Yii::t('multilingual', 'Attributes'),
                'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
                'language' => $tableResult['language']
            ],
            'pagination' => $dataProvider->pagination,
            'body' => []
        ];

        if (!empty($dataProvider->getModels())) {
            foreach ($dataProvider->getModels() as $key => $row) {
                $index = $key + 1 + ($dataProvider->pagination->page * $dataProvider->pagination->pageSize);
                $result['body'][$index] = $row;
                $values = json_decode($row['value'], true);
                ksort($values);
                foreach ($languages as $language) {
                    if (!empty($values[$language['name']])) {
                        foreach ($values[$language['name']] as $attribute => $translation) {
                            $result['body'][$index]['translate'][$attribute][$language['name']] = $translation;
                            if (isset($language['table']) && empty($translation)) {
                                $result['header']['language'][$language['name']] += 1;
                            }
                        }
                    } elseif (!empty($values[$default_lang])) {
                        foreach ($values[$default_lang] as $attribute => $translation) {
                            $result['body'][$index]['translate'][$attribute][$language['name']] = '';
                            $result['header']['language'][$language['name']] += 1;
                        }
                    }
                }
            }
        } else {
            if (empty(self::getJson()['tables'])) {
                $result['empty'] = Yii::t('multilingual', 'No tables to translate were found. Please run the {command} command.', ['command' => '<code style="cursor: pointer">php yii ml-extract/attributes</code>']);
            } else {
                $result['empty'] = Yii::t('multilingual', 'Translation tables exist, but no untranslated data was found.');
            }
        }
        return $result;
    }

    /** lang_* tablitsalarini chaqirib olish (Create, Update)
     * @throws InvalidConfigException
     */
    public static function setCustomAttributes($model, string $attribute = null, bool $is_multiple = false, int $index = 0): array
    {
        $jsonData = self::getJson();
        $attributes = [];
        $table_name = $model::tableName();
        $table_index = array_search($table_name, array_keys($jsonData['tables']));
        $languages = Yii::$app->params['language_list'];
        if (!empty($languages) && $table_index) {
            foreach ($languages as $key => $language) {
                if (!empty($language['table']) && self::checkTable($language['table'])) {
                    $lang_table = (new yii\db\Query())
                        ->from($language['table'])
                        ->select('value')
                        ->where([
                            'table_name' => $table_name,
                            'table_iteration' => $model->id
                        ])
                        ->scalar();
                    $data_value = json_decode($lang_table);
                    $name = $key;
                    if ($attribute !== null) {
                        if ($is_multiple) {
                            $name = MlConstant::MULTILINGUAL."[$name][$table_index][$index][$attribute]";
                        } else {
                            $name = MlConstant::MULTILINGUAL."[$name][$table_index][$attribute]";
                        }
                    }
                    $attributes[$name] = !empty($data_value->$attribute) ? $data_value->$attribute : null;
                }
            }
        }
        return $attributes;
    }
}