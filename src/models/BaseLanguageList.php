<?php

namespace Yunusbek\Multilingual\models;

use yii\base\InvalidParamException;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\AttributeBehavior;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;
use yii\db\ActiveRecord;
use yii\db\Exception;
use Yii;

/**
 * This is the model class for table "language_list".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $short_name
 * @property string|null $key
 * @property string|null $image
 * @property string|null $table
 * @property string|null $import_excel
 * @property int|null $order_number
 * @property int|null $status
 * @property int|null $created_at
 * @property int|null $created_by
 * @property int|null $updated_at
 * @property int|null $updated_by
 */
class BaseLanguageList extends ActiveRecord
{
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
                    return $this->status ?? 1;
                },
            ]
        ];
    }

    public $import_excel;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
            [['short_name', 'key'], 'string'],
            [['import_excel'], 'file', 'skipOnEmpty' => true, 'extensions' => 'xlsx'],
            [['image', 'table'], 'string'],
            [['table', 'key'], 'unique', 'filter' => function ($query) {
                $query->andWhere(['and',
                    ['status' => 1],
                    ['not', ['id' => $this->id]]
                ]);
            }],
        ];
    }

    /**
     * @throws InvalidParamException
     */
    public function beforeSave($insert)
    {
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success'
        ];
        $this->table = 'lang_'.$this->key;
        if ($this->isNewRecord)
        {
            $order_number = self::find()
                ->select(['order_number'])
                ->where(['status' => 1])
                ->orderBy(['order_number' => SORT_DESC])
                ->asArray()
                ->limit(1)
                ->one();
            $this->order_number = !empty($order_number) ? $order_number['order_number'] + 1 : 1;
            if (!$this::isTableExists($this->table)) {
                $response = BaseLanguageQuery::createLangTable($this->table);
            }
        } else {
            $oldTableName = 'lang_' . $this->getOldAttribute('key');
            if ($this->getOldAttribute('key') !== $this->key) {
                $response = BaseLanguageQuery::updateLangTable($oldTableName, $this->table);
            }
        }

        $file = UploadedFile::getInstance($this, 'image');
        if (!empty($file))
        {
            $dirPath = 'uploads/' . $this::tableName();
            $fileNamePath = '/' . $dirPath . '/' . $this->table . "." . $file->extension;
            $absolutePath = Yii::getAlias("@webroot{$fileNamePath}");

            if (!is_dir($dirPath)) { mkdir($dirPath); }

            if ($file->saveAs($absolutePath)) {
                $this->image = $fileNamePath;
            } else {
                $response['status'] = false;
                $response['message'] = 'Rasmni saqlashda xatolik';
            }
        }
        if ($response['status']) {
            $response = MultilingualModel::importFromExcel($this);
        }
        if (!$response['status']) {
            Yii::$app->session->setFlash($response['code'], $response['message']);
            return false;
        }
        return parent::beforeSave($insert);
    }

    public static function isTableExists($tableName): bool
    {
        $schema = Yii::$app->db->schema;
        return in_array($tableName, $schema->getTableNames());
    }

    /**
     * @throws Exception
     */
    public static function createLangTable(string $tableName): array
    {
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        try {
            Yii::$app->db->createCommand("CREATE TABLE {$tableName} (table_name VARCHAR(50), table_iteration INT, value JSON, PRIMARY KEY (table_name, table_iteration))")->execute();
            Yii::$app->db->createCommand("CREATE INDEX idx_{$tableName}_table_iteration ON {$tableName} (table_iteration)")->execute();
            Yii::$app->db->createCommand("CREATE INDEX idx_{$tableName}_table_name ON {$tableName} (table_name)")->execute();
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }
        return $response;
    }

    /**
     * @throws Exception
     */
    public static function updateLangTable(string $oldTableName, string $tableName): array
    {
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        try {
            Yii::$app->db->createCommand("ALTER TABLE {$oldTableName} RENAME TO {$tableName}")->execute();
            Yii::$app->db->createCommand("ALTER INDEX idx_{$oldTableName}_table_iteration RENAME TO idx_{$tableName}_table_iteration")->execute();
            Yii::$app->db->createCommand("ALTER INDEX idx_{$oldTableName}_table_name RENAME TO idx_{$tableName}_table_name")->execute();
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }
        return $response;
    }
}
