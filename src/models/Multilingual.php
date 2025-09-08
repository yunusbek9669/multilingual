<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Exception;
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

    /** Tablitsadan excelga export qilish
     * @param array $params
     * @return bool|string
     * @throws Exception
     * @throws \Exception
     */
    public static function exportToExcelI18n(array $params): bool|string
    {
        $default_lang = MlConstant::LANG_PREFIX . key(Yii::$app->params['default_language']);
        $data = Yii::$app->db->createCommand("SELECT * FROM {$default_lang} WHERE is_static = true ORDER BY table_name")->queryAll();

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