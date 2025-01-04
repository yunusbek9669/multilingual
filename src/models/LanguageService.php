<?php

namespace Yunusbek\Multilingual\models;

use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Query;
use Yii;

class LanguageService extends ActiveQuery
{
    public static function checkTable(string $table = null): bool
    {
        return (new Query())
            ->from('information_schema.tables')
            ->where(['table_name' => $table])
            ->exists();
    }

    /**
     * Umumiy extend olgan modellarning ma’lumotlari
     * @throws Exception
     */
    public static function getModelsData(string $extendModel, array $params): array
    {
        $is_all = isset($params['is_all']) && $params['is_all'];

        /** Extend olgan (Asosiy) modellar */
        $modelsExtended = self::getChildModels($extendModel);
        $languages = Yii::$app->params['language_list'];
        $default_language = current(array_filter($languages, fn($lang) => empty($lang['table'])));
        if (empty($modelsExtended) || empty($languages)) {
            return [];
        }

        $langTables = [];
        $result = [
            'header' => [
                'table_name' => Yii::t('app', 'Table Name'),
                'attributes' => Yii::t('app', 'Attributes'),
                'table_iteration' => Yii::t('app', 'Table Iteration'),
                'language' => []
            ]
        ];

        if ($is_all)
        {
            /** Dinamik tillar tablitsalarini ro‘yxatini shakllantirish */
            $tableResult = self::getLangTables($languages);
            $langTables = $tableResult['langTables'];
            $result['header']['language'] = array_merge($result['header']['language'], $tableResult['language']);
        }

        $body = [];
        /** Asosiy modellar sikli */
        foreach ($modelsExtended as $model)
        {
            /** Asosiy modellarning kerakli attributelari yig‘ilgan holdagi ro‘yxati */
            $modelData = self::getTranslateAbleData($model);

            if (!empty($modelData))
            {
                $tableName = $model::tableName();

                /** Asosiy modellarning ma‘lumotlar qatorlari bo‘yicha siklga solish */
                foreach ($modelData as $modelRow)
                {
                    $formattedTranslate = [];
                    /** @example $formattedTranslate = [
                     *      'attribute1' => [
                     *          'language1' => 'value1',...
                     *      ],...
                     * ]
                     */


                    $is_full = true;
                    /** Ro‘yxatni shakllantirish */
                    $id = $modelRow['id'];
                    $unique_name = $tableName.'_'.$id;
                    $body[$unique_name] = [
                        'table_name' => $tableName,
                        'table_iteration' => $id,
                    ];

                    unset($modelRow['id']);
                    /** Asosiy modellarning qatorlar attributelari bo‘yicha siklga solish */
                    foreach ($modelRow as $modelAttribute => $modelValue)
                    {
                        /** Asosiy modeldan olingan qiymatni qo‘shish */
                        if (empty($modelValue)) {
                            $result['header']['language'][$default_language['name']] += 1;
                        }
                        $formattedTranslate[$modelAttribute][$default_language['name']] = $modelValue;

                        if ($is_all)
                        {
                            /** Tizimdagi tillar bo‘yicha siklga solish */
                            foreach ($languages as $language)
                            {
                                if (!empty($language['table']))
                                {
                                    $lang_name = $language['name'];
                                    $value_found = false;

                                    if (!empty($langTables))
                                    {
                                        foreach ($langTables[$lang_name] ?? [] as $langRow)
                                        {
                                            if ($langRow['table_name'] === $tableName && $langRow['table_iteration'] == $id)
                                            {
                                                $decoded_values = json_decode($langRow['value'], true);
                                                if (!empty($decoded_values[$modelAttribute])) {
                                                    $formattedTranslate[$modelAttribute][$lang_name] = $decoded_values[$modelAttribute];
                                                    $value_found = true;
                                                }
                                            }
                                        }

                                        if (!$value_found) {
                                            $result['header']['language'][$lang_name] += 1;
                                            $is_full = false;
                                            $formattedTranslate[$modelAttribute][$lang_name] = null;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $body[$unique_name]['is_full'] = $is_full;
                    $body[$unique_name]['translate'] = $formattedTranslate;
                }
            }
        }
        $result['body'] = $body;

        return $result;
    }

    /**
     * Umumiy extend olgan modellar ro‘yxati
     */
    public static function getChildModels(string $extendModel): array
    {
        $models = [];
        $modules = Yii::$app->getModules();

        foreach ($modules as $moduleName => $moduleConfig)
        {
            $modulePath = Yii::getAlias('@app/modules/' . $moduleName);

            $modelsPath = $modulePath . '/models';
            if (is_dir($modelsPath))
            {
                foreach (scandir($modelsPath) as $file)
                {
                    if (strpos($file, '.php') !== false)
                    {
                        $modelName = pathinfo($file, PATHINFO_FILENAME);
                        $className = 'app\\modules\\' . $moduleName . '\\models\\' . $modelName;

                        if (class_exists($className) && is_subclass_of($className, $extendModel)) {
                            $models[] = $className;
                        }
                    }
                }
            }
        }

        return $models;
    }


    /** lang_* tablitsalarini chaqirib olish (Create, Update) */
    public static function setCustomAttributes($model, string $attribute = null): array
    {
        $attributes = [];
        $languages = Yii::$app->params['language_list'];
        if (!empty($languages))
        {
            foreach ($languages as $language)
            {
                if (!empty($language['table']) && self::checkTable($language['table']))
                {
                    $lang_table = (new yii\db\Query())
                        ->from($language['table'])
                        ->select('value')
                        ->where([
                            'table_name' => $model::tableName(),
                            'table_iteration' => $model->id
                        ])
                        ->scalar();
                    $data_value = json_decode($lang_table);
                    $name = $language['table'];
                    if ($attribute !== null) {
                        $name = 'Language['.$name.']['.$attribute.']';
                    }
                    $attributes[$name] = !empty($data_value->$attribute) ? $data_value->$attribute : null;
                }
            }
        }
        return $attributes;
    }

    /** Tarjima qilinadigan modellarning kerakli attributelari yig‘ilgan holdagi ro‘yxatini olish */
    public static function getTranslateAbleData($model)
    {
        /** kerakli ustunlarni yig‘ish */
        $attributes = [];
        $current_table = $model::tableName();
        $columns = Yii::$app->db->getTableSchema($current_table)->columns;
        foreach ($columns as $columnName => $column) {
            if ($columnName === 'id' || in_array($column->type, ['string', 'text', 'safe'])) {
                $attributes[] = $columnName;
            }
        }

        /** Asosiy modellarning kerakli attributelarini olish */
        return $model::find()
            ->select($attributes)
            ->where(['status' => 1])
            ->asArray()
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar */
    public static function getLangTables(array $languages): array
    {
        $result = [];
        /** Tizimdagi tillar bo‘yicha siklga solish */
        foreach ($languages as $language)
        {
            $result['language'][$language['name']] = 0;
            if (!empty($language['table']))
            {

                /** Dinamik tillar tablitsalarini ro‘yxatini shakllantirish */
                if (self::checkTable($language['table']))
                {
                    $result['langTables'][$language['name']] = (new Query())
                        ->from($language['table'])
                        ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                        ->all();
                }
            }
        }
        return $result;
    }


    /** Ma‘lumotlarni excelga export qilish */
    public static function exportToExcelData($data, $fileName): bool|string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /** Asosiy ustunlar */
        $letterList = ['A','B'];
        $baseHeaders = ['table_name', 'table_iteration'];
        $jsonKeys = [];

        /** Barcha JSON indekslarini aniqlash */
        foreach ($data as $row) {
            $jsonData = json_decode($row['value'], true);
            if (is_array($jsonData)) {
                $jsonKeys = array_unique(array_merge($jsonKeys, array_keys($jsonData)));
            }
        }

        /** Barcha sarlavhalar: asosiy ustunlar + JSON indekslari */
        $headers = array_merge($baseHeaders, $jsonKeys);
        $sheet->fromArray($headers, NULL, 'A1');
        $headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle("A1:B1")->getFont()->setBold(true)->setColor(new Color('777777'));
        $sheet->getStyle($headerRange)->getFont()->setBold(true);

        /** Ma'lumotlarni qo'shish */
        $rowNumber = 2;
        foreach ($data as $row) {
            $sheet->setCellValue("A{$rowNumber}", $row['table_name']);
            $sheet->setCellValue("B{$rowNumber}", $row['table_iteration']);

            $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

            /** JSON qiymatlarini mos ustunlarga qo'shish */
            $jsonData = json_decode($row['value'], true);
            $colIndex = 2;
            foreach ($jsonKeys as $key) {
                $colIndex++;
                $colLetter = Coordinate::stringFromColumnIndex($colIndex); // A, B, C...
                $letterList = array_merge($letterList, [$colLetter]);
                $sheet->setCellValue("{$colLetter}{$rowNumber}", $jsonData[$key] ?? '');
            }
            $rowNumber++;
        }

        /** Ustunlarning kengligini avtomatik sozlash */
        foreach (array_unique($letterList) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        /** Asosiy ustunlarni (table_name, table_iteration, va JSON kalitlari) himoyalash */
        $sheet->getStyle('A1:B' . $rowNumber)->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('C1:' . $sheet->getHighestColumn() . '1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);

        /** JSON qiymatlarini o'zgartirishga ruxsat berish */
        $sheet->getStyle('C2:' . $sheet->getHighestColumn() . $rowNumber)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        /** Himoyani yoqish */
        $spreadsheet->getActiveSheet()->getProtection()->setSheet(true);

        /** Faylni saqlash */
        $writer = new Xlsx($spreadsheet);if (!is_dir('uploads/languages')) { mkdir('uploads/languages'); }
        $filePath = Yii::getAlias("@webroot/uploads/languages/{$fileName}");
        $fileUrl = Yii::getAlias("@web/uploads/languages/{$fileName}");
        $writer->save($filePath);

        $writer->save($filePath);

        return json_encode(['success' => true, 'fileUrl' => $fileUrl]);
    }
}