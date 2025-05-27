<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Query;
use Yunusbek\Multilingual\components\ExcelExportImport;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\traits\MultilingualTrait;


/**
 * @package Yunusbek\Multilingual\models
 *
 * @property-write array $dynamicLanguageValue
 */
class Multilingual extends ActiveRecord
{
    use MultilingualTrait;

    public static $tableName;

    public static function tableName()
    {
        if (!empty(static::$tableName)) {
            return static::$tableName;
        }

        return parent::tableName();
    }

    /**
     * @throws Exception
     */
    public function afterSave($insert, $changedAttributes)
    {
        $this->multilingualAfterSave();
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterDelete()
    {
        $this->multilingualAfterDelete();
    }


    /** Static ma'lumotlarni tarjima qilish
     * @param string $langTable
     * @param string $category
     * @param array $value
     * @return array
     * @throws Exception
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
     * @param array $params
     * @return bool|string
     * @throws Exception
     * @throws \Exception
     */
    public static function exportToExcelI18n(array $params): bool|string
    {
        $default_lang = MlConstant::LANG_PREFIX . key(Yii::$app->params['default_language']);
        $data = Yii::$app->db->createCommand("SELECT * FROM {$default_lang} WHERE is_static = true AND table_iteration = 0 ORDER BY table_name")->queryAll();

        if (empty($data)) {
            throw new InvalidConfigException(Yii::t('multilingual', "The table to which I18n should be written is currently empty. Please run the {command} command to fill it.", ['command' => '" php yii ml-extract/i18n "']));
        }

        return ExcelExportImport::exportToExcelData($data, "i18n.xlsx");
    }

    /** Asosiy tablitsalardan excelga export qilish
     * @param array $params
     * @return bool|string
     * @throws \Exception
     */
    public static function exportToExcelDefault(array $params): bool|string
    {
        $languages = Yii::$app->params['language_list'];
        $data = LanguageService::getLangTables($languages, $params, true)->getModels() ?? null;

        if (empty($data)) {
            throw new InvalidConfigException(Yii::t('multilingual', 'No information was found in the table'));
        }

        return ExcelExportImport::exportToExcelData($data, "db_lang.xlsx");
    }
}