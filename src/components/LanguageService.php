<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

class LanguageService
{
    private static array $jsonData = [];

    /**
     * @throws InvalidConfigException
     */
    public static function getJson() {
        $jsonPath = Yii::getAlias('@app') .'/'. MlConstant::MULTILINGUAL.'.json';
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            if (json_last_error() === JSON_ERROR_NONE) {
                self::$jsonData = json_decode($jsonContent, true);
            } else {
                throw new InvalidConfigException(Yii::t('multilingual', 'Invalid JSON structure detected in {jsonPath}.', ['jsonPath' => $jsonPath]));
            }
        } else {
            throw new InvalidConfigException(Yii::t('multilingual', 'The file {jsonPath} could not be found. Please run the {command} command.', ['jsonPath' => $jsonPath, 'command' => '" php yii ml-extract/i18n "']));
        }
        foreach (self::$jsonData['tables'] as &$fields) {
            sort($fields);
        }
        unset($fields);
        ksort(self::$jsonData);
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
            'dataProvider' => $dataProvider,
            'body' => []
        ];

        if (!empty($dataProvider->getModels())) {
            foreach ($dataProvider->getModels() as $key => $row) {
                $result['body'][$key] = $row;
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
     * @throws InvalidConfigException
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
            function isFull(array &$result, string $attribute, string $lang_table, string $table_name): void
            {
                $result['is_full'] .= new Expression("
                    WHEN COALESCE({$table_name}.{$attribute}, '') <> '' AND (NOT jsonb_path_exists({$lang_table}.value::jsonb, '$.{$attribute}') OR COALESCE({$lang_table}.value::jsonb ->> '{$attribute}', '') = '') THEN FALSE 
                    WHEN {$lang_table}.value IS NULL THEN FALSE 
                ");
            }

            /** tarjima qilinadigan jadvallar attributelari uchun */
            function setExists(string $lang_table): string
            {
                return new Expression("EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE COALESCE(kv.value, '') = '')");
            }

            /** tarjima qilinadigan ustunlarni bitta jsonga saralab olish */
            function jsonBuilder(string $table_name, string $lang_name, array $attributes, string $lang_table = null): string
            {
                $selects = [];
                foreach ($attributes as $attribute) {
                    $value = "{$table_name}.{$attribute}";
                    if (!empty($lang_table)) {
                        $value = "COALESCE({$lang_table}.value->>'{$attribute}', '')";
                    }
                    $selects[] = new Expression("SELECT
                        '{$attribute}' AS key,
                        CASE WHEN {$table_name}.{$attribute} IS NOT NULL AND {$table_name}.{$attribute} <> '' THEN {$value} END AS value
                    ");
                }
                $selects = implode(' UNION ALL ', $selects);
                return new Expression("
                    '{$lang_name}', (
                        SELECT jsonb_object_agg(key, value)
                        FROM ({$selects}) as fields
                        WHERE value IS NOT NULL
                    )"
                );
            }

            /** lang_* jadvallari bo‘yicha sql sozlamalar yasash uchun */
            function sqlHelper(array $languages, array $attributes, string $table_name, int $isStatic, int $isAll, bool $export): array
            {
                $result = [
                    'joins' => [],
                    'json_builder' => [],
                    'is_full' => var_export((bool)$isStatic,true)." as is_full",
                ];

                if ($export) {
                    $result['is_full'] = var_export((bool)$isStatic,true)." as is_static";
                } elseif (count($languages) > 1) {
                    $conditions = [];
                    $result['is_full'] = new Expression("CASE ");
                    foreach ($languages as $language) {
                        if (isset($language['table'])) {
                            $name = $language['name'];
                            $lang_table = $language['table'];
                            $exists = setExists($lang_table);

                            /** JOIN yasab berish uchun */
                            $result['joins'][$lang_table] = new Expression("LEFT JOIN $lang_table AS $lang_table ON $table_name.id = $lang_table.table_iteration AND '$table_name' = $lang_table.table_name");

                            /** is_full:BOOLEAN to‘liq tarjima qilinganligini tekshirish */
                            $result['is_full'] .= new Expression("WHEN {$lang_table}.value::jsonb = '{}' THEN FALSE ");

                            /** JSON ustunida mavjud bo'lmagan attributelarni qo‘shib berish */
                            foreach ($attributes as $attribute) {
                                isFull($result, $attribute, $lang_table, $table_name);
                            }
                            $result['json_builder'][$name] = jsonBuilder($table_name, $name, $attributes, $lang_table);;

                            /** Qo‘shimcha shartlar */
                            $conditions[$lang_table] = new Expression("($lang_table.is_static IS NULL OR $lang_table.is_static::int = $isStatic)");

                            /** Faqat bo‘sh qiymatlilarni yig‘ish */
                            if ($isAll === 0) {
                                $conditions[$lang_table] .= new Expression(" AND ($lang_table.is_static IS NULL OR {$exists})");
                            }
                        }
                    }

                    $result['is_full'] .= "ELSE TRUE END AS is_full";
                    $result['conditions'] = implode(' ', $conditions);
                }
                $result['json_builder'] = implode(", ", $result['json_builder']);
                $result['joins'] = implode(" ", $result['joins']);
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
                $default_lang = array_values(Yii::$app->params['default_language'])[0];
                $allValues = jsonBuilder($table_name, $default_lang['name'], $attributes);

                /** WHERE */
                $where = [];
                foreach ($jsonData['where'] as $key => $value) {
                    $where[] = "$table_name.$key = $value";
                }
                $where = implode(" AND ", $where);

                if (!$export) {
                    if (!empty($sqlHelper['json_builder'])) {
                        $allValues = $allValues.', '.$sqlHelper['json_builder'];
                    }
                    if (!empty($sqlHelper['conditions'])) {
                        $where = implode(" AND ", array_merge([$where], [$sqlHelper['conditions']]));
                    }
                }

                $tableTextFormat = str_replace("'", "''", self::tableTextFormat($table_name, true));
                $select[] = new Expression("(
                    SELECT 
                        '$tableTextFormat' AS table_translated, 
                        '$table_name' AS table_name, 
                        $table_name.id AS table_iteration, 
                        {$sqlHelper['is_full']}, 
                        jsonb_build_object($allValues) AS value 
                    FROM $table_name 
                    {$sqlHelper['joins']} 
                    WHERE $where 
                    ORDER BY $table_name.id ASC
                )");
                $countSelect[] = new Expression("(SELECT $table_name.id FROM $table_name {$sqlHelper['joins']} WHERE $where)");
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
    public static function tableTextFormList(array $tables, bool $i18n = false): array
    {
        $list = [];
        foreach ($tables as $table_name => $table) {
            $list[$table_name] = self::tableTextFormat($table_name, $i18n);
        }
        return $list;
    }

    /** Jadval nomini matnli ro‘yxati */
    public static function tableTextFormat(string $table_name, bool $i18n = false): string
    {
        if ($i18n) {
            return Yii::t(MlConstant::MULTILINGUAL, str_replace('_', ' ', ucwords($table_name, '_')));
        }
        return str_replace('_', ' ', ucwords($table_name, '_'));
    }
}