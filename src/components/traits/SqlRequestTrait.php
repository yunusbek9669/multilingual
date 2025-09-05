<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\DataReader;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\base\InvalidConfigException;
use yii\db\Query;
use Yunusbek\Multilingual\components\MlConstant;

trait SqlRequestTrait
{
    use JsonTrait;
    use SqlHelperTrait;

    /**
     * @param string|null $table_name
     * @throws Exception
     */
    public static function issetTable(string $table_name = null): bool|DataReader|int|string|null
    {
        if (empty($table_name)) {
            $table_name = MlConstant::LANG_PREFIX . Yii::$app->language;
        }
        $table_name = Yii::$app->db->schema->getRawTableName($table_name);
        return Yii::$app->db->createCommand("SELECT to_regclass(:table) IS NOT NULL")->bindValue(':table', $table_name)->queryScalar();
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
            $select = [];
            $countSelect = [];
            foreach ($jsonData['tables'] as $table_name => $attributes)
            {
                if (isset($params['table-name']) && $params['table-name'] !== $table_name) { continue; }

                /** lang_* jadvallari bo‘yicha sql sozlamalar */
                $sqlHelper = self::sqlHelper($languages, $attributes, $table_name, $isStatic, $isAll, $export);


                /** JSON value::begin */
                $default_lang = array_values(Yii::$app->params['default_language'])[0];
                $allValues = self::jsonBuilder($table_name, $default_lang['name'], $attributes);
                if (!empty($sqlHelper['json_builder'])) {
                    $allValues = $allValues.', '.$sqlHelper['json_builder'];
                }
                /** JSON value::end */


                /** WHERE::begin */
                $where = self::whereBuilder($jsonData, $table_name, $attributes, $sqlHelper);
                /** WHERE::end */


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
            $sql = new Expression("SELECT * FROM ({$select}) AS combined");
            $countSql = new Expression("SELECT COUNT(*) FROM ({$countSelect}) AS combined");
            $totalCount = Yii::$app->db->createCommand($countSql)->queryScalar();
        }
        $pagination = new Pagination([
            'totalCount' => $totalCount,
            'pageSize' => $params['per-page'] ?? MlConstant::LIMIT,
        ]);

        return new SqlDataProvider([
            'sql' => $sql,
            'totalCount' => $pagination->totalCount,
            'pagination' => $pagination,
        ]);
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



    /**
     * yangi lang_* table yaratish
     */
    public static function createLangTable(string $tableName): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];

        $tableName = Yii::$app->db->schema->getRawTableName($tableName);
        try {
            $db->createCommand("
                CREATE TABLE {$tableName} (
                    table_name VARCHAR(50) NOT NULL,
                    table_iteration INT NOT NULL,
                    is_static BOOLEAN DEFAULT FALSE,
                    value JSON NOT NULL,
                    PRIMARY KEY (table_name, table_iteration, is_static)
                ) PARTITION BY LIST (is_static);
            ")->execute();

            $db->createCommand("
                CREATE INDEX idx_{$tableName}_table_name_iteration 
                ON {$tableName} (table_name, table_iteration);
            ")->execute();

            $db->createCommand("
                CREATE TABLE static_{$tableName} PARTITION OF {$tableName}
                FOR VALUES IN (TRUE);
            ")->execute();

            $db->createCommand("
                CREATE TABLE dynamic_{$tableName} PARTITION OF {$tableName}
                FOR VALUES IN (FALSE)
                PARTITION BY LIST (table_name);
            ")->execute();
        } catch (\Throwable $e) {
            $response['code'] = 'error';
            $response['status'] = false;
            $response['message'] = "Jadval yaratishda xato: " . self::errToStr($e);
        }

        return $response;
    }

    /**
     * lang_* table nomini yangilash
     */
    public static function updateLangTable(string $oldTableName, string $tableName): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $tableName = Yii::$app->db->schema->getRawTableName($tableName);
        $transaction = $db->beginTransaction();
        try {
            $db->createCommand("
                CREATE TABLE {$tableName} (
                    table_name VARCHAR(50) NOT NULL,
                    table_iteration INT NOT NULL,
                    is_static BOOLEAN DEFAULT FALSE,
                    value JSON NOT NULL,
                    PRIMARY KEY (table_name, table_iteration, is_static)
                ) PARTITION BY LIST (is_static);
            ")->execute();

            $db->createCommand("
                CREATE INDEX idx_{$tableName}_table_name_iteration 
                ON {$tableName} (table_name, table_iteration);
            ")->execute();

            $db->createCommand("
                CREATE TABLE static_{$tableName} PARTITION OF {$tableName}
                FOR VALUES IN (TRUE);
            ")->execute();

            $db->createCommand("
                CREATE TABLE dynamic_{$tableName} PARTITION OF {$tableName}
                FOR VALUES IN (FALSE)
                PARTITION BY LIST (table_name);
            ")->execute();

            $db->createCommand("
                INSERT INTO {$tableName} (table_name, table_iteration, value, is_static)
                SELECT table_name, table_iteration, value, is_static 
                FROM {$oldTableName};
            ")->execute();

            $db->createCommand("DROP INDEX IF EXISTS idx_{$oldTableName}_table_name_iteration")->execute();
            $db->createCommand("DROP TABLE {$oldTableName}")->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $response['message'] = "Jadvalni yangilashda xato: " . self::errToStr($e);
            $response['status'] = false;
            $response['code'] = 'error';
        }
        return $response;
    }

    /**
     * @throws InvalidConfigException
     */
    public static function setI18n(string $default_table, string $lang_table): array
    {
        $data = (new Query())
            ->select([
                'table_name',
                'table_iteration',
                'value' => new Expression(
                    "(SELECT json_object_agg(key, '') FROM json_each_text(value))"
                )
            ])
            ->from($default_table)
            ->where(['not', ['value' => null]])
            ->all();
        try {
            foreach ($data as $row) {
                self::singleUpsert($lang_table, $row['table_name'], $row['table_iteration'], true, json_decode($row['value'], true));
            }
        } catch (Exception) {}
        return [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function singleUpsert(string $table, string $category, int $iteration, bool $isStatic, array $value): int
    {
        if (!$isStatic) {
            $real_keys = self::getJson()['tables'][$category];

            $existing = Yii::$app->db->createCommand("
                SELECT value FROM {$table}
                WHERE table_name = :category AND table_iteration = :iteration
                LIMIT 1
            ")->bindValues([
                ':category' => $category,
                ':iteration' => $iteration,
            ])->queryOne();

            $existingValue = isset($existing['value']) ? json_decode($existing['value'], true) : [];

            foreach ($value as $key => $val) {
                $existingValue[$key] = $val;
            }

            $value = array_filter(
                $existingValue,
                fn($k) => in_array($k, $real_keys, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        return Yii::$app->db->createCommand()
            ->upsert($table, [
                'table_name' => $category,
                'table_iteration' => $iteration,
                'is_static' => $isStatic,
                'value' => $value
            ], [
                'value' => $value
            ])
            ->execute();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function batchUpsert(string $table, string $category, array $newData): int
    {
        $real_keys = self::getJson()['tables'][$category];

        $ids = array_keys($newData);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_values(array_merge([$category], $ids));
        array_unshift($params, null);
        unset($params[0]);

        $existingRows = Yii::$app->db->createCommand("
            SELECT table_iteration, value
            FROM {$table}
            WHERE table_name = ? AND table_iteration IN ($placeholders)
        ", $params)->queryAll();

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[$row['table_iteration']] = json_decode($row['value'], true) ?? [];
        }

        $rowsToInsert = [];
        foreach ($newData as $table_iteration => $newValue)
        {
            $old = $existingMap[$table_iteration] ?? [];

            foreach ($newValue as $k => $v) {
                $old[$k] = $v;
            }

            $filtered = array_filter(
                $old,
                fn($k) => in_array($k, $real_keys, true),
                ARRAY_FILTER_USE_KEY
            );

            $rowsToInsert[] = [
                $category,
                $table_iteration,
                false,
                json_encode($filtered, JSON_UNESCAPED_UNICODE)
            ];
        }

        $result = self::batchBulk(['table_name', 'table_iteration', 'is_static', 'value'], $rowsToInsert, $table);

        return Yii::$app->db->createCommand($result['sql'], $result['params'])->execute();
    }


    public static function errToStr($model): string
    {
        if (!$model instanceof Model)
        {
            $explode = explode("\n", trim($model->getMessage()));
            return $explode[0] ?? $model;
        }
        $errors = $model->getErrors();
        $string = "";
        foreach ($errors as $error) {
            $string = $error[0] . " " . PHP_EOL . $string;
        }

        return $string;
    }
}