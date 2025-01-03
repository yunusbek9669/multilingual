<?php
namespace Yunusbek\Multilingual\models;

use PhpOffice\PhpSpreadsheet\IOFactory;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Query;
use Yii;


/**
 * @package Yunusbek\Multilingual\models
 *
 * @property-write array $languageValue
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

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
            ],
            [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created_by', 'updated_by'],
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => function () {
                    return Yii::$app->user->id;
                },
            ],
            [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => 'status',
                ],
                'value' => function () {
                    return $this->hasAttribute('status') ? ($this->status ?? 1) : null;
                },
            ]
        ];
    }

    /**
     * @throws Exception
     */
    public static function find(): ActiveQuery|BaseLanguageQuery
    {
        return (new BaseLanguageQuery(static::class))->joinWithLang();
    }

    /**
     * @throws Exception
     */
    public function afterSave($insert, $changedAttributes)
    {
        $transaction = Yii::$app->db->beginTransaction();
        $response = [];
        $response['status'] = true;
        $response['message'] = Yii::t('app', 'Error');
        try
        {
            $post = Yii::$app->request->post('Language');
            if (!empty($post))
            {
                $response = $this->setLanguageValue($post);
            } elseif (isset($this->status) && $this->status !== 1)
            {
                $response = $this->deleteLanguageValue();
            }
        } catch (Exception $e)
        {
            $response['message'] = $e->getMessage();
            $response['status'] = false;
        }

        if ($response['status']) {
            $transaction->commit();
        } else {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', $response['message']);
        }
        parent::afterSave($insert, $changedAttributes);
    }

    /** Tarjimalarni qo‘shib qo‘yish
     * @throws Exception
     */
    public function setLanguageValue(array $post = []): array
    {
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        foreach ($post as $table => $data)
        {
            $exists = (new Query())
                ->from($table)
                ->where(condition: [
                    'table_name' => $this::tableName(),
                    'table_iteration' => $this->id,
                ])
                ->exists();

            if ($exists) {
                $updated = Yii::$app->db->createCommand()
                    ->update($table, ['value' => $data], [
                        'table_name' => $this::tableName(),
                        'table_iteration' => $this->id,
                    ])
                    ->execute();

                if ($updated <= 0) {
                    $response['message'] = '"' . $table . '"ni yangilashda xatolik';
                    $response['status'] = false;
                    break;
                }
            } else {
                $inserted = Yii::$app->db->createCommand()
                    ->insert($table, [
                        'table_name' => $this::tableName(),
                        'table_iteration' => $this->id,
                        'value' => $data
                    ])
                    ->execute();

                if ($inserted <= 0) {
                    $response['message'] = '"' . $table . '"ni saqlashda xatolik';
                    $response['status'] = false;
                    break;
                }
            }
        }
        return $response;
    }

    /** Tarjimalarni o‘chirish
     * @throws Exception
     */
    public function deleteLanguageValue(): array
    {
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        $data = LanguageService::setCustomAttributes($this);
        foreach ($data as $key => $value)
        {
            if ($key !== 'lang_default')
            {
                $exists = (new Query())
                    ->from($key)
                    ->where(condition: [
                        'table_name' => $this::tableName(),
                        'table_iteration' => $this->id,
                    ])
                    ->exists();

                if ($exists) {
                    $deleted = Yii::$app->db->createCommand()
                        ->delete($key, [
                            'table_name' => $this::tableName(),
                            'table_iteration' => $this->id,
                        ])
                        ->execute();

                    if ($deleted <= 0) {
                        $response['message'] = '"' . $key . '"ni o‘chirishda xatolik';
                        $response['status'] = false;
                        break;
                    }
                }
            }
        }
        return $response;
    }

    /** Barcha extend olgan tablitsalardan excelga export qilish
     * @throws \Exception
     */
    public static function exportBasicToExcel(): bool|string
    {
        $modelsExtended = LanguageService::getChildModels(Multilingual::class);
        $data = [];
        foreach ($modelsExtended as $model)
        {
            $tableName = $model::tableName();
            $modelData = LanguageService::getTranslateAbleData($model);
            if (!empty($modelData))
            {
                foreach ($modelData as $modelRow)
                {
                    $id = $modelRow['id'];
                    unset($modelRow['id']);
                    $data[] = [
                        'table_name' => $tableName,
                        'table_iteration' => $id,
                        'value' => json_encode($modelRow),
                    ];
                }
            }
        }

        if (empty($data)) {
            throw new \Exception("Jadvalda ma'lumot topilmadi.");
        }

        return LanguageService::exportToExcelData($data, "all.xlsx");
    }

    /** Tablitsadan excelga export qilish
     * @throws \Exception
     */
    public static function exportToExcel($tableName): bool|string
    {
        // PostgreSQLdan ma'lumotlarni olish
        $data = Yii::$app->db->createCommand("SELECT * FROM {$tableName}")->queryAll();

        if (empty($data)) {
            throw new \Exception("Jadvalda ma'lumot topilmadi.");
        }

        return LanguageService::exportToExcelData($data, "{$tableName}.xlsx");
    }

    /** Exceldan tablitsaga import qilish */
    public static function importFromExcel(BaseLanguageList $model): array
    {
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $excel_file = UploadedFile::getInstance($model, 'import_excel');
        if ($excel_file) {
            if ($model->validate()) {
                try {
                    if (!is_dir('uploads/import_language')) { mkdir('uploads/import_language'); }
                    $filePath = 'uploads/import_language/' . $excel_file->name;
                    $excel_file->saveAs($filePath);

                    $spreadsheet = IOFactory::load($filePath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $data = $sheet->toArray();

                    unlink($filePath);

                    if (!empty($data))
                    {
                        $attributes = array_slice($data[0], 2);
                        unset($data[0]);
                        if (!empty($data))
                        {
                            foreach ($data as $row)
                            {
                                if (isset($row[0], $row[1]))
                                {
                                    $filteredArray = array_slice($row, 2);
                                    $values = array_combine($attributes, $filteredArray);
                                    $values = array_filter($values, function($value) {
                                        return $value !== null;
                                    });
                                    $exists = (new Query())
                                        ->from($model->table)
                                        ->where([
                                            'table_name' => $row[0],
                                            'table_iteration' => $row[1]
                                        ])
                                        ->one();

                                    if ($exists) {
                                        $updated = Yii::$app->db->createCommand()
                                            ->update($model->table,
                                                ['value' => $values],
                                                ['table_name' => $row[0], 'table_iteration' => $row[1]]
                                            )
                                            ->execute();

                                        if ($updated <= 0) {
                                            $json = json_encode($values);
                                            $response['status'] = false;
                                            $response['message'] = "«{$row[0]}, {$row[1]}, {$json}»ni saqlashda xatolik";
                                            break;
                                        }
                                    } else {
                                        $inserted = Yii::$app->db->createCommand()
                                            ->insert($model->table, [
                                                'table_name' => $row[0],
                                                'table_iteration' => $row[1],
                                                'value' => $values,
                                            ])
                                            ->execute();

                                        if ($inserted <= 0) {
                                            $json = json_encode($values);
                                            $response['status'] = false;
                                            $response['message'] = "«{$row[0]}, {$row[1]}, {$json}»ni saqlashda xatolik";
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $response['status'] = false;
                    $response['message'] = $e->getMessage();
                }
            } else {
                $response['status'] = false;
                $response['message'] = $model->getErrors();
            }
        }
        return $response;
    }
}