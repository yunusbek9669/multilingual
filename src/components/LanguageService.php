<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
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
                    $incomplete = (new Query())
                        ->select(['table_name', 'table_iteration', 'value'])
                        ->from($table_name)
                        ->where([
                            'table_name' => $category,
                            'is_static' => (int)$params['is_static'],
                        ])
                        ->andWhere(new Expression("EXISTS (SELECT 1 FROM json_each_text({$table_name}.value) kv WHERE COALESCE(kv.value, '') = '')"))
                        ->one();
                    $count = 0;
                    if (!empty($incomplete)) {
                        foreach (json_decode($incomplete['value']) as $row) {
                            $count += (int)empty($row);
                        }
                    }
                    $result['body'][$language['name']][$category] = $category.' '.'<span class="ml-not-translated ' . ($count > 0 ? 'has' : 'not') . '">'.$count.'</span>';
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
        asort($data);
        $limit = MlConstant::LIMIT;
        $currentItems = array_slice($data, (isset($params['page']) ? (int)$params['page'] : 0) * $limit, $limit);
        return ['total' => (int)floor(count($data) / $limit), $lang => $currentItems];
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
            $result['empty'] = Yii::t('multilingual', 'No tables to translate were found. Please run the {command} command.', ['command' => '<code onclick="copyText(this.innerText)" style="cursor: pointer">php yii ml-extract/attributes</code>']);
        }
        return $result;
    }

    /** lang_* tablitsalarini chaqirib olish (Create, Update)
     * @throws InvalidConfigException
     */
    public static function setCustomAttributes($model, string $attribute = null): array
    {
        $jsonData = self::getJson();
        $attributes = [];
        $table_name = $model::tableName();
        $table_index = array_search($table_name, array_keys($jsonData['tables']));
        $languages = Yii::$app->params['language_list'];
        if (!empty($languages)) {
            foreach ($languages as $language) {
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
                    $name = $language['table'];
                    if ($attribute !== null) {
                        $name = MlConstant::MULTILINGUAL."[$name][$table_index][$attribute]";
                    }
                    $attributes[$name] = !empty($data_value->$attribute) ? $data_value->$attribute : null;
                }
            }
        }
        return $attributes;
    }
}