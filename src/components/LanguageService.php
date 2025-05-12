<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use Yunusbek\Multilingual\commands\Messages;

class LanguageService
{
    private static $jsonData = [];

    public static function getJson() {
        $id = 'messages';
        $module = Yii::$app;
        $message = new Messages($id, $module);
        $jsonPath = Yii::getAlias('@app') .'/'. $message->json_file_name.'.json';
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            if (json_last_error() === JSON_ERROR_NONE) {
                self::$jsonData = json_decode($jsonContent, true);
            }
        }
        return self::$jsonData;
    }

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
        $limit = 500;
        $page = isset($params['page']) ? (int)$params['page'] : 0;
        $offset = $page * $limit;

        $isStatic = (int)($params['is_static'] ?? 0);
        $isAll = (int)($params['is_all'] ?? 0);

        $jsonData = self::getJson();
        $default_lang = array_values(Yii::$app->params['default_language'])[0];
        $lang_categories = [];
        if (!empty($jsonData['tables'])) {
            $count = 0;
            $calculated_limit = $limit;
            $calculated_offset = $offset;
            if ($isAll === 0)
            {
                $lang_tables = [];
                $joins = [];
                $conditions = [];
                foreach ($languages as $language) {
                    $result['language'][$language['name']] = 0;
                    if (isset($language['table'])) {
                        $lang_tables[$language['name']] = $language['table'];
                        $joins[$language['table']] = [
                            'first' => $language['table'].' as '.$language['table'],
                            'second' => ".id = ".$language['table'].".table_iteration and ".$language['table'].".table_name = "
                        ];
                        $conditions[$language['table']] = new Expression("
                            EXISTS (
                                SELECT 1
                                FROM json_each_text({$language['table']}.value) kv
                                WHERE kv.value = '' or kv.value IS NULL
                            )
                        ");
                    }
                }

                foreach ($jsonData['tables'] as $table_name => $attributes)
                {
                    /** Bo‘sh qiymatli ("" value) lang_* dan topilgan table_name + table_iteration larni yig‘ish */
                    $select = [
                        'table_name' => new Expression("'$table_name'"),
                        'table_iteration' => new Expression("$table_name.id")
                    ];
                    $realValues = [];
                    foreach ($attributes as $attribute) {
                        $realValues[] = "'$attribute', $attribute";
                    }
                    $json = join(", ", $realValues);
                    $default_name = $default_lang['name'];
                    $langValues = ["'$default_name', jsonb_build_object($json)"];

                    $query = (new Query())
                        ->from($table_name);
                    foreach ($lang_tables as $name => $lang_table) {
                        $langValues[] = "'$name', $lang_table.value";
                        $query->where([$lang_table.'.is_static' => $isStatic]);
                        $query->andWhere($conditions[$lang_table]);
                        $query->leftJoin($joins[$lang_table]['first'], "$table_name".$joins[$lang_table]['second']."'$table_name'");
                    }
                    $allValues = join(", ", $langValues);
                    $select = array_merge($select, [ new \yii\db\Expression("
                        jsonb_build_object($allValues) AS all_values
                    ")]);

                    $rows = $query
                        ->select($select)
                        ->limit($calculated_limit)
                        ->offset($calculated_offset)
                        ->orderBy([$table_name.'.id' => SORT_ASC])
                        ->all();

                    foreach ($rows as $row) {
                        $count++;
                        $values = json_decode($row['all_values'], true);
                        foreach ($result['language'] as $lang_name => $value) {
                            $result['langTables'][$lang_name][] = ['table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration'], 'value' => $values[$lang_name]];
                        }
                        if ($count === $limit) break 2;
                    }
                }
            } else {
                /** Asl jadvallardan (table_name bo‘yicha) ham o‘qib olish */
                foreach ($jsonData['tables'] as $table_name => $attributes)
                {
                    $query = (new Query())
                        ->select(array_merge(['id'], $attributes))
                        ->from($table_name);
                    if (!empty($jsonData['where'])) {
                        $query->andWhere($jsonData['where']);
                    }

                    $totalPages = (int)floor((int)$query->count() / $limit);
                    $result['total'] = max($result['total'], $totalPages);
                    $row = $query
                        ->limit($calculated_limit)
                        ->offset($calculated_offset)
                        ->orderBy(['id' => SORT_ASC])
                        ->all();
                    foreach ($row as $entry) {
                        $iteration = $entry['id'];
                        unset($entry['id']);
                        $result['langTables'][$default_lang['name']][] = [
                            'table_name' => $table_name,
                            'table_iteration' => $iteration,
                            'value' => $entry
                        ];
                        $count++;
                        $lang_categories[$table_name][] = $iteration;
                        if ($count === $limit) break 2;
                    }
                    $calculated_limit -= $count;
                    if ($calculated_limit < 0) {
                        break;
                    }
                }

                /** Barcha lang_* dan shu kombinatsiyalarga mos bo‘lgan satrlarni yig‘ish */
                foreach ($languages as $language) {
                    $result['language'][$language['name']] = 0;
                    if (!empty($language['table'])) {
                        $result['langTables'][$language['name']] = [];
                        foreach ($lang_categories as $category => $iterations) {
                            $row = (new Query())
                                ->select(['table_name', 'table_iteration', 'value'])
                                ->from($language['table'])
                                ->where([
                                    'is_static' => $isStatic,
                                    'table_name' => $category
                                ])
                                ->andWhere([
                                    'in', 'table_iteration', $iterations
                                ])
                                ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                                ->one();

                            if ($row) {
                                $row['value'] = json_decode($row['value'], true);
                                $result['langTables'][$language['name']][] = $row;
                            }
                        }
                    }
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