<?php

namespace Yunusbek\Multilingual\components\traits;

use yii\db\Expression;

trait SqlHelperTrait
{
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
                        }

                        /** value ustunida tarjimalarni json holatda yasash */
                        $result['json_builder'][$name] = self::jsonBuilder($table_name, $name, $attributes, $lang_table);

                        /** is_full:BOOLEAN to‘liq tarjima qilinganligini tekshirish */
                        $is_full[] = new Expression("WHEN {$lang_table}.value::jsonb = '{}' THEN FALSE ");
                    }

                    /** Qo‘shimcha shartlar */
                    $conditions[$lang_table] = new Expression("($lang_table.is_static IS NULL OR $lang_table.is_static::int = $isStatic)");

                    /** Faqat bo‘sh qiymatlilarni yig‘ish */
                    if ($isAll === 0) {
                        $conditions[$lang_table] .= new Expression(" AND ($lang_table.is_static IS NULL OR {$exists})");
                    }
                }
            }

            if (!$export) {
                $is_full = implode(' ', $is_full);
                $result['is_full'] = new Expression("CASE {$is_full} ELSE TRUE END AS is_full");
            }
            $result['conditions'] = implode(' ', $conditions);
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