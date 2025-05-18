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
    protected $joinList = [];
    protected $customAlias = null;
    protected $langTable = BaseLanguageList::LANG_TABLE_PREFIX;

    public function __construct($modelClass, $config = [])
    {
        parent::__construct($modelClass, $config);
        $this->langTable .= Yii::$app->language;
    }

    public function alias($alias): static
    {
        $this->customAlias = $alias;
        $this->from[$alias] = $this->modelClass::tableName();
        return $this;
    }

    public function joinWithLang(string $joinType = 'leftJoin', string $current_table = null): static
    {
        if (Yii::$app->params['table_available'] ?? false) {
            $current_table = $current_table ?? $this->modelClass::tableName();
            $alias = $this->customAlias ?? $current_table;
            if (empty($this->select)) {
                $this->setFullSelect($joinType, $current_table, $alias);
            } else {
                $tableSchema = Yii::$app->db->getTableSchema($current_table);
                $stringColumns = array_filter($tableSchema->columns, fn($column) => in_array($column->type, ['string', 'text', 'safe']));
                foreach ($this->select as $attribute_name => $column) {
                    if (!str_contains($column, 'COUNT') && !empty($this->join)) {
                        $full = str_contains($column, '.*') ? $column : (str_contains($attribute_name, '.*') ? $attribute_name : null);
                        if (!empty($full) && $this->customAlias === explode('.', $full)[0]) {
                            $this->setFullSelect($joinType, $current_table, $alias);
                        } else {
                            $this->setSingleSelect($joinType, $this->join, $this->modelClass::tableName(), $attribute_name, $column);
                        }
                    } elseif (isset($stringColumns[$column])) {
                        $this->setSingleSelectNotAlias($joinType, $current_table, $alias, $column);
                    }
                }
            }
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

    protected function setSingleSelectNotAlias(string $joinType, string $current_table, string $alias, string $current_column): void
    {
        $collectColumns = [];
        $joinTable = $current_table . '_' . $this->langTable;
        $collectColumns[$current_column] = $this->coalesce($joinTable, $current_column, $alias . '.' . $current_column);
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
        $this->addSelect($collectColumns);
        $this->addJoin($joinType, $joinTable, $current_table, $alias);
    }

    protected function setFullSelect(string $joinType, string $current_table, string $alias): void
    {
        $collectColumns = [];
        $nonTranslatableColumns = [];
        $joinTable = $current_table . '_' . $this->langTable;
        $columns = Yii::$app->db->getTableSchema($current_table)->columns;
        foreach ($columns as $attribute_name => $column) {
            if (in_array($column->type, ['string', 'text', 'safe'])) {
                $collectColumns[$attribute_name] = $this->coalesce($joinTable, $attribute_name, $alias . '.' . $attribute_name);
            } else {
                $nonTranslatableColumns[] = "{$alias}.{$attribute_name}";
            }
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
        $this->addSelect(array_merge($nonTranslatableColumns, $collectColumns));
        $this->addJoin($joinType, $joinTable, $current_table, $alias);
    }

    protected function setSingleSelect(string $joinType, array $joins, string $rootTable, string $attribute_name, string $column): void
    {
        $explode = explode('.', $column);
        $collectColumns = [];
        foreach ($joins as $join) {
            if (isset($join[1][$explode[0]])) {
                $current_table = $join[1][$explode[0]];
                $joinTable = $current_table . '_' . $this->langTable;
                $collectColumns[$attribute_name] = $this->coalesce($joinTable, $explode[1], $column);
                $this->addSelect($collectColumns);
                $this->addJoin($joinType, $joinTable, $current_table, $explode[0]);
                break;
            }
        }
        $this->joinList = array_merge($this->joinList, $collectColumns);
        if (empty($collectColumns) && !in_array($attribute_name, array_keys($this->joinList)) && $explode[0] === $this->customAlias) {
            $joinTable = $rootTable . '_' . $this->langTable;
            $collectColumns[$attribute_name] = $this->coalesce($joinTable, $explode[1], $column);
            $this->addSelect($collectColumns);
            $this->addJoin($joinType, $joinTable, $rootTable, $explode[0]);
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
    }

    protected function coalesce(string $table, string $attribute, string $qualified_column_name): string
    {
        return "COALESCE(NULLIF(json_extract_path_text({$table}.value, '{$attribute}'), ''), {$qualified_column_name})";
    }

    protected function addJoin(string $joinType, string $joinTable, string $current_table, string $alias): void
    {
        $this->$joinType(
            [$joinTable => $this->langTable],
            "$joinTable.table_name = :table_name_$current_table AND $joinTable.table_iteration = {$alias}.id",
            [":table_name_$current_table" => $current_table]
        );
    }

    public function orderBy($columns)
    {
        if (is_array($columns) && isset($this->selectColumns)) {
            foreach ($columns as $column => $value) {
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

    /**
     * @throws Exception
     */
    public static function upsert(string $table, string $category, int $iteration, bool $isStatic, array $value): int
    {
        return Yii::$app->db->createCommand()
            ->upsert($table, [
                'table_name' => $category,
                'table_iteration' => $iteration,
                'is_static' => $isStatic,
                'value' => $value,
            ], [
                'value' => $value
            ])
            ->execute();
    }


    public static function modErrToStr($model): string
    {
        if (!$model instanceof Model) {
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

    /** so‘z oxiriga "s" qo‘shish */
    public static function toPlural($word): string
    {
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        } elseif (str_ends_with($word, 's') || str_ends_with($word, 'x') || str_ends_with($word, 'z') || str_ends_with($word, 'sh') || str_ends_with($word, 'ch')) {
            return $word . 'es';
        }
        return $word . 's';
    }
}