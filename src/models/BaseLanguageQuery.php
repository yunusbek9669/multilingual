<?php

namespace Yunusbek\Multilingual\CommonLanguages\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;

/**
 *
 * @property-write mixed $customAttributes
 */
class BaseLanguageQuery extends ActiveQuery
{
    public $selectColumns = [];
    /**
     * @throws Exception
     */
    public function joinWithLang(): static
    {
        $tableName = 'lang_' . Yii::$app->language;

        $tableExists = Yii::$app->db->createCommand("SELECT to_regclass(:table) IS NOT NULL")
            ->bindValue(':table', $tableName)
            ->queryScalar();

        if ($tableExists) {
            $current_table = $this->modelClass::tableName();
            $this->alias($current_table);

            $columns = Yii::$app->db->getTableSchema($current_table)->columns;

            $selectColumns = [];
            foreach ($columns as $columnName => $column)
            {
                if (in_array($column->type, ['string', 'text', 'safe']))
                {
                    $coalesce = "COALESCE(
                        NULLIF(json_extract_path_text($tableName.value, '$columnName'), ''),
                        {$current_table}.{$columnName}
                    )";
                    $this->selectColumns[$columnName] = $coalesce;
                    $selectColumns[] = $coalesce." AS {$columnName}";
                }
            }
            $this->addSelect(array_merge(["{$current_table}.*"], $selectColumns));

            $this->leftJoin(
                $tableName,
                "$tableName.table_name = :table_name AND $tableName.table_iteration = $current_table.id",
                [':table_name' => $current_table]
            );
        }

        return $this;
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
        return LanguageService::getModelsData(LanguageModel::class, $params);
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
                    value JSON NOT NULL,
                    PRIMARY KEY (table_name, table_iteration)
                );
            ")->execute();

            $db->createCommand("
                CREATE INDEX idx_{$tableName}_table_name_iteration 
                ON {$tableName} (table_name, table_iteration);
            ")->execute();
        } catch (\Throwable $e) {
            $response['code'] = 'error';
            $response['status'] = false;
            $response['message'] = "Jadval yaratishda xato: " . $e->getMessage();
        }

        return $response;
    }

    /**
     * yangi lang_* table yaratish
     */
    public static function updateLangTable(string $oldTableName, string $tableName): array
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        try {
            $db->createCommand("ALTER TABLE {$oldTableName} RENAME TO {$tableName}")->execute();
            $db->createCommand("ALTER INDEX idx_{$oldTableName}_table_name_iteration RENAME TO idx_{$tableName}_table_name_iteration")->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $response['message'] = "Jadval yangilashda xato: " . $e->getMessage();
            $response['status'] = false;
            $response['code'] = 'error';
        }
        return $response;
    }
}