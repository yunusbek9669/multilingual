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
 */
class BaseLanguageList extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%language_list}}';
    }

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
                $query->andWhere(['not', ['id' => $this->id]]);
            }],
        ];
    }

    /**
     * @throws InvalidParamException
     * @throws Exception
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
                $response['message'] = Yii::t('multilingual', 'Error saving image');
            }
        }
        if ($response['status']) {
            $response = Multilingual::importFromExcel($this);
        }
        if (!$response['status']) {
            $this->addError($response['code'], $response['message']);
            return false;
        }
        return parent::beforeSave($insert);
    }

    public static function isTableExists($tableName): bool
    {
        $schema = Yii::$app->db->schema;
        return in_array($tableName, $schema->getTableNames());
    }
}
