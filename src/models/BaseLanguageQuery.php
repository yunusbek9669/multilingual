<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\Exception;

/**
 *
 * @property-write mixed $customAttributes
 */
class BaseLanguageQuery extends ActiveQuery
{
    public $selectColumns = [];
    protected $customAlias = null;
    protected $langJoined = false;

    public function __construct($modelClass, $config = [])
    {
        parent::__construct($modelClass, $config);
    }

    public function alias($alias): static
    {
        $this->customAlias = $alias;
        $this->from[$alias] = $this->modelClass::tableName();
        return $this;
    }

    public function joinWithLang(string $joinType = 'leftJoin', string $current_table = null, string $alias = null, array $selectColumns = []): static
    {
        if ($this->langJoined) { return $this; }

        $tableName = 'lang_' . Yii::$app->language;

        if (Yii::$app->params['table_available'] ?? false) {
            if ($current_table === null) { $current_table = $this->modelClass::tableName(); }
            if ($alias === null) { $alias = $this->customAlias ?: $current_table; }
            if (empty($selectColumns)) {
                $columns = Yii::$app->db->getTableSchema($current_table)->columns;
                foreach ($columns as $columnName => $column) {
                    if (in_array($column->type, ['string', 'text', 'safe'])) {
                        $coalesce = "COALESCE(NULLIF(json_extract_path_text($current_table$tableName.value, '$columnName'), ''), {$alias}.{$columnName})";
                        $selectColumns[$columnName] = $coalesce;
                    }
                }
            }

            if (!empty($this->select)) {
                foreach ($this->select as $key => $column) {
                    if (str_contains($column, '.')) {
                        $joinAlias = explode('.', $column)[0];
                        if ($joinAlias !== $alias) {
                            if ($this->join) {
                                foreach ($this->join as $join) {
                                    $joinType = lcfirst(str_replace(' ', '', $join[0]));
                                    $joinTable = is_array($join[1]) ? reset($join[1]) : $join[1];
                                    if ($current_table !== $joinTable) {
                                        $clear_column = explode('.', $column)[1];
                                        $coalesce = "COALESCE(NULLIF(json_extract_path_text($joinTable$tableName.value, '$clear_column'), ''), {$column})";
                                        $selectColumns[$key] = $coalesce;
                                        $this->joinWithLang($joinType, $joinTable, $joinAlias, $selectColumns);
                                    }
                                }
                            }
                            if (!str_contains($column, 'COUNT')) {
                                $this->addSelect(array_merge(["{$alias}.*"], $selectColumns));
                            }
                        }
                    }
                }
            } else {
                $this->addSelect(array_merge(["{$alias}.*"], $selectColumns));
            }

            $this->$joinType(
                [$current_table.$tableName => $tableName],
                "$current_table$tableName.table_name = :table_name_$current_table AND $current_table$tableName.table_iteration = {$alias}.id",
                [":table_name_$current_table" => $current_table]
            );

            $this->langJoined = true;
        }

        return $this;
    }

    public function prepare($builder)
    {
        $this->joinWithLang();
        if ($this->customAlias) {
            $tableName = $this->modelClass::tableName();
            $this->from = [$this->customAlias => $tableName];
        }
        return parent::prepare($builder);
    }

    public function orderBy($columns)
    {
        if (is_array($columns) && isset($this->selectColumns)) {
            foreach ($columns as $column => $value)
            {
                if (!empty($this->selectColumns[$column])) {
                    $columns[$this->selectColumns[$column]] = $value;
                    unset($columns[$column]);
                }
            }
        }

        $this->orderBy = $this->normalizeOrderBy($columns);
        return $this;
    }

    /**
     * @throws Exception
     */
    public static function searchAllLanguage($params): array
    {
        return LanguageService::getModelsData($params);
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
                FOR VALUES IN (FALSE);
            ")->execute();
        } catch (\Throwable $e) {
            $response['code'] = 'error';
            $response['status'] = false;
            $response['message'] = "Jadval yaratishda xato: " . self::modErrToStr($e);
        }

        return $response;
    }

    /**
     * lang_* table nomini yangilash
     * @throws Exception
     */
    public static function updateLangTable(string $oldTableName, string $tableName): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $transaction = $db->beginTransaction();
        try {
            $db->createCommand("
                CREATE TABLE {$tableName} (
                    table_name VARCHAR(50) NOT NULL,
                    table_iteration INT NOT NULL,
                    is_static BOOLEAN DEFAULT FALSE,
                    message VARCHAR(100),
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
                FOR VALUES IN (FALSE);
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
            $response['message'] = "Jadvalni yangilashda xato: " . self::modErrToStr($e);
            $response['status'] = false;
            $response['code'] = 'error';
        }
        return $response;
    }


    public static function modErrToStr($model): string
    {
        if (!$model instanceof Model)
        {
            $explode = explode("\n", trim($model->getMessage()));
            return $explode[0] ?? $model;
        }
        $errors = $model->getErrors();
        $string = "";
        foreach ($errors as $error)
        {
            $string = $error[0] . " " . PHP_EOL . $string;
        }

        return $string;
    }
}