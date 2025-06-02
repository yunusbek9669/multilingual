<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\DataReader;
use yii\data\Pagination;
use yii\data\SqlDataProvider;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\components\MlConstant;

trait SqlRequestTrait
{
    use SqlHelperTrait;
    use JsonTrait;

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
}