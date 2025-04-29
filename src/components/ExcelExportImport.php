<?php

namespace Yunusbek\Multilingual\components;

use Yii;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\Memory\SimpleCache3;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yunusbek\Multilingual\models\BaseLanguageList;

class ExcelExportImport
{
    /** Ma‘lumotlarni excelga export qilish */
    public static function exportToExcelData($data, $fileName): bool|string
    {
        Settings::setCache(new SimpleCache3());
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

    /** Exceldan tablitsaga import qilish
     * @param BaseLanguageList $model
     * @return array
     */
    public static function importFromExcel(BaseLanguageList $model): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $excel_file = UploadedFile::getInstance($model, 'import_excel');
        if ($excel_file) {
            if ($model->validate()) {
                $table = $model->table;
                $transaction = $db->beginTransaction();
                try {
                    if (!is_dir('uploads/import_language')) {
                        mkdir('uploads/import_language');
                    }
                    $filePath = 'uploads/import_language/' . $excel_file->name;
                    $excel_file->saveAs($filePath);

                    $spreadsheet = IOFactory::load($filePath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $data = $sheet->toArray();

                    unlink($filePath);

                    if (!empty($data)) {
                        $attributes = array_slice($data[0], 3);
                        unset($data[0]);
                        if (!empty($data)) {
                            /** static tarjimalr uchun */
                            $static = [];
                            foreach ($data as $row) {
                                if ($row[0] == '1') {
                                    $static[$row[1]][$row[2]] = $row[3];
                                }
                            }
                            foreach ($static as $category => $values) {
                                $upsert = $db->createCommand()
                                    ->upsert($table, [
                                        'is_static' => true,
                                        'table_name' => $category,
                                        'table_iteration' => 0,
                                        'value' => $values,
                                    ], [
                                        'value' => $values
                                    ])->execute();

                                if ($upsert <= 0) {
                                    $json = json_encode($values);
                                    $response['status'] = false;
                                    $response['code'] = 'error';
                                    $response['message'] = Yii::t('multilingual', 'Error saving {category}, {json}', ['category' => $category, 'json' => $json]);
                                    break;
                                }
                            }

                            /** dynamic tarjimalr uchun */
                            $dynamic = array_filter($data, function ($item) {
                                return $item[0] === '0';
                            });
                            foreach ($dynamic as $row) {
                                $filteredArray = array_slice($row, 3);
                                $values = array_combine($attributes, $filteredArray);
                                $values = array_filter($values, function ($value) {
                                    return $value !== null;
                                });
                                $upsert = $db->createCommand()
                                    ->upsert($table, [
                                        'is_static' => false,
                                        'table_name' => $row[1],
                                        'table_iteration' => (int)$row[2],
                                        'value' => $values,
                                    ], [
                                        'value' => $values
                                    ])->execute();

                                if ($upsert <= 0) {
                                    $json = json_encode($values);
                                    $response['status'] = false;
                                    $response['code'] = 'error';
                                    $response['message'] = Yii::t('multilingual', 'Error saving {category}, {json}', ['category' => $row[1], 'json' => $json]);
                                    break;
                                }
                            }
                        }
                    }
                    Yii::$app->cache->flush();
                    $transaction->commit();
                } catch (Exception $e) {
                    $response['status'] = false;
                    $response['code'] = 'error';
                    $response['message'] = $e->getMessage();
                    $transaction->rollBack();
                }
            } else {
                $response['status'] = false;
                $response['code'] = 'error';
                $response['message'] = $model->getErrors();
            }
        }
        return $response;
    }
}