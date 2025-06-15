<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\Query;
use yii\db\Expression;
use yii\db\ActiveQuery;

trait SqlHelperTrait
{
    use JsonTrait;

    private array $selectColumns = [];
    private array $joinList = [];
    private string|null $customAlias = null;


    /** ========= Auto Join helper::begin ========= */
    public function prepare($builder): Query|ActiveQuery|self
    {
        $this->joinWithLang();
        if ($this->customAlias) {
            $this->from = [$this->customAlias => $this->current_table];
        }
        return parent::prepare($builder);
    }

    /** Select turlariga qarab tarjimaga moslab chiqish
     * @param string $joinType
     * @param string|null $current_table
     * @return SqlHelperTrait
     */
    private function joinWithLang(string $joinType = 'leftJoin', string $current_table = null): static
    {
        if (Yii::$app->params['table_available'] ?? false) {
            $current_table = $current_table ?? $this->current_table;
            $alias = $this->customAlias ?? $current_table;
            $ml_attributes = $this->jsonTables[$current_table] ?? [];
            if (empty($this->select)) {
                $this->setFullSelect($joinType, $current_table, $alias, $ml_attributes);
            } else {
                foreach ($this->select as $attribute_name => $column) {
                    if ($column instanceof Expression || $this->unusualSelect($column)) {
                        $this->setSingleSelectExpression($joinType, $current_table, $column, $attribute_name, $this->join);
                    } elseif ($this->clearColumn($attribute_name, $column) && in_array($column, $ml_attributes)) {
                        $this->setSingleSelectNotAlias($joinType, $current_table, $alias, $column);
                    } elseif (!empty($this->join)) {
                        $full = str_contains($column, '.*') ? $column : (str_contains($attribute_name, '.*') ? $attribute_name : null);
                        if (!empty($full) && $this->customAlias === explode('.', $full)[0]) {
                            $this->setFullSelect($joinType, $current_table, $alias, $ml_attributes);
                        } else {
                            $this->setSingleSelect($joinType, $this->join, $current_table, $attribute_name, $column);
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (Expression bo'lsa)
     * @param string $joinType
     * @param string $rootTable
     * @param string $column
     * @param string|int|null $attribute_name
     * @param array|null $joins
     * @return void
     */
    private function setSingleSelectExpression(string $joinType, string $rootTable, string $column, string|int $attribute_name = null, array $joins = null): void
    {
        $collectColumns = [];
        if (is_array($joins)) {
            $joinTable = '';
            $joinAlias = '';
            foreach ($joins as $join) {
                $this->explodeTableAlias($join[1], $joinTable, $joinAlias);
                $joinLangTable = $joinTable . '_' . $this->langTable;
                if ($this->replaceMlAttributeWithCoalesce($column, $joinAlias, $joinTable, $joinLangTable)) {
                    $this->normalizeJoin($join[0]);
                    $collectColumns[$attribute_name] = $column;
                    $this->addSelect($collectColumns);
                    $this->addJoin($join[0], $joinLangTable, $joinTable, $joinAlias);
                    break;
                }
            }
            $this->joinList = array_merge($this->joinList, $collectColumns);
        }

        if (empty($collectColumns) && isset($this->jsonTables[$rootTable])) {
            $joinLangTable = $rootTable . '_' . $this->langTable;
            if ($this->replaceMlAttributeWithCoalesce($column, $this->customAlias, $rootTable, $joinLangTable)) {
                $collectColumns[$attribute_name] = $column;
                $this->addSelect($collectColumns);
                $this->addJoin($joinType, $joinLangTable, $rootTable, $this->customAlias);
            }
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (birnechta ustun tanlab select qilingan bo'lsa (alias qo'yilmagan toza ustun bo'lsa))
     * @param string $joinType
     * @param string $current_table
     * @param string $alias
     * @param string $current_column
     */
    private function setSingleSelectNotAlias(string $joinType, string $current_table, string $alias, string $current_column): void
    {
        $collectColumns = [];
        $joinLangTable = $current_table . '_' . $this->langTable;
        $collectColumns[$current_column] = $this->coalesce($joinLangTable, $current_column, $alias . '.' . $current_column);
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
        $this->addSelect($collectColumns);
        $this->addJoin($joinType, $joinLangTable, $current_table, $alias);
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (Barcha ustunlar select qilingan bo'lsa)
     * @param string $joinType
     * @param string $current_table
     * @param string $alias
     * @param array $ml_attributes
     * @return void
     */
    private function setFullSelect(string $joinType, string $current_table, string $alias, array $ml_attributes): void
    {
        $collectColumns = [];
        $nonTranslatableColumns = [];
        $joinLangTable = $current_table . '_' . $this->langTable;
        $columns = Yii::$app->db->getTableSchema($current_table)->columns;
        foreach ($columns as $attribute_name => $column) {
            if (in_array($attribute_name, $ml_attributes)) {
                $collectColumns[$attribute_name] = $this->coalesce($joinLangTable, $attribute_name, $alias . '.' . $attribute_name);
            } else {
                $nonTranslatableColumns[] = "{$alias}.{$attribute_name}";
            }
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
        $this->addSelect(array_merge($nonTranslatableColumns, $collectColumns));
        $this->addJoin($joinType, $joinLangTable, $current_table, $alias);
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (birnechta ustun tanlab select qilingan bo'lsa)
     * @param string $joinType
     * @param array $joins
     * @param string $rootTable
     * @param string $attribute_name
     * @param string $column
     * @return void
     */
    private function setSingleSelect(string $joinType, array $joins, string $rootTable, string $attribute_name, string $column): void
    {
        $explode = explode('.', $column);
        $collectColumns = [];
        $joinTable = '';
        $joinAlias = '';
        foreach ($joins as $join) {
            $this->explodeTableAlias($join[1], $joinTable, $joinAlias);
            if (isset($this->jsonTables[$joinTable]) && in_array($explode[1] ?? $column, $this->jsonTables[$joinTable]) && $explode[0] === $joinAlias) {
                $joinLangTable = $joinTable . '_' . $this->langTable;
                $this->normalizeJoin($join[0]);
                $collectColumns[$attribute_name] = $this->coalesce($joinLangTable, $explode[1], $column);
                $this->addSelect($collectColumns);
                $this->addJoin($join[0], $joinLangTable, $joinTable, $explode[0]);
                break;
            }
        }
        $this->joinList = array_merge($this->joinList, $collectColumns);
        if (empty($collectColumns) && isset($this->jsonTables[$rootTable]) && in_array($explode[1] ?? $column, $this->jsonTables[$rootTable]) && isset($this->joinList[$attribute_name]) && $explode[0] === $this->customAlias) {
            $joinLangTable = $rootTable . '_' . $this->langTable;
            $collectColumns[$attribute_name] = $this->coalesce($joinLangTable, $explode[1], $column);
            $this->addSelect($collectColumns);
            $this->addJoin($joinType, $joinLangTable, $rootTable, $explode[0]);
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
    }

    /**
     * Agar json ustuni ichida mos qiymat bo'lsa shuni aks holda asl qiymatni qaytaradi
     * @param string $table
     * @param string $attribute
     * @param string $qualified_column_name
     * @return string
     */
    private function coalesce(string $table, string $attribute, string $qualified_column_name): string
    {
        return "COALESCE(NULLIF({$table}.value->>'{$attribute}', ''), {$qualified_column_name})";
//        return "COALESCE(NULLIF(json_extract_path_text({$table}.value, '{$attribute}'), ''), {$qualified_column_name})"; //muqobil usul
    }

    /**
     * (Join)larni generatsiya qilib beradi
     * @param string $joinType
     * @param string $joinTable
     * @param string $current_table
     * @param string $alias
     * @return void
     */
    private function addJoin(string $joinType, string $joinTable, string $current_table, string $alias): void
    {
        $this->$joinType(
            [$joinTable => $this->langTable],
            "$joinTable.is_static = false AND $joinTable.table_name = :table_name_$current_table AND $joinTable.table_iteration = {$alias}.id",
            [":table_name_$current_table" => $current_table]
        );
        $this->join = array_map('unserialize', array_unique(array_map('serialize', $this->join)));
    }

    /**
     * (ORDER BY)ni tarjima qilingan qiymatlar bo'yicha sozlab beradi
     * @param $columns
     * @return SqlHelperTrait
     */
    public function orderBy($columns)
    {
        if (is_array($columns) && isset($this->selectColumns)) {
            foreach ($columns as $column => $value) {
                if (isset($this->selectColumns[$column])) {
                    $columns[$this->selectColumns[$column]] = $value;
                    unset($columns[$column]);
                }
            }
        }

        return parent::orderBy($columns);
    }

    /**
     * (TableName va Alias)ni ajratib beradi
     * @param array|string $tables
     * @param string|null $table_name
     * @param string|null $alias
     * @return void
     */
    private function explodeTableAlias(array|string $tables, string|null &$table_name, string|null &$alias): void
    {
        if (is_array($tables)) {
            foreach ($tables as $alias => $table) {
                if (is_string($alias)) {
                    $table_name = $table;
                } else {
                    $this->separateAlias($table, $table_name, $alias);
                }
            }
        } else {
            $this->separateAlias($tables, $table_name, $alias);
        }
    }

    /**
     * @param string $table
     * @param string|null $table_name
     * @param string|null $alias
     * @return void
     */
    private function separateAlias(string $table, string|null &$table_name, string|null &$alias): void
    {
        if (preg_match('/^([\w.]+)\s+(?:as\s+)?(\w+)$/i', $table, $matches)) {
            $table_name = $matches[1];
            $alias = $matches[2];
        } else {
            $table_name = $table;
            $alias = $table;
        }
    }

    /** Tarjima qilinadigan ustun bor bo'lsa uni COALESCE ga o'rash */
    private function replaceMlAttributeWithCoalesce(&$sqlExpr, $alias, $tableName, $joinLangTable): bool
    {
        $flag = false;
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\b/';
        preg_match_all($pattern, $sqlExpr, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            [$full, $matchAlias, $attribute] = $match;
            if (isset($this->jsonTables[$tableName]) &&
                in_array($attribute, $this->jsonTables[$tableName]) &&
                preg_match('/(?:' . preg_quote($alias . '.', '/') . ')?' . preg_quote($attribute, '/') . '\b/', $sqlExpr) &&
                $matchAlias === $alias
            ) {
                $sqlExpr = str_replace($full, $this->coalesce($joinLangTable, $attribute, $full), $sqlExpr);
                $flag = true;
            }
        }
        return $flag;
    }

    /** JOIN larni moslab olish */
    private function normalizeJoin(string &$sql): void
    {
        $replacements = [
            '/\bLEFT\s+JOIN\b/i'   => 'leftJoin',
            '/\bRIGHT\s+JOIN\b/i'  => 'rightJoin',
            '/\bINNER\s+JOIN\b/i'  => 'innerJoin',
            '/\bFULL\s+JOIN\b/i'   => 'fullJoin',
            '/\bCROSS\s+JOIN\b/i'  => 'crossJoin',
            '/\bJOIN\b/i'          => 'join', // faqat 'JOIN' bo‘lsa
        ];

        foreach ($replacements as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }
    }

    /** Noodatiy selectlarni aniqlash */
    private function unusualSelect(string $column): bool
    {
        $exprLower = strtolower($column);
        return (
            strpos($exprLower, 'case') !== false ||
            strpos($exprLower, 'coalesce') !== false ||
            strpos($exprLower, 'concat') !== false ||
            strpos($exprLower, 'select') !== false || // subquery
            strpos($exprLower, '(') !== false ||      // function or expression
            preg_match('/[+\-*\/]/', $exprLower)      // matematik amallar
        );
    }

    private function clearColumn($key, $value): bool
    {
        $expr = is_string($key) ? $value : $key;
        if (!is_string($expr)) return true;
        return !preg_match('/\b[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*\b/', $expr);
    }
    /** ========= Auto Join helper::end ========= */


    /** tarjima qilinadigan ustunlarni bitta jsonga saralab olish */
    private static function jsonBuilder(string $table_name, string $lang_name, array $attributes, string $lang_table = null): string
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
            )
        ");
    }

    /** tarjima qilinadigan jadvallar attributelari uchun */
    private static function setExists(string $lang_table): string
    {
        return new Expression("EXISTS (SELECT 1 FROM json_each_text({$lang_table}.value) kv WHERE COALESCE(kv.value, '') = '')");
    }

    /** tarjima qilinadigan jadvallar attributelari uchun */
    private static function isFull(array &$result, string $attribute, string $lang_table, string $table_name): void
    {
        $result['is_full'] .= new Expression("
            WHEN COALESCE({$table_name}.{$attribute}, '') <> '' AND (NOT jsonb_path_exists({$lang_table}.value::jsonb, '$.{$attribute}') OR COALESCE({$lang_table}.value::jsonb ->> '{$attribute}', '') = '') THEN FALSE 
            WHEN {$lang_table}.value IS NULL THEN FALSE 
        ");
    }

    /** Conditions */
    private static function whereBuilder(array $jsonData, string $table_name, array $attributes, array $sqlHelper): string
    {
        $conditions = [];

        if (!empty($jsonData['where']) && is_array($jsonData['where'])) {
            foreach ($jsonData['where'] as $key => $value) {
                $val = is_numeric($value) ? $value : "'" . addslashes($value) . "'";
                $conditions[] = "$table_name.$key = $val";
            }
        }

        if (!empty($attributes)) {
            $orParts = [];
            foreach ($attributes as $attribute) {
                $orParts[] = "($table_name.$attribute IS NOT NULL AND $table_name.$attribute <> '')";
            }
            if (!empty($orParts)) {
                $conditions[] = '(' . implode(' OR ', $orParts) . ')';
            }
        }

        if (!empty($sqlHelper['conditions'])) {
            $extraConditions = is_array($sqlHelper['conditions']) ? $sqlHelper['conditions'] : [$sqlHelper['conditions']];
            $conditions = array_merge($conditions, $extraConditions);
        }

        return implode(' AND ', $conditions);
    }

    /** lang_* jadvallari bo‘yicha sql sozlamalar yasash uchun */
    private static function sqlHelper(array $languages, array $attributes, string $table_name, int $isStatic, int $isAll, bool $export): array
    {
        $result = [
            'joins' => [],
            'json_builder' => [],
            'is_full' => $export ? var_export((bool)$isStatic,true)." as is_static" : var_export((bool)$isStatic,true)." as is_full",
        ];

        if (count($languages) > 1) {
            $is_full = [];
            $conditions = [];
            foreach ($languages as $language) {
                if (isset($language['table'])) {
                    $name = $language['name'];
                    $lang_table = $language['table'];
                    $exists = self::setExists($lang_table);

                    /** JOIN yasab berish uchun */
                    $result['joins'][$lang_table] = new Expression("LEFT JOIN $lang_table AS $lang_table ON $table_name.id = $lang_table.table_iteration AND '$table_name' = $lang_table.table_name");

                    if (!$export) {
                        /** JSON ustunida mavjud bo'lmagan attributelarni qo‘shib berish */
                        foreach ($attributes as $attribute) {
                            self::isFull($result, $attribute, $lang_table, $table_name);
//                            $exists .= new Expression(" OR ($table_name.$attribute IS NOT NULL AND $table_name.$attribute <> '' AND COALESCE(json_extract_path_text({$lang_table}.value, '{$attribute}'), '') = '')");
                            $exists .= new Expression(" OR ($table_name.$attribute IS NOT NULL AND $table_name.$attribute <> '' AND COALESCE({$lang_table}.value->>'{$attribute}', '') = '')");
                        }

                        /** value ustunida tarjimalarni json holatda yasash */
                        $result['json_builder'][$name] = self::jsonBuilder($table_name, $name, $attributes, $lang_table);

                        /** is_full:BOOLEAN to‘liq tarjima qilinganligini tekshirish */
                        $is_full[] = new Expression("WHEN {$lang_table}.value::jsonb = '{}' THEN FALSE ");
                    }

                    /** Qo‘shimcha shartlar */
                    $conditions[$lang_table] = "(";
                    $conditions[$lang_table] .= new Expression("($lang_table.is_static IS NULL OR $lang_table.is_static::int = $isStatic)");
                    /** Faqat bo‘sh qiymatlilarni yig‘ish */
                    if ($isAll === 0) {
                        $conditions[$lang_table] .= new Expression(" AND ($lang_table.is_static IS NULL OR {$exists})");
                    }
                    $conditions[$lang_table] .= ')';
                }
            }

            if (!$export) {
                $is_full = implode(' ', $is_full);
                $result['is_full'] = new Expression("CASE {$is_full} ELSE TRUE END AS is_full");
            }
            $result['conditions'] = implode(' OR ', $conditions);
        }
        $result['json_builder'] = implode(", ", $result['json_builder']);
        $result['joins'] = implode(" ", $result['joins']);
        return $result;
    }

    /** ON CONFLICT DO UPDATE bilan batch (bulk) UPSERT qilish */
    private static function batchBulk(array $columns, array $rowsToInsert, string $table): array
    {
        $params = [];
        $columns = implode(',', $columns);
        $valuesSql = [];
        foreach ($rowsToInsert as $row) {
            $valuesSql[] = '(' . implode(',', array_fill(0, count($row), '?')) . ')';
            array_push($params, ...$row);
        }
        $params = array_values($params);
        array_unshift($params, null);
        unset($params[0]);

        $onConflictSql = "ON CONFLICT (table_name, table_iteration, is_static) DO UPDATE SET value = EXCLUDED.value";
        $sql = "INSERT INTO {$table} ({$columns})
        VALUES " . implode(',', $valuesSql) . " $onConflictSql";

        return ['sql' => $sql, 'params' => $params];
    }
}