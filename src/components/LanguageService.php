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

        $tableResult = self::getLangTables($languages, $params);
        $translate_list = array_fill_keys(array_keys($tableResult['language']), null);

        $result = [
            'total' => $tableResult['total'],
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
                    /** Ro‘yxatni shakllantirish */
                    $tableValue = $tableRow['value'];
                    $unique_name = $tableRow['table_name'] . '::' . $tableRow['table_iteration'];
                    unset($tableRow['is_static']);
                    unset($tableRow['value']);
                    $body[$unique_name]['table_name'] = $tableRow['table_name'];
                    $body[$unique_name]['table_iteration'] = $tableRow['table_iteration'];
                    if (!isset($body[$unique_name]['is_full'])) {
                        $body[$unique_name]['is_full'] = true;
                    }

                    /** lang_* jadvallarining value:json ustuni bo‘yicha siklga solish */
                    foreach ($tableValue as $attribute => $value) {
                        if (empty($body[$unique_name]['translate'][$attribute])) {
                            $body[$unique_name]['translate'][$attribute] = $translate_list;
                        }
                        /** Asosiy modeldan olingan qiymatni qo‘shish */
                        $body[$unique_name]['translate'][$attribute][$key] = $value;
                        if (empty($value)) {
                            $result['header']['language'][$key] += 1;
                            $body[$unique_name]['is_full'] = false;
                        }

                    }
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
    public static function getI18NData(array $params): array
    {
        $basePath = Yii::$app->i18n->translations ?? [];

        /** kategoriyalar bo‘yicha ro‘yxat */
        $result = [
            'header' => [
                'languages' => Yii::t('multilingual', 'Languages'),
                'categories' => Yii::t('multilingual', 'Categories')
            ],
            'tables' => []
        ];
        foreach (Yii::$app->params['language_list'] as $language) {
            if (isset($language['table'])) {
                $result['tables'][$language['name']] = $language['table'];
                foreach (array_keys($basePath) as $category) {
                    if ($category !== 'yii') {
                        $category = str_replace('*', '', $category);
                        $incomplete = (new \yii\db\Query())
                            ->select(['table_name', 'table_iteration', 'value'])
                            ->from($language['table'])
                            ->where([
                                'table_name' => $category,
                                'is_static' => (int)$params['is_static'],
                            ])
                            ->andWhere(new \yii\db\Expression("EXISTS (SELECT 1 FROM json_each_text({$language['table']}.value) kv WHERE kv.value = '')"))
                            ->one();
                        $count = 0;
                        if (!empty($incomplete)) {
                            foreach (json_decode($incomplete['value']) as $row) {
                                $count += (int)empty($row);
                            }
                        }
                        $result['body'][$language['name']][$category] = $category.' '.'<span class="ml-not-translated ' . ($count > 0 ? 'has' : 'not') . '">'.$count.'</span>';
                    }
                }
            }
        }

        return $result;
    }

    /** Bazadagi barcha tarjimon (lang_*) tablitsalar */
    public static function getLangTables(array $languages, array $params): array
    {
        $result = [];
        $result['total'] = 0;
        $emptyEntries = [];
        $limit = 1000;
        $page = isset($params['page']) ? (int)$params['page'] : 0;
        $offset = $page * $limit;

        $isStatic = (int)($params['is_static'] ?? 0);
        $isAll = (int)($params['is_all'] ?? 0);

        /** Tizimdagi tillar bo‘yicha siklga solish */
        foreach ($languages as $language) {
            $result['language'][$language['name']] = 0;
            if (!empty($language['table'])) {
                /** Bo‘sh qiymatli ("" value) lang_* dan topilgan table_name + table_iteration larni yig‘ish */
                $query = (new \yii\db\Query())
                    ->select(['table_name', 'table_iteration', 'value'])
                    ->from($language['table'])
                    ->where(['is_static' => $isStatic]);
                if ($isAll === 0) {
                    $getEmpty = new \yii\db\Expression("
                        EXISTS (
                            SELECT 1
                            FROM json_each_text({$language['table']}.value) kv
                            WHERE kv.value = ''
                        )
                    ");
                    $query->andWhere($getEmpty);
                }

                $totalCount = (int)$query->count();
                $totalPages = (int)floor($totalCount / $limit);

                $result['total'] = max($result['total'], $totalPages);

                $rows = $query
                    ->limit($limit)
                    ->offset($offset)
                    ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                    ->all();

                foreach ($rows as $row) {
                    $key = $row['table_name'] . '::' . $row['table_iteration'];
                    $emptyEntries[$key] = ['table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration']];
                }
            }
        }

        /** Barcha lang_* dan shu kombinatsiyalarga mos bo‘lgan satrlarni yig‘ish */
        foreach ($languages as $language) {
            if (!empty($language['table'])) {
                $result['langTables'][$language['name']] = [];
                foreach ($emptyEntries as $entry) {
                    $row = (new \yii\db\Query())
                        ->select(['table_name', 'table_iteration', 'value'])
                        ->from($language['table'])
                        ->where([
                            'is_static' => (int)$params['is_static'],
                            'table_name' => $entry['table_name'],
                            'table_iteration' => $entry['table_iteration']
                        ])
                        ->orderBy(['table_name' => SORT_ASC, 'table_iteration' => SORT_ASC])
                        ->one();

                    if ($row) {
                        $row['value'] = json_decode($row['value'], true);
                        $result['langTables'][$language['name']][$row['table_name'] . '::' . $row['table_iteration']] = $row;
                    }
                }
                ksort($result['langTables'][$language['name']]);
            }
        }

        /** Asl jadvallardan (table_name bo‘yicha) ham o‘qib olish */
        if (!empty($result['langTables'])) {
            foreach ($languages as $language) {
                if (empty($language['table'])) {
                    $default_lang = [];
                    foreach (reset($result['langTables']) as $entry) {
                        $tableName = $entry['table_name'];
                        $iteration = $entry['table_iteration'];
                        $row = (new \yii\db\Query())
                            ->select(array_keys($entry['value']))
                            ->from($tableName)
                            ->where(['id' => $iteration])
                            ->one();

                        if ($row) {
                            $default_lang[$language['name']][$tableName . '::' . $iteration] = [
                                'table_name' => $tableName,
                                'table_iteration' => $iteration,
                                'value' => $row
                            ];
                        }
                    }
                    $result['langTables'] = array_merge($default_lang, $result['langTables']);
                    break;
                }
            }
        }
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