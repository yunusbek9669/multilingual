<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

class LanguageService
{
    public static function checkTable(string $table = null): bool
    {
        return (new Query())
            ->from('information_schema.tables')
            ->where(['table_name' => $table])
            ->exists();
    }

    /**  Umumiy extend olgan modellarning ma’lumotlari */
    public static function getModelsData(array $params): array
    {
        $languages = Yii::$app->params['language_list'];
        if (count($languages) === 1) {
            return [];
        }

        $tableResult = self::getLangTables($languages, $params);
        $translate_list = array_fill_keys(array_keys($tableResult['language']), null);

        $result = [
            'total' => $tableResult['total'],
            'header' => [
                'table_name' => Yii::t('multilingual', 'Table Name'),
                'attributes' => Yii::t('multilingual', 'Attributes'),
                'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
                'language' => $tableResult['language']
            ]
        ];

        $body = [];
        /** body shakllantirish */
        foreach ($tableResult['langTables'] as $key => $table) {
            if (!empty($table)) {
                /** lang_* jadvallarining qatorlari bo‘yicha siklga solish */
                foreach ($table as $tableRow) {
                    /** Ro‘yxatni shakllantirish */
                    $tableValue = $tableRow['value'];
                    $unique_name = $tableRow['table_name'] . '::' . $tableRow['table_iteration'];
                    unset($tableRow['is_static']);
                    unset($tableRow['value']);
                    $body[$unique_name]['table_name'] = $tableRow['table_name'];
                    $body[$unique_name]['table_iteration'] = $tableRow['table_iteration'];
                    if (!isset($body[$unique_name]['is_full'])) {
                        $body[$unique_name]['is_full'] = true;
                    }

                    /** lang_* jadvallarining value:json ustuni bo‘yicha siklga solish */
                    foreach ($tableValue as $attribute => $value) {
                        if (empty($body[$unique_name]['translate'][$attribute])) {
                            $body[$unique_name]['translate'][$attribute] = $translate_list;
                        }
                        /** Asosiy modeldan olingan qiymatni qo‘shish */
                        $body[$unique_name]['translate'][$attribute][$key] = $value;
                        if (empty($value)) {
                            $result['header']['language'][$key] += 1;
                            $body[$unique_name]['is_full'] = false;
                        }

                    }
                }
            }
        }
        $result['body'] = $body;

        return $result;
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
        foreach (Yii::$app->params['language_list'] as $language) {
            if (isset($language['table'])) {
                $result['tables'][$language['name']] = $language['table'];
                foreach (array_keys($basePath) as $category) {
                    if ($category !== 'yii' && !str_contains($category, 'yii/')) {
                        $category = str_replace('*', '', $category);
                        $incomplete = (new Query())
                            ->select(['table_name', 'table_iteration', 'value'])
                            ->from($language['table'])
                            ->where([
                                'table_name' => $category,
                                'is_static' => (int)$params['is_static'],
                            ])
                            ->andWhere(new Expression("EXISTS (SELECT 1 FROM json_each_text({$language['table']}.value) kv WHERE kv.value = '')"))
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
        }

        return $result;
    }

    /** Ma’lum bir (lan_*) tablitsadagi tanlangan category messagelari */
    public static function getMessages(string $lang, string $category, array $params): array
    {
        $table = (new Query())->select([$lang => 'value'])->from($lang)->where(['table_name' => $category, 'is_static' => true])->one();
        $data = json_decode($table[$lang], true);
        asort($data);
        $limit = 1000;
        $currentItems = array_slice($data, (isset($params['page']) ? (int)$params['page'] : 0) * $limit, $limit);
        return ['total' => (int)floor(count($data) / $limit), $lang => $currentItems];
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar */
    public static function getLangTables(array $languages, array $params): array
    {
        $result = [];
        $result['total'] = 0;
        $emptyEntries = [];
        $limit = 1000;
        $page = isset($params['page']) ? (int)$params['page'] : 0;
        $offset = $page * $limit;

        $isStatic = (int)($params['is_static'] ?? 0);
        $isAll = (int)($params['is_all'] ?? 0);

        /** Tizimdagi tillar bo‘yicha siklga solish */
        foreach ($languages as $language) {
            $result['language'][$language['name']] = 0;
            if (!empty($language['table'])) {
                /** Bo‘sh qiymatli ("" value) lang_* dan topilgan table_name + table_iteration larni yig‘ish */
                $query = (new Query())
                    ->select(['table_name', 'table_iteration', 'value'])
                    ->from($language['table'])
                    ->where(['is_static' => $isStatic]);
                if ($isAll === 0) {
                    $getEmpty = new Expression("
                        EXISTS (
                            SELECT 1
                            FROM json_each_text({$language['table']}.value) kv
                            WHERE kv.value = '' or kv.value IS NULL
                        )
                    ");
                    $query->andWhere($getEmpty);
                }

                $totalPages = (int)floor((int)$query->count() / $limit);

                $result['total'] = max($result['total'], $totalPages);

                $rows = $query
                    ->limit($limit)
                    ->offset($offset)
                    ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                    ->all();

                foreach ($rows as $row) {
                    $key = $row['table_name'] . '::' . $row['table_iteration'];
                    $emptyEntries[$key] = ['table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration']];
                }
            }
        }

        /** Barcha lang_* dan shu kombinatsiyalarga mos bo‘lgan satrlarni yig‘ish */
        foreach ($languages as $language) {
            if (!empty($language['table'])) {
                $result['langTables'][$language['name']] = [];
                foreach ($emptyEntries as $entry) {
                    $row = (new Query())
                        ->select(['table_name', 'table_iteration', 'value'])
                        ->from($language['table'])
                        ->where([
                            'is_static' => (int)$params['is_static'],
                            'table_name' => $entry['table_name'],
                            'table_iteration' => $entry['table_iteration']
                        ])
                        ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                        ->one();

                    if ($row) {
                        $row['value'] = json_decode($row['value'], true);
                        $result['langTables'][$language['name']][$row['table_name'] . '::' . $row['table_iteration']] = $row;
                    }
                }
                ksort($result['langTables'][$language['name']]);
            }
        }

        /** Asl jadvallardan (table_name bo‘yicha) ham o‘qib olish */
        if (!empty($result['langTables'])) {
            foreach ($languages as $language) {
                if (empty($language['table'])) {
                    $default_lang = [];
                    foreach (reset($result['langTables']) as $entry) {
                        $tableName = $entry['table_name'];
                        $iteration = $entry['table_iteration'];
                        $row = (new Query())
                            ->select(array_keys($entry['value']))
                            ->from($tableName)
                            ->where(['id' => $iteration])
                            ->one();

                        if ($row) {
                            $default_lang[$language['name']][$tableName . '::' . $iteration] = [
                                'table_name' => $tableName,
                                'table_iteration' => $iteration,
                                'value' => $row
                            ];
                        }
                    }
                    $result['langTables'] = array_merge($default_lang, $result['langTables']);
                    break;
                }
            }
        }
        return $result;
    }

    /** Bazadagi barcha tarjimon qilinadigan asosiy tablitsalar
     * @throws Exception
     */
    public static function getDefaultTables(array $languages, array $params): array
    {
        $result = [];
        $allRealTables = [];

        /** Tizimdagi tillar bo‘yicha siklga solish */
        foreach ($languages as $language) {
            if (!empty($language['table'])) {
                $query = (new Query())->select(['table_name', 'value'])->from($language['table'])->orderBy(['table_name' => SORT_ASC])->where(['is_static' => false]);

                if (isset($params['category'])) {
                    $query->andWhere(['table_name' => $params['category']]);
                }

                $table = $query->createCommand()->setSql('SELECT DISTINCT ON ("table_name") "table_name", "value" FROM "' . $language['table'] . '" WHERE "is_static" = FALSE ORDER BY "table_name" ASC')->queryAll();
                foreach ($table as $row) {
                    $allRealTables[$row['table_name']] = json_decode($row['value'], true);
                }
            }
        }

        /** Asl jadvallardan ma‘lumotlarni olish */
        if (!empty($allRealTables)) {
            $allSqlParts = [];
            foreach ($allRealTables as $table_name => $attributes) {
                $jsonFields = [];
                foreach (array_keys($attributes) as $key) {
                    $jsonFields[] = "'$key'";
                    $jsonFields[] = $key;
                }
                $jsonFieldsSql = implode(", ", $jsonFields);

                $allSqlParts[] = "
                    SELECT
                      '{$table_name}' AS table_name,
                      id AS table_iteration,
                      FALSE AS is_static,
                      json_build_object($jsonFieldsSql)::json AS value
                    FROM {$table_name}
                ";
            }
            $finalSql = implode(" UNION ALL ", $allSqlParts) . " ORDER BY table_name, table_iteration";

            $result = Yii::$app->db->createCommand($finalSql)->queryAll();
        }
        return $result;
    }

    /** lang_* tablitsalarini chaqirib olish (Create, Update) */
    public static function setCustomAttributes($model, string $attribute = null): array
    {
        $attributes = [];
        $languages = Yii::$app->params['language_list'];
        if (!empty($languages)) {
            foreach ($languages as $language) {
                if (!empty($language['table']) && self::checkTable($language['table'])) {
                    $lang_table = (new yii\db\Query())
                        ->from($language['table'])
                        ->select('value')
                        ->where([
                            'table_name' => $model::tableName(),
                            'table_iteration' => $model->id
                        ])
                        ->scalar();
                    $data_value = json_decode($lang_table);
                    $name = $language['table'];
                    if ($attribute !== null) {
                        $name = 'Language[' . $name . '][' . $attribute . ']';
                    }
                    $attributes[$name] = !empty($data_value->$attribute) ? $data_value->$attribute : null;
                }
            }
        }
        return $attributes;
    }
}