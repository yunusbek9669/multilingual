<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Query;

class LanguageService
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
    public static function getModelsData(array $params): array
    {
        $languages = Yii::$app->params['language_list'];
        if (count($languages) === 1) {
            return [];
        }

        $tableResult = self::getLangTables($languages);
        $translate_list = array_fill_keys(array_keys($tableResult['language']), null);

        /** Asosiy tilni ro‘yxatga qo‘shish */
        $default_lang = [];
        foreach ($languages as $language) {
            if (!isset($language['table'])) {
                $default_lang_name = $language['name'].' <i class="fas fa-star text-warning"></i>';
                foreach ($tableResult['table_iterations'] as $table_name => $table_iterations) {
                    $table_id_list = array_keys($table_iterations);
                    if (self::checkTable($table_name)) {
                        $flat = call_user_func_array('array_merge', $table_iterations);
                        $result = array_merge(array_keys(array_flip($flat)), ['id']);
                        $modelResult = (new Query())->select($result)->from($table_name)->where(['in', 'id', $table_id_list])->all();
                        if (!empty($modelResult)) {
                            foreach ($modelResult as $model) {
                                $id = $model['id'];
                                unset($model['id']);
                                $default_lang[$default_lang_name][] = [
                                    'table_name' => $table_name,
                                    'table_iteration' => $id,
                                    'value' => json_encode($model),
                                ];
                            }
                        }
                    }
                }
            }
        }
        $tableResult['langTables'] = array_merge($default_lang, $tableResult['langTables']);

        $result = [
            'header' => [
                'table_name' => Yii::t('multilingual', 'Table Name'),
                'attributes' => Yii::t('multilingual', 'Attributes'),
                'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
                'language' => $tableResult['language']
            ]
        ];

        $body = [];
        /** body shakllantirish */
        foreach ($tableResult['langTables'] as $key => $table) {
            if (!empty($table)) {
                /** lang_* jadvallarining qatorlari bo‘yicha siklga solish */
                foreach ($table as $tableRow) {
                    $is_full = true;
                    /** Ro‘yxatni shakllantirish */
                    $tableValue = json_decode($tableRow['value'], true);
                    $unique_name = $tableRow['table_name'] . '_' . $tableRow['table_iteration'];
                    unset($tableRow['is_static']);
                    unset($tableRow['value']);
                    $body[$unique_name]['table_name'] = $tableRow['table_name'];
                    $body[$unique_name]['table_iteration'] = $tableRow['table_iteration'];

                    /** lang_* jadvallarining value:json ustuni bo‘yicha siklga solish */
                    foreach ($tableValue as $attribute => $value) {
                        if (empty($body[$unique_name]['translate'][$attribute])) {
                            $body[$unique_name]['translate'][$attribute] = $translate_list;
                        }
                        /** Asosiy modeldan olingan qiymatni qo‘shish */
                        $body[$unique_name]['translate'][$attribute][$key] = $value;
                        if (empty($value)) {
                            $result['header']['language'][$key] += 1;
                            $is_full = false;
                        }

                    }
                    $body[$unique_name]['is_full'] = $is_full;
                }
            }
        }
        $result['body'] = $body;

        return $result;
    }

    /**
     * Umumiy extend olgan modellarning ma’lumotlari
     * @throws Exception
     */
    public static function getI18NData(): array
    {
        $basePath = Yii::$app->i18n->translations ?? [];

        /** kategoriyalar bo‘yicha ro‘yxat */
        $result = [];
        foreach (array_keys($basePath) as $category) {
            if ($category !== 'yii') {
                $category = str_replace('*', '', $category);
                $result['categories'][] = $category;
            }
        }
        $result['header'] = [
            'categories' => Yii::t('multilingual', 'Table Name'),
            'attributes' => Yii::t('multilingual', 'Attributes'),
            'table_iteration' => Yii::t('multilingual', 'Table Iteration'),
        ];

        return $result;
    }

    /** lang_* tablitsalarini chaqirib olish (Create, Update) */
    public static function setCustomAttributes($model, string $attribute = null): array
    {
        $attributes = [];
        $languages = Yii::$app->params['language_list'];
        if (!empty($languages)) {
            foreach ($languages as $language) {
                if (!empty($language['table']) && self::checkTable($language['table'])) {
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
                        $name = 'Language[' . $name . '][' . $attribute . ']';
                    }
                    $attributes[$name] = !empty($data_value->$attribute) ? $data_value->$attribute : null;
                }
            }
        }
        return $attributes;
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar */
    public static function getLangTables(array $languages, bool $is_static = false): array
    {
        $result = [];
        $grouped = [];
        /** Tizimdagi tillar bo‘yicha siklga solish */
        foreach ($languages as $language) {
            $result['language'][$language['name']] = 0;
            if (!empty($language['table'])) {
                /** Dinamik tillar tablitsalarini ro‘yxatini shakllantirish */
                if (self::checkTable($language['table'])) {
                    $result['langTables'][$language['name']] = (new Query())
                        ->select(['table_name', 'table_iteration', 'value'])
                        ->from($language['table'])
                        ->where(['is_static' => $is_static])
                        ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                        ->all();

                    if (empty($grouped)) {
                        foreach ($result['langTables'][$language['name']] as $item) {
                            $grouped[$item['table_name']][$item['table_iteration']] = array_keys(json_decode($item['value'], true));
                        }
                    }
                    $result['table_iterations'] = $grouped;
                }
            }
        }
        return $result;
    }


    /** Ma‘lumotlarni excelga export qilish */
    public static function exportToExcelData($data, $fileName): bool|string
    {
        $is_static = false;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /** Asosiy ustunlar */
        $basicList = ['A', 'B', 'C'];
        $letterList = $basicList;
        $baseHeaders = ['is_static', 'table_name', 'table_iteration'];
        $dynamicJsonKeys = [];
        $staticJsonKeys = [];

        /** Barcha JSON indekslarini aniqlash */
        foreach ($data as $row) {
            $is_static = $row['is_static'];
            $jsonData = json_decode($row['value'], true);
            if ($row['is_static']) {
                $staticJsonKeys[$row['table_name']] = [];
                if (is_array($jsonData)) {
                    $staticJsonKeys[$row['table_name']] = array_unique(array_merge($staticJsonKeys[$row['table_name']], array_keys($jsonData)));
                }
            } else {
                if (is_array($jsonData)) {
                    $dynamicJsonKeys = array_unique(array_merge($dynamicJsonKeys, array_keys($jsonData)));
                }
            }
        }

        /** Barcha sarlavhalar: asosiy ustunlar + JSON indekslari */
        $headers = array_merge($baseHeaders, $dynamicJsonKeys);
        $sheet->fromArray($headers, NULL, 'A1');
        $headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getColumnDimension('A')->setAutoSize(false);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('A')->setWidth(9);
        if ($is_static) {
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getStyle('D1:' . $sheet->getHighestColumn() . '1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
            $sheet->getStyle('D1')->getFont()->setBold(true);
            $sheet->setCellValue("D1", "translate here");
        } else {
            $sheet->getColumnDimension('C')->setAutoSize(false);
            $sheet->getColumnDimension('C')->setWidth(15);
        }
        $sheet->getStyle("A1:B1")->getFont()->setBold(true)->setColor(new Color('777777'));
        $sheet->getStyle("C1")->getFont()->setBold(true)->setColor(new Color('777777'));
        $sheet->getStyle($headerRange)->getFont()->setBold(true);

        /** Asosiy ustunlarni (table_name, table_iteration, va JSON kalitlari) himoyalash */
        $sheet->getStyle('A1:B1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('C1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('D1:' . $sheet->getHighestColumn() . '1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);

        /** Ma'lumotlarni qo'shish */
        $rowNumber = 2;
        foreach ($data as $row) {
            $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));
            $sheet->getStyle("C{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

            $sheet->setCellValue("A{$rowNumber}", (int)$row['is_static']);
            $sheet->setCellValue("B{$rowNumber}", $row['table_name']);
            if ($row['is_static']) {
                /** JSON qiymatlarini alohida qatorda chiqarish */
                $jsonData = json_decode($row['value'], true);
                foreach ($staticJsonKeys[$row['table_name']] as $value) {
                    $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));
                    $sheet->getStyle("C{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

                    $sheet->setCellValue("A{$rowNumber}", (int)$row['is_static']);
                    $sheet->setCellValue("B{$rowNumber}", $row['table_name']);
                    $sheet->setCellValue("C{$rowNumber}", $value);

                    $colLetter = Coordinate::stringFromColumnIndex(4); // A, B, C...
                    $letterList = array_merge($letterList, [$colLetter]);
                    $sheet->setCellValue("{$colLetter}{$rowNumber}", $jsonData[$value] ?? '');
                    $rowNumber++;
                }
            } else {
                $sheet->setCellValue("C{$rowNumber}", $row['table_iteration']);
                /** JSON qiymatlarini mos ustunlarga qo'shish */
                $jsonData = json_decode($row['value'], true);
                $colIndex = 3;
                foreach ($dynamicJsonKeys as $key) {
                    $colIndex++;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex); // A, B, C...
                    $letterList = array_merge($letterList, [$colLetter]);
                    $sheet->setCellValue("{$colLetter}{$rowNumber}", $jsonData[$key] ?? '');
                }
                $rowNumber++;
            }
        }

        /** Ustunlarning kengligini avtomatik sozlash */
        foreach (array_diff_key(array_unique($letterList), $basicList) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        /** JSON qiymatlarini o'zgartirishga ruxsat berish */
        $sheet->getStyle('D2:' . $sheet->getHighestColumn() . $rowNumber)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        /** Himoyani yoqish */
        $spreadsheet->getActiveSheet()->getProtection()->setSheet(true);

        /** Faylni saqlash */
        $writer = new Xlsx($spreadsheet);
        $baseDir = Yii::getAlias('@webroot/uploads');
        $directory = "$baseDir/languages";

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = "$directory/{$fileName}";
        $fileUrl = Yii::getAlias("@web/uploads/languages/{$fileName}");
        $writer->save($filePath);

        return json_encode(['success' => true, 'fileUrl' => $fileUrl]);
    }
}