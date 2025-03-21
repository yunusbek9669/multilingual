<?php
namespace Yunusbek\Multilingual\models;

use PhpOffice\PhpSpreadsheet\IOFactory;
use yii\base\InvalidParamException;
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
        $response['message'] = Yii::t('multilingual', 'Error');
        try
        {
            $post = Yii::$app->request->post('Language');
            if (!empty($post))
            {
                $response = $this->setDynamicLanguageValue($post);
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

    public function afterDelete()
    {
        $response = $this->deleteLanguageValue();
        if ($response['status']) {
            Yii::error("deleteLanguageValue() failed: " . json_encode($this->attributes), ' ' . $response['message'], __METHOD__);
        }
        parent::afterDelete();
    }

    /** Tarjimalarni qo‘shib qo‘yish
     * @throws Exception
     */
    public function setDynamicLanguageValue(array $post = []): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success',
        ];
        $table_name = $this::tableName();
        foreach ($post as $table => $data)
        {
            $upsert = $db->createCommand()
                ->upsert($table, [
                    'table_name' => $table_name,
                    'table_iteration' => $this->id ?? null,
                    'is_static' => false,
                    'value' => $data,
                ], [
                    'value' => $data
                ])->execute();

            if ($upsert <= 0) {
                $response['message'] = Yii::t('multilingual', 'An error occurred while writing "{table}"', ['table' => $table]);
                $response['code'] = 'error';
                $response['status'] = false;
                break;
            }
        }
        return $response;
    }

    /** Tarjimalarni o‘chirish
     * @throws Exception
     */
    public function deleteLanguageValue(): array
    {
        $db = Yii::$app->db;
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        $data = LanguageService::setCustomAttributes($this);
        foreach ($data as $key => $value)
        {
            try {
                $db->createCommand()
                    ->delete($key, [
                        'table_name' => $this::tableName(),
                        'table_iteration' => $this->id ?? null,
                    ])
                    ->execute();
            } catch (\yii\db\Exception $e) {
                $response['message'] = BaseLanguageQuery::modErrToStr($e);
                $response['code'] = 'error';
                $response['status'] = false;
            }
        }
        return $response;
    }

    /** Static ma'lumotlarni tarjima qilish
     * @throws \Exception
     */
    public static function setStaticLanguageValue($category, $message): array
    {
        $db = Yii::$app->db;
        $data = [];
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $languages = Yii::$app->params['language_list'];
        foreach ($languages as $language)
        {
            if (!empty($language['table']))
            {
                $table = $language['table'];
                $upsert = $db->createCommand()
                    ->upsert($table, [
                        'is_static' => true,
                        'table_name' => $category,
                        'table_iteration' => 0,
                        'value' => $data,
                    ], [
                        'value' => $data
                    ])->execute();

                if ($upsert <= 0) {
                    $response['message'] = Yii::t('multilingual', 'An error occurred while writing "{table}"', ['table' => $table]);
                    $response['code'] = 'error';
                    $response['status'] = false;
                    break;
                }
            }
        }

        return $response;
    }

    /** Tablitsadan excelga export qilish
     * @throws \Exception
     */
    public static function exportToExcel($tableName, bool $is_static = true): bool|string
    {
        $data = Yii::$app->db->createCommand("SELECT * FROM {$tableName} WHERE is_static = :is_static" . ($is_static ? '' : ' AND table_iteration != 0'))
            ->bindValue(':is_static', $is_static, \PDO::PARAM_BOOL)->queryAll();

        if (empty($data)) {
            throw new \Exception(Yii::t('multilingual', 'No information was found in the table'));
        }

        return LanguageService::exportToExcelData($data, "{$tableName}.xlsx");
    }

    /** Exceldan tablitsaga import qilish
     * @throws Exception|InvalidParamException
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
            if ($model->validate())
            {
                $table = $model->table;
                $transaction = $db->beginTransaction();
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
                        $attributes = array_slice($data[0], 3);
                        unset($data[0]);
                        if (!empty($data))
                        {
                            /** static tarjimalr uchun */
                            $static = [];
                            foreach ($data as $row) {
                                if ($row[0] == '1') {
                                    $static[$row[1]][$row[2]] = $row[3];
                                }
                            }
                            foreach ($static as $category => $values)
                            {
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
                                    $response['message'] = Yii::t('multilingual', '"Error saving {category}, {json}"', ['category' => $category, 'json' => $json]);
                                    break;
                                }
                            }

                            /** dynamic tarjimalr uchun */
                            $dynamic = [];
                            $dynamic = array_filter($data, function ($item) {
                                return $item[0] === '0';
                            });
                            foreach ($dynamic as $row)
                            {
                                $filteredArray = array_slice($row, 3);
                                $values = array_combine($attributes, $filteredArray);
                                $values = array_filter($values, function($value) {
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
                                    $response['message'] = Yii::t('multilingual', '"Error saving {category}, {json}"', ['category' => $row[1], 'json' => $json]);
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