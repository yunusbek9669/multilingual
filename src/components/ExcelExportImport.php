<?php

namespace Yunusbek\Multilingual\components;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Yii;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\Memory\SimpleCache3;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\UploadedFile;
use Yunusbek\Multilingual\models\BaseLanguageList;
use Yunusbek\Multilingual\models\BaseLanguageQuery;

class ExcelExportImport
{
    /** xarflarni raqam bilan alishtirish */
    protected static function encodeString($input): string
    {
        $map = array_combine(range('a', 'z'), range(1, 26));
        $input = strtolower($input);
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            if (isset($map[$char])) {
                $output .= $map[$char] . '-';
            } else {
                $output .= $char . '-';
            }
        }

        return rtrim($output, '-');
    }

    /** raqamlarni xarf bilan alishtirish */
    protected static function decodeString($encoded): string
    {
        $reverseMap = array_combine(range(1, 26), range('a', 'z'));
        $parts = explode('-', $encoded);
        $output = '';

        foreach ($parts as $part) {
            if (ctype_digit($part) && isset($reverseMap[(int)$part])) {
                $output .= $reverseMap[(int)$part];
            } else {
                $output .= $part;
            }
        }
        return $output;
    }

    /** Ma‘lumotlarni excelga export qilish
     * @throws InvalidConfigException
     */
    public static function exportToExcelData($data, $fileName): bool|string
    {
        $k_n = [];
        $jsonData = LanguageService::getJson()['tables'];
        $iteration = 1;
        foreach (LanguageService::tableTextFormList($jsonData, true) as $table_name => $name) {
            $k_n[$name] = $iteration++;
        }
        Settings::setCache(new SimpleCache3());
        $is_static = false;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /** Asosiy ustunlar */
        $basicList = ['A', 'B', 'C'];
        $letterList = $basicList;
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
            }
        }

        /** Barcha sarlavhalar: asosiy ustunlar + JSON indekslari */
        $sheet->getColumnDimension('B')->setAutoSize(true);

        /** Asosiy ustunlarni (table_name, table_iteration, va JSON kalitlari) himoyalash */
        $sheet->getStyle('A1:B1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('C1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('D1:' . $sheet->getHighestColumn() . '1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        if ($is_static) {
            $headers = ['ID', 'Category', 'Keywords', 'Translate here'];
            $sheet->getColumnDimension('A')->setAutoSize(false);
            $sheet->getColumnDimension('A')->setWidth(3);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getStyle('D1:' . $sheet->getHighestColumn() . '1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
            $sheet->getStyle('D1')->getFont()->setBold(true);
            $sheet->getStyle("A1:C1")->getFont()->setBold(true)->setColor(new Color('777777'));
            $sheet->fromArray($headers, NULL, 'A1');
            $headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);

            /** Ma'lumotlarni qo'shish */
            $rowNumber = 2;
            foreach ($data as $row) {
                $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));
                $sheet->getStyle("C{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

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
            }
        } else {
            $headers = ['ID', 'Table Name', 'Attribute', 'Value'];
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->fromArray($headers, NULL, 'A1');
            $headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1';
            $sheet->getStyle("A1:C1")->getFont()->setBold(true)->setColor(new Color('777777'));
            $sheet->getStyle($headerRange)->getFont()->setBold(true);

            /** Ma'lumotlarni qo'shish */
            $rowNumber = 2;
            foreach ($data as $row) {
                $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));
                $sheet->getStyle("C{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

                $jsonData = json_decode($row['value'], true);
                foreach ($jsonData as $attribute => $value) {
                    $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));
                    $sheet->getStyle("C{$rowNumber}")->getFont()->setItalic(true)->setColor(new Color('777777'));

                    $sheet->setCellValue("A{$rowNumber}", (int)$row['is_static'].':'.(int)$k_n[$row['table_name']].':'.(int)$row['table_iteration']);
                    $sheet->setCellValue("B{$rowNumber}", $row['table_name']);
                    $sheet->setCellValue("C{$rowNumber}", $attribute);
                    $sheet->setCellValue("D{$rowNumber}", $value);
                    $rowNumber++;
                }
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
        $tableNames = $db->schema->getTableNames();
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
                        $attributes = [];
                        $hash_attributes = array_slice($data[0], 3);
                        foreach ($hash_attributes as $attribute) {
                            $attributes[] = self::decodeString(trim($attribute));
                        }
                        unset($data[0]);
                        if (!empty($data)) {
                            /** static tarjimalr uchun */
                            $static = [];
                            foreach ($data as $row) {
                                if ($row[0] == '1') {
                                    $static[$row[1]][$tableNames[(int)$row[2]]] = $row[3];
                                }
                            }
                            foreach ($static as $category => $values) {
                                $upsert = BaseLanguageQuery::upsert($table, $category, 0, true, $values);
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
                                $upsert = BaseLanguageQuery::upsert($table, $tableNames[(int)$row[1]], (int)$row[2], false, $values);
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