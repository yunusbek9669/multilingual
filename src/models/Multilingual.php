<?php

namespace Yunusbek\Multilingual\models;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Yii;
use yii\base\InvalidParamException;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\db\Exception;
use yii\db\Query;
use yii\web\UploadedFile;
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

    /** Avto tarjimani ulash */
    public static function find(): ActiveQuery|BaseLanguageQuery
    {
        return new BaseLanguageQuery(static::class);
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
        try {
            $post = Yii::$app->request->post('Language');
            if (!empty($post)) {
                $response = $this->setDynamicLanguageValue($post);
            } elseif (isset($this->status) && $this->status !== 1) {
                $response = $this->deleteLanguageValue();
            }
        } catch (Exception $e) {
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

    /** Tarjimalarni o‘chirish */
    public function deleteLanguageValue(): array
    {
        $db = Yii::$app->db;
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        $data = LanguageService::setCustomAttributes($this);
        foreach ($data as $key => $value) {
            try {
                $db->createCommand()
                    ->delete($key, [
                        'table_name' => $this::tableName(),
                        'table_iteration' => $this->id ?? null,
                    ])
                    ->execute();
            } catch (Exception $e) {
                $response['message'] = BaseLanguageQuery::modErrToStr($e);
                $response['code'] = 'error';
                $response['status'] = false;
            }
        }
        return $response;
    }

    /** Tarjimalarni qo‘shib qo‘yish
     * @param array $post
     * @return array
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
        foreach ($post as $table => $data) {
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
        $upsert = Yii::$app->db->createCommand()
            ->upsert($langTable, [
                'is_static' => true,
                'table_name' => $category,
                'table_iteration' => 0,
                'value' => array_replace($allMessages, $value),
            ], [
                'value' => array_replace($allMessages, $value)
            ])->execute();

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