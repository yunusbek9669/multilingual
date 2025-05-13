<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use Yunusbek\Multilingual\commands\Messages;

class LanguageService
{
    const LIMIT = 500;
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

    /**  Umumiy extend olgan modellarning ma’lumotlari
     * @throws Exception
     */
    public static function getModelsData(array $params): array
    {
        $languages = Yii::$app->params['language_list'];
        if (count($languages) === 1) {
            return [];
        }
        $tableResult = [];
        $default_lang = '';
        foreach ($languages as $language) {
            if (!isset($language['table'])) { $default_lang = $language['name']; }
            $tableResult['language'][$language['name']] = 0;
        }

        $dataProvider = self::getLangTables($languages, $params);
        $result = [
            'header' => [
                'table_name' => Yii::t('multilingual', 'Table Name'),
                'attributes' => Yii::t('multilingual', 'Attributes'),
                'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
                'language' => $tableResult['language']
            ],
            'dataProvider' => $dataProvider,
        ];

        foreach ($dataProvider->getModels() as $key => $row) {
            $result['body'][$key] = [
                'table_name' => $row['table_name'],
                'table_iteration' => $row['table_iteration'],
                'is_full' => $row['is_full'],
            ];
            $values = json_decode($row['translate'], true);
            foreach ($languages as $language) {
                if (!empty($values[$language['name']])) {
                    foreach ($values[$language['name']] as $attribute => $translation) {
                        $result['body'][$key]['translate'][$attribute][$language['name']] = $translation;
                        if (isset($language['table']) && empty($translation)) {
                            $result['header']['language'][$language['name']] += 1;
                        }
                    }
                } else {
                    foreach ($values[$default_lang] as $attribute => $translation) {
                        $result['body'][$key]['translate'][$attribute][$language['name']] = '';
                        $result['header']['language'][$language['name']] += 1;
                    }
                }
            }
        }
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
        $limit = self::LIMIT;
        $currentItems = array_slice($data, (isset($params['page']) ? (int)$params['page'] : 0) * $limit, $limit);
        return ['total' => (int)floor(count($data) / $limit), $lang => $currentItems];
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar
     * @throws Exception
     */
    public static function getLangTables(array $languages, array $params): SqlDataProvider|array
    {
        $dataProvider = [];
        $isStatic = (int)($params['is_static'] ?? 0);
        $isAll = (int)($params['is_all'] ?? 0);

        $jsonData = self::getJson();
        $default_lang = array_values(Yii::$app->params['default_language'])[0];
        if (!empty($jsonData['tables']))
        {
            /** lang_* jadvallari bo‘yicha sql sozlamalar yasash uchun */
            function sqlHelper(array $languages, array $attributes, string $table_name, int $isStatic, int $isAll): array
            {
                $joins = [];
                $langValues = [];
                $conditions = [];
                $is_full = new Expression("CASE ");
                foreach ($languages as $language) {
                    if (isset($language['table'])) {
                        $name = $language['name'];
                        $lang_table = $language['table'];

                        /** JOIN yasab berish uchun */
                        $joins[$lang_table] = "LEFT JOIN $lang_table AS $lang_table ON $table_name.id = $lang_table.table_iteration AND '$table_name' = $lang_table.table_name";

                        /** is_full:BOOLEAN to‘liq tarjima qilinganligini tekshirish */
                        $is_full .= new Expression("WHEN {$lang_table}.value::jsonb = '{}' OR EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE kv.value = '' OR kv.value IS NULL) THEN FALSE ");

                        /** JSON ustunida mavjud bo'lmagan attributelarni qo‘shib berish */
                        $build = [];
                        $langValues[$name] = new Expression("'$name', $lang_table.value::jsonb || jsonb_build_object(");
                        foreach ($attributes as $attribute) {
                            $build[] = new Expression("'$attribute', COALESCE($lang_table.value->>'$attribute', '') ");
                            $is_full .= new Expression("WHEN $table_name.$attribute IS NULL OR COALESCE($table_name.$attribute, '') = '' THEN FALSE ");
                            $is_full .= new Expression("WHEN NOT jsonb_path_exists({$lang_table}.value::jsonb, '$.$attribute') THEN FALSE ");
                        }
                        $langValues[$name] .= implode(", ", $build);
                        $langValues[$name] .= ")";


                        /** Qo‘shimcha shartlar */
                        $conditions[$lang_table] = new Expression("($lang_table.is_static IS NULL OR $lang_table.is_static::int = $isStatic)");
                        /** Bo‘sh qiymatlilarni yig‘ish */
                        if ($isAll === 0) {
                            $conditions[$lang_table] .= new Expression(" AND ($lang_table.is_static IS NULL OR EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE kv.value = '' OR kv.value IS NULL))");
                        }
                    }
                }
                $is_full .= "ELSE TRUE END AS is_full";

                return [
                    'joins' => implode(" ", $joins),
                    'langValues' => implode(", ", $langValues),
                    'conditions' => $conditions,
                    'is_full' => $is_full
                ];
            }

            $sql = "SELECT * FROM (";
            $countSql = "SELECT COUNT(*) FROM (";
            $select = [];
            $countSelect = [];
            foreach ($jsonData['tables'] as $table_name => $attributes)
            {
                /** lang_* jadvallari bo‘yicha sql sozlamalar */
                $sqlHelper = sqlHelper($languages, $attributes, $table_name, $isStatic, $isAll);

                /** JSON value */
                $realValues = [];
                foreach ($attributes as $attribute) {
                    $realValues[] = "'$attribute', $attribute";
                }
                $json = implode(", ", $realValues);
                $default_name = $default_lang['name'];
                $allValues = join(", ", array_merge(["'$default_name', jsonb_build_object($json)"], [$sqlHelper['langValues']]));

                /** WHERE */
                $defaultWhere = [];
                foreach ($jsonData['where'] as $key => $value) {
                    $defaultWhere[] = "$table_name.$key = $value";
                }
                $where = implode(" AND ", array_merge($defaultWhere, $sqlHelper['conditions']));

                $select[] = "(SELECT '$table_name' AS table_name, $table_name.id AS table_iteration, {$sqlHelper['is_full']}, jsonb_build_object($allValues) AS translate FROM $table_name {$sqlHelper['joins']} WHERE $where ORDER BY $table_name.id ASC)";
                $countSelect[] = "(SELECT $table_name.id FROM $table_name {$sqlHelper['joins']} WHERE $where)";
            }
            $select = implode(" UNION ALL ", $select);
            $countSelect = implode(" UNION ALL ", $countSelect);
            $sql .= "$select) AS combined";
            $countSql .= "$countSelect) AS combined";

            $pagination = new Pagination([
                'totalCount' => Yii::$app->db->createCommand($countSql)->queryScalar(),
                'pageSize' => self::LIMIT,
            ]);

            $dataProvider = new SqlDataProvider([
                'sql' => $sql,
                'totalCount' => $pagination->totalCount,
                'pagination' => $pagination,
            ]);
        }
        return $dataProvider;
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