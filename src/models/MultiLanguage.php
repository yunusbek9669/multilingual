<?php

namespace Yunusbek\Multilingual\models;

use yii\behaviors\TimestampBehavior;
use yii\behaviors\AttributeBehavior;
use yii\db\BaseActiveRecord;
use yii\db\ActiveRecord;
use yii\db\Exception;
use Yii;

/**
 * This is the model class for table "multi_language".
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
class MultiLanguage extends ActiveRecord
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
    public static function tableName()
    {
        return 'multi_language';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_number', 'status', 'created_at', 'created_by', 'updated_at', 'updated_by'], 'default', 'value' => null],
            [['order_number', 'status', 'created_at', 'created_by', 'updated_at', 'updated_by'], 'integer'],
            [['name'], 'string', 'max' => 30],
            [['short_name', 'key'], 'string', 'max' => 5],
            [['import_excel'], 'file', 'skipOnEmpty' => true, 'extensions' => 'xlsx'],
            [['image', 'table'], 'string', 'max' => 50],
            [['table', 'key'], 'unique', 'filter' => function ($query) {
                $query->andWhere(['and',
                    ['status' => 1],
                    ['not', ['id' => $this->id]]
                ]);
            }],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name' => Yii::t('app', 'Name'),
            'short_name' => Yii::t('app', 'Short Name'),
            'key' => Yii::t('app', 'Key'),
            'image' => Yii::t('app', 'Image'),
            'table' => Yii::t('app', 'Table'),
            'order_number' => Yii::t('app', 'Order Number'),
            'status' => Yii::t('app', 'Status'),
            'created_at' => Yii::t('app', 'Created At'),
            'created_by' => Yii::t('app', 'Created By'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'updated_by' => Yii::t('app', 'Updated By'),
        ];
    }

    /**
     * @throws Exception
     */
    public function beforeSave($insert)
    {
        if ($this->isNewRecord)
        {
            $order_number = self::find()
                ->select(['order_number'])
                ->where([
                    'status' => 1
                ])
                ->asArray()
                ->orderBy(['order_number' => SORT_DESC])
                ->limit(1)
                ->one();
            $this->order_number = !empty($order_number) ? $order_number['order_number'] + 1 : 1;
        }
        $this->table = 'lang_'.$this->key;
        return parent::beforeSave($insert);
    }

    public static function getTableList()
    {
        $tables = Yii::$app->db->schema->tableNames;
        $list = [];
        foreach ($tables as $table) {
            $list[$table] = ucfirst(str_replace('_', ' ', $table));
        }
        return $list;
    }

    public static function isTableExists($tableName)
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
