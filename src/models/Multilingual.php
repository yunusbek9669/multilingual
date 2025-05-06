<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Query;
use Yunusbek\Multilingual\components\ExcelExportImport;
use Yunusbek\Multilingual\components\LanguageService;


/**
 * @package Yunusbek\Multilingual\models
 *
 * @property-write array $dynamicLanguageValue
 */
class Multilingual extends ActiveRecord
{
    public static $tableName;

    public static function tableName()
    {
        if (!empty(static::$tableName)) {
            return static::$tableName;
        }

        return parent::tableName();
    }


    /** Static ma'lumotlarni tarjima qilish
     * @param string $langTable
     * @param string $category
     * @param array $value
     * @return array
     */
    public static function setStaticLanguageValue(string $langTable, string $category, array $value): array
    {
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $table = (new Query())->select('value')->from($langTable)->where(['table_name' => $category, 'is_static' => true])->one();
        $allMessages = json_decode($table['value'], true);
        ksort($allMessages);
        ksort($value);
        $upsert = BaseLanguageQuery::upsert($langTable, $category, 0, true, array_replace($allMessages, $value));
        if ($upsert <= 0) {
            $response['message'] = Yii::t('multilingual', 'An error occurred while writing "{table}"', ['table' => $langTable]);
            $response['code'] = 'error';
            $response['status'] = false;
        } else {
            $data = Yii::$app->cache->get($langTable);
            $data[$category] = $value;
            Yii::$app->cache->set($langTable, $data);
        }

        return $response;
    }

    /** Tablitsadan excelga export qilish
     * @param string $tableName
     * @param bool $is_static
     * @return bool|string
     * @throws Exception
     * @throws \Exception
     */
    public static function exportToExcel(string $tableName, bool $is_static = true): bool|string
    {
        $data = Yii::$app->db->createCommand("SELECT * FROM {$tableName} WHERE is_static = :is_static" . ($is_static ? '' : ' AND table_iteration != 0'))
            ->bindValue(':is_static', $is_static, \PDO::PARAM_BOOL)->queryAll();

        if (empty($data)) {
            throw new \Exception(Yii::t('multilingual', 'No information was found in the table'));
        }

        return ExcelExportImport::exportToExcelData($data, "{$tableName}.xlsx");
    }

    /** Asosiy tablitsalardan excelga export qilish
     * @param array $params
     * @return bool|string
     * @throws \Exception
     */
    public static function exportToExcelDefault(array $params): bool|string
    {
        $languages = Yii::$app->params['language_list'];
        if (count($languages) === 1) {
            throw new \Exception(Yii::t('multilingual', 'No information was found in the table'));
        }
        $data = LanguageService::getDefaultTables($languages, $params);

        if (empty($data)) {
            throw new \Exception(Yii::t('multilingual', 'No information was found in the table'));
        }

        return ExcelExportImport::exportToExcelData($data, "default_lang.xlsx");
    }
}