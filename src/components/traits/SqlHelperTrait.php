<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\ExpressionInterface;
use yii\db\Query;
use yii\db\Expression;
use yii\db\ActiveQuery;
use yii\helpers\Console;
use Yunusbek\Multilingual\components\MlConstant;

trait SqlHelperTrait
{
    use JsonTrait;

    private array $selectColumns = [];
    private array $joinList = [];
    private string|null $customAlias = null;
    private $mlGroupBy = [];

    private int $alias_i = 0;


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
     * @param string|null $current_table
     * @return SqlHelperTrait
     */
    private function joinWithLang(string $current_table = null): static
    {
        if (Yii::$app->params['table_available'] ?? false)
        {
            $langTable = $this->langTable;
            $current_table = $current_table ?? $this->current_table;
            $alias = $this->customAlias ?? $current_table;
            $ml_attributes = self::$jsonTables[$current_table] ?? [];
            if (!empty($ml_attributes) && empty($this->select))
            {
                $this->setFullSelect($current_table, $alias, $ml_attributes);
            } else {
                foreach ($this->select ?? [] as $attribute_name => $column)
                {
                    [$alias_attribute, $attribute, $qualified_column, $table_alias] = $this->normalizeAttribute($langTable, $current_table, $alias, $attribute_name, $column);
                    if (isset(self::$jsonTables[$current_table]))
                    {
                        //noodatiy yozilgan ustunlar uchun
                        if ($column instanceof Expression || $this->unusualSelect($column))
                        {
                            $this->setSingleSelectExpression($langTable, $current_table, $column, $attribute_name, $this->join);
                        }
                        //(toza, alias.column, [alias => column], [alias => alias.column]) tarzda yozilgan ustunlar uchun
                        elseif (in_array($attribute, $ml_attributes) && $table_alias === $alias)
                        {
                            $this->setCommonSelect($langTable, $current_table, $alias_attribute, $attribute, $qualified_column, $alias);
                        }
                        //join qilingan tablitsa uchun
                        elseif (!empty($this->join))
                        {
                            $full = str_contains($column, '.*') ? $column : (str_contains($attribute_name, '.*') ? $attribute_name : null);
                            if (!empty($full) && $this->customAlias === explode('.', $full)[0]) {
                                $this->setFullSelect($current_table, $alias, $ml_attributes);
                            } else {
                                $this->setSingleSelect($this->join, $langTable, $current_table, $alias_attribute, $attribute, $qualified_column, $table_alias);
                            }
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (Expression bo'lsa)
     * @param string $langTable
     * @param string $rootTable
     * @param string $column
     * @param string|int $alias_attribute
     * @param array|null $joins
     * @return void
     */
    private function setSingleSelectExpression(string $langTable, string $rootTable, string $column, string|int $alias_attribute, array $joins = null): void
    {
        if (is_array($joins)) {
            foreach ($joins as $join) {
                [$joinTable, $joinAlias] = $this->explodeJoin($join);
                if (isset(self::$jsonTables[$joinTable]) && $this->replaceMlAttributeWithCoalesce($column, $joinTable, $langTable, $joinAlias)) {
                    unset($this->select[$alias_attribute]);
                    $this->addSelect([$alias_attribute => $column]);
                    $this->joinList[$alias_attribute] = $column;
                    $this->selectColumns[$alias_attribute] = $column;
                    return;
                }
            }
        }

        if (!isset($this->joinList[$alias_attribute])) {
            if ($this->replaceMlAttributeWithCoalesce($column, $rootTable, $langTable, $this->customAlias)) {
                unset($this->select[$alias_attribute]);
                $this->addSelect([$alias_attribute => $column]);
                $this->selectColumns[$alias_attribute] = $column;
            }
        }
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (toza, alias.column, [alias => column], [alias => alias.column])
     * @param string $langTable
     * @param string $rootTable
     * @param string $current_table
     * @param string $alias_attribute
     * @param string $attribute
     * @param string $qualified_column
     * @param string $table_alias
     * @return void
     */
    private function setCommonSelect(string $langTable, string $current_table, string $alias_attribute, string $attribute, string $qualified_column, string $table_alias): void
    {
        $joinLangTable = "{$current_table}_{$langTable}_{$table_alias}";
        $this->getBaseColumnName($joinLangTable, $attribute);
        $expression = $this->coalesce($joinLangTable, $attribute, $qualified_column);
        $this->addSelect([$alias_attribute => $expression]);
        $this->addJoin('leftJoin', $langTable, $joinLangTable, $current_table, $table_alias);

        $this->selectColumns[$alias_attribute] = $expression;
        $this->joinList[$alias_attribute]      = $expression;
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (Barcha ustunlar select qilingan bo'lsa)
     * @param string $current_table
     * @param string $alias
     * @param array $ml_attributes
     * @return void
     */
    private function setFullSelect(string $current_table, string $alias, array $ml_attributes): void
    {
        $collectColumns = [];
        $nonTranslatableColumns = [];
        $joinLangTable = "{$current_table}_{$this->langTable}_{$alias}";
        $columns = Yii::$app->db->getTableSchema($current_table)->columns;
        foreach ($columns as $attribute_name => $column) {
            if (in_array($attribute_name, $ml_attributes)) {
                $this->getBaseColumnName($joinLangTable, $attribute_name);
                $collectColumns[$attribute_name] = $this->coalesce($joinLangTable, $attribute_name, $alias . '.' . $attribute_name);
            } else {
                $nonTranslatableColumns[] = "{$alias}.{$attribute_name}";
            }
        }
        $this->selectColumns = array_merge($this->selectColumns, $collectColumns);
        $this->addSelect(array_merge($nonTranslatableColumns, $collectColumns));
        $this->addJoin('leftJoin', $this->langTable, $joinLangTable, $current_table, $alias);
    }

    /**
     * Select qilingan ustunlar tarjima qilishga qo'shilgan bo'lsa shartni bajaradi (birnechta ustun tanlab select qilingan bo'lsa)
     * @param array $joins
     * @param string $langTable
     * @param string $rootTable
     * @param string $alias_attribute
     * @param string $attribute
     * @param string $qualified_column
     * @param string $table_alias
     * @return void
     */
    private function setSingleSelect(array $joins, string $langTable, string $current_table, string $alias_attribute, string $attribute, string $qualified_column, string $table_alias): void
    {
        foreach ($joins as $join) {
            [$joinTable, $joinAlias] = $this->explodeJoin($join);
            if ($this->canApplyLang($joinTable, $joinAlias, $attribute, $table_alias)) {
                $this->setCommonSelect($langTable, $joinTable, $alias_attribute, $attribute, $qualified_column, $joinAlias);
                return;
            }
        }

        if (isset($this->joinList[$alias_attribute]) && $this->canApplyLang($current_table, $this->customAlias, $attribute, $table_alias)) {
            $this->setCommonSelect($langTable, $current_table, $alias_attribute, $attribute, $qualified_column, $table_alias);
        }
    }

    private function canApplyLang(string $table, string $joinAlias, string $attribute, string $expectedAlias): bool
    {
        return $joinAlias === $expectedAlias && isset(self::$jsonTables[$table]) && in_array($attribute, self::$jsonTables[$table], true);
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
     * @param string $langTable
     * @param string $joinTable
     * @param string $current_table
     * @param string $alias
     * @return void
     */
    private function addJoin(string $joinType, string $langTable, string $joinTable, string $current_table, string $alias): void
    {
        $this->$joinType(
            [$joinTable => $langTable],
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

    function getBaseColumnName($alias, $attribute): void
    {
        if (preg_match('/([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\b(?!.*\.)/', $attribute, $matches)) {
            $this->mlGroupBy[] = new Expression("{$alias}.value->>'{$matches[2]}'");
        } else {
            $this->mlGroupBy[] = new Expression("{$alias}.value->>'{$attribute}'");
        }
    }

    /**
     * Sets the GROUP BY part of the query.
     * @param string|array|ExpressionInterface|null $columns the columns to be grouped by.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     *
     * Note that if your group-by is an expression containing commas, you should always use an array
     * to represent the group-by information. Otherwise, the method will not be able to correctly determine
     * the group-by columns.
     *
     * Since version 2.0.7, an [[ExpressionInterface]] object can be passed to specify the GROUP BY part explicitly in plain SQL.
     * Since version 2.0.14, an [[ExpressionInterface]] object can be passed as well.
     * @return $this the query object itself
     * @see addGroupBy()
     */
    public function groupBy($columns)
    {
        $columns = (array)$columns;
        if (empty($this->mlGroupBy)) {
            $this->joinWithLang($this->current_table ?? null);
        }
        foreach ($this->mlGroupBy as $col) {
            if (!in_array($col, $columns, true)) {
                $columns[] = $col;
            }
        }
        return parent::groupBy($columns);
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the `AND` operator.
     * @param string|array|ExpressionInterface $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andWhere($condition, $params = [])
    {
        parent::andWhere($condition, $params);
        return $this;
    }

    /**
     * Sets the LIMIT part of the query.
     * @param int|ExpressionInterface|null $limit the limit. Use null or negative value to disable limit.
     * @return $this the query object itself
     */
    public function limit($int)
    {
        return parent::limit($int);
    }

    /**
     * @param array $join
     * @return array
     */
    private function explodeJoin(array $join): array
    {
        $table = $alias = '';
        $this->explodeTableAlias($join[1], $table, $alias);
        return [$table, $alias];
    }

    /**
     * Join ichidan (TableName va Alias)ni ajratib beradi
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
    private function replaceMlAttributeWithCoalesce(&$sqlExpr, $tableName, $langTable, $alias): bool
    {
        $flag = false;
        $pattern = '/(?<![a-zA-Z0-9_])'
            . preg_quote($alias, '/')
            . '\.([a-zA-Z_][a-zA-Z0-9_]*)'
            . '(?:\{([a-zA-Z_]+)\})?'
            . '(?![a-zA-Z0-9_])/';

        $sqlExpr = preg_replace_callback(
            $pattern,
            function ($match) use ($tableName, $langTable, $alias, &$flag)
            {
                $full      = $match[0];   // org.name{ru} yoki org.name
                $attribute = $match[1];   // name

                //locale'ni ajratish
                $this->extractLocale($full, $langTable);

                $joinLangTable = "{$tableName}_{$langTable}_{$alias}";
                if (in_array($attribute, self::$jsonTables[$tableName]) && !str_contains($full, $joinLangTable)) {
                    $flag = true;
                    $this->addJoin('leftJoin', $langTable, $joinLangTable, $tableName, $alias);
                    return $this->coalesce($joinLangTable, $attribute, $full);
                }
                return $full;
            }, $sqlExpr);

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

    /**
     * table va attribute'larni ajratib olish
     * @param string $langTable
     * @param string $current_table
     * @param string $alias
     * @param string|int $attributeName
     * @param string|Expression $column
     * @return array
     */
    private function normalizeAttribute(string &$langTable, string &$current_table, string $alias, string|int $attributeName, string|Expression $column): array
    {
        //locale'ni ajratish
        $this->extractLocale($column, $langTable);

        $column = $this->unwrap($column);
        if (is_string($attributeName)) {
            $attributeName = $this->unwrap($attributeName);
        }

        $isStringKey = is_string($attributeName);
        $pattern = '/\b' . preg_quote($alias, '/') . '\.([a-zA-Z_][a-zA-Z0-9_]*)\b/';
        if (preg_match($pattern, $column, $matches)) {
            $tableAlias = $alias;
            $qualifiedColumn = $matches[0];
            $attribute = $matches[1];
        } else {
            if (preg_match('/\b([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\b/', $column, $matches)) {
                $tableAlias = $matches[1];
                $attribute = $matches[2];
                $qualifiedColumn = "$tableAlias.$attribute";
            } else {
                $tableAlias = $alias;
                $attribute = $column;
                $qualifiedColumn = $column;
            }
        }

        $key = $isStringKey
            ? (str_starts_with($attributeName, "$tableAlias.")
                ? substr($attributeName, strlen($tableAlias) + 1)
                : $attributeName)
            : $attribute;

        // join qilingan tablitsa bo‘lsa
        if (is_array($this->join)) {
            foreach ($this->join as $join) {
                [$joinTable, $joinAlias] = $this->explodeJoin($join);
                if ($joinAlias === $tableAlias) {
                    $current_table = $joinTable;
                }
            }
        }

        return [$key, $attribute, $qualifiedColumn, $tableAlias];
    }

    /** attributeni tozalab oolish */
    private function unwrap(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            return trim(substr($value, 1, -1));
        }
        return $value;
    }

    /** Noodatiy selectlarni aniqlash */
    private function unusualSelect(string $column): bool
    {
        $exprLower = strtolower($column);
        if (preg_match('/^(count|sum|avg|min|max)\s*\(/', $exprLower)) {
            return false;
        }
        return (
            strpos($exprLower, 'case') !== false ||
            strpos($exprLower, 'coalesce') !== false ||
            strpos($exprLower, 'concat') !== false ||
            strpos($exprLower, 'select') !== false || // subquery
            strpos($exprLower, '(') !== false ||      // function or expression
            preg_match('/[+\-*\/]/', $exprLower)      // matematik amallar
        );
    }

    /** Tillar uchun alohida select qilinsa locale'ni ushlab olish */
    function extractLocale(string &$value, string &$langTable): void
    {
        if (preg_match('/\{([^}]+)\}/', $value, $matches)) {
            $langTable = MlConstant::LANG_PREFIX.$matches[1];
            $value = str_replace("{{$matches[1]}}", '', $value);
        } else {
            $langTable = $this->langTable;
        }
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
                CASE WHEN NULLIF($table_name.$attribute, '') IS NOT NULL THEN {$value} END AS value
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
                $orParts[] = "NULLIF($table_name.$attribute, '') IS NOT NULL";
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

                    /** JSON ustunida mavjud bo'lmagan attributelarni qo‘shib berish */
                    foreach ($attributes as $attribute) {
                        if (!$export) {
                            self::isFull($result, $attribute, $lang_table, $table_name);
                        }
                        $exists .= new Expression(" OR (NULLIF($table_name.$attribute, '') IS NOT NULL AND COALESCE({$lang_table}.value->>'{$attribute}', '') = '')");
                    }


                    if (!$export) {
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
            $result['conditions'] = '('.implode(' OR ', $conditions).')';
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