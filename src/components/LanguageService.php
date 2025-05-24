<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

class LanguageService
{
    private static $jsonData = [];

    public static function getJson() {
        $jsonPath = Yii::getAlias('@app') .'/'. MlConstant::MULTILINGUAL.'.json';
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
                        ->andWhere(new Expression("EXISTS (SELECT 1 FROM json_each_text({$table_name}.value) kv WHERE kv.value = '')"))
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
                'table_name' => Yii::t('multilingual', 'Table Name'),
                'attributes' => Yii::t('multilingual', 'Attributes'),
                'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
                'language' => $tableResult['language']
            ],
            'dataProvider' => $dataProvider,
            'body' => []
        ];

        if (!empty($dataProvider->getModels())) {
            foreach ($dataProvider->getModels() as $key => $row) {
                $result['body'][$key] = [
                    'table_name' => $row['table_name'],
                    'table_iteration' => $row['table_iteration'],
                    'is_full' => $row['is_full'],
                ];
                $values = json_decode($row['value'], true);
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
        } else {
            $result['empty'] = Yii::t('multilingual', 'No tables to translate were found. Please run the {command} command.', ['command' => '<code onclick="copyText(this.innerText)" style="cursor: pointer">php yii ml-extract/attributes</code>']);
        }
        return $result;
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar
     * @throws Exception
     */
    public static function getLangTables(array $languages, array $params, bool $export = false): SqlDataProvider|array
    {
        $isStatic = (int)($params['is_static'] ?? 0);
        $isAll = (int)($params['is_all'] ?? 0);

        $sql = 'SELECT * FROM language_list WHERE id = -1 ORDER BY id ASC';
        $totalCount = 0;
        $jsonData = self::getJson();
        if (!empty($jsonData['tables']))
        {
            /** tarjima qilinadigan jadvallar attributelari uchun */
            function setAttr(array &$build, array &$result, array &$jsonConditions, string $attribute, string $lang_table, string $table_name): void
            {
                $build[] = new Expression("'$attribute', COALESCE($lang_table.value->>'$attribute', '') ");
                $result['is_full'] .= new Expression("
                    WHEN $table_name.$attribute IS NULL OR COALESCE($table_name.$attribute, '') = '' THEN FALSE 
                    WHEN NOT jsonb_path_exists({$lang_table}.value::jsonb, '$.$attribute') THEN 
                    FALSE 
                ");
                $jsonConditions[$lang_table][] = new Expression("NOT jsonb_path_exists({$lang_table}.value::jsonb, '$.$attribute')");
            }

            /** lang_* jadvallari bo‘yicha sql sozlamalar yasash uchun */
            function sqlHelper(array $languages, array $attributes, string $table_name, int $isStatic, int $isAll, bool $export): array
            {
                $result = [
                    'joins' => [],
                    'langValues' => [],
                    'is_full' => var_export((bool)$isStatic,true)." as is_full",
                ];
                $commonConditions = [];
                $isAllConditions = [];

                if ($export) {
                    $result['is_full'] = var_export((bool)$isStatic,true)." as is_static";
                    $result['langValues'] = [];
                } elseif (count($languages) > 1) {
                    $jsonConditions = [];
                    $result['is_full'] = new Expression("CASE ");
                    foreach ($languages as $language) {
                        if (isset($language['table'])) {
                            $name = $language['name'];
                            $lang_table = $language['table'];

                            /** JOIN yasab berish uchun */
                            $result['joins'][$lang_table] = "LEFT JOIN $lang_table AS $lang_table ON $table_name.id = $lang_table.table_iteration AND '$table_name' = $lang_table.table_name";

                            /** is_full:BOOLEAN to‘liq tarjima qilinganligini tekshirish */
                            $result['is_full'] .= new Expression("WHEN {$lang_table}.value::jsonb = '{}' OR EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE kv.value = '' OR kv.value IS NULL) THEN FALSE ");

                            /** JSON ustunida mavjud bo'lmagan attributelarni qo‘shib berish */
                            $build = [];
                            $result['langValues'][$name] = new Expression("'$name', $lang_table.value::jsonb || jsonb_build_object(");
                            foreach ($attributes as $attribute) {
                                setAttr($build, $result, $jsonConditions, $attribute, $lang_table, $table_name);
                            }
                            $jsonConditions[$lang_table] = implode(' OR ', $jsonConditions[$lang_table]);
                            $result['langValues'][$name] .= implode(", ", $build);
                            $result['langValues'][$name] .= ")";


                            /** Qo‘shimcha shartlar */
                            $commonConditions[$lang_table] = new Expression("($lang_table.is_static IS NULL OR $lang_table.is_static::int = $isStatic)");
                            /** Bo‘sh qiymatlilarni yig‘ish */
                            if ($isAll === 0) {
                                $isAllConditions[$lang_table] = new Expression("({$jsonConditions[$lang_table]} OR (EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE COALESCE(kv.value, '') = '' OR kv.value IS NULL)))");
                            }
                        }
                    }
                    $result['is_full'] .= "ELSE TRUE END AS is_full";
                    if (!empty($isAllConditions)) {
                        $isAllConditions = ['('.implode(' OR ', $isAllConditions).')'];
                    }
                    $result['langValues'] = [implode(", ", $result['langValues'])];

                }
                $result['joins'] = implode(" ", $result['joins']);
                $result['conditions'] = implode(' AND ', array_merge($commonConditions, $isAllConditions));
                return $result;
            }

            $sql = "SELECT * FROM (";
            $countSql = "SELECT COUNT(*) FROM (";
            $select = [];
            $countSelect = [];
            foreach ($jsonData['tables'] as $table_name => $attributes)
            {
                /** lang_* jadvallari bo‘yicha sql sozlamalar */
                $sqlHelper = sqlHelper($languages, $attributes, $table_name, $isStatic, $isAll, $export);

                /** JSON value */
                $realValues = [];
                foreach ($attributes as $attribute) {
                    $realValues[] = "'$attribute', $attribute";
                }
                $json = implode(", ", $realValues);
                $allValues = $json;
                if (!$export) {
                    $default_lang = array_values(Yii::$app->params['default_language'])[0];
                    $allValues = "'{$default_lang['name']}', jsonb_build_object($json)";
                    if (!empty($sqlHelper['langValues'][0])) {
                        $allValues = join(", ", array_merge([$allValues], $sqlHelper['langValues']));
                    }
                }

                /** WHERE */
                $where = [];
                foreach ($jsonData['where'] as $key => $value) {
                    $where[] = "$table_name.$key = $value";
                }
                $where = implode(" AND ", $where);
                if (!empty($sqlHelper['conditions'])) {
                    $where = implode(" AND ", array_merge([$where], [$sqlHelper['conditions']]));
                }

                $select[] = "(SELECT '$table_name' AS table_name, $table_name.id AS table_iteration, {$sqlHelper['is_full']}, jsonb_build_object($allValues) AS value FROM $table_name {$sqlHelper['joins']} WHERE $where ORDER BY $table_name.id ASC)";
                $countSelect[] = "(SELECT $table_name.id FROM $table_name {$sqlHelper['joins']} WHERE $where)";
            }
            $select = implode(" UNION ALL ", $select);
            $countSelect = implode(" UNION ALL ", $countSelect);
            $sql .= "$select) AS combined";
            $countSql .= "$countSelect) AS combined";
            $totalCount = Yii::$app->db->createCommand($countSql)->queryScalar();
        }
        $pagination = new Pagination([
            'totalCount' => $totalCount,
            'pageSize' => MlConstant::LIMIT,
        ]);

        return new SqlDataProvider([
            'sql' => $sql,
            'totalCount' => $pagination->totalCount,
            'pagination' => $pagination,
        ]);
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

    /** Jadval nomlarini matnli ro‘yxati */
    public static function tableTextFormList(array $tables): array
    {
        $list = [];
        foreach ($tables as $table_name => $table) {
            $list[$table_name] = str_replace('_', ' ', ucwords($table_name, '_'));
        }
        return $list;
    }
}