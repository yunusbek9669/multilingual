<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\Exception;
use Yunusbek\Multilingual\commands\Messages;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\models\BaseLanguageQuery;

trait MultilingualTrait
{
    private bool $where = true;
    private array $jsonData = [];
    private string $jsonFile;
    public function getPath($controllerInstance): void
    {
        $this->jsonFile = Yii::getAlias('@app') .'/'. $controllerInstance->json_file_name.'.json';
    }

    public function __construct()
    {
        $id = 'messages';
        $module = Yii::$app;
        $message = new Messages($id, $module);
        $this->getPath($message);
        if (file_exists($this->jsonFile)) {
            $jsonContent = file_get_contents($this->jsonFile);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->jsonData = json_decode($jsonContent, true);
            }
        }
    }

    /** Avto tarjimani ulash */
    public static function find(): BaseLanguageQuery
    {
        return new BaseLanguageQuery(static::class);
    }

    /**
     * @throws Exception
     */
    public function afterSave($insert, $changedAttributes)
    {
        $transaction = Yii::$app->db->beginTransaction();
        $response = [
            'status' => true,
            'message' => Yii::t('multilingual', 'Error')
        ];

        $post = Yii::$app->request->post('Language');
        if (!empty($post)) {
            $response = $this->setDynamicLanguageValue($post);
        } elseif (!$this->where()) {
            $response = $this->deleteLanguageValue();
        }

        if ($response['status']) {
            $transaction->commit();
        } else {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', $response['message']);
        }
        if (method_exists(get_parent_class($this), 'afterSave')) {
            parent::afterSave($insert, $changedAttributes);
        }
    }

    public function afterDelete()
    {
        $response = $this->deleteLanguageValue();
        if ($response['status']) {
            Yii::error("deleteLanguageValue() failed: " . json_encode($this->attributes), ' ' . $response['message'], __METHOD__);
        }
        if (method_exists(get_parent_class($this), 'afterDelete')) {
            parent::afterDelete();
        }
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
                        'table_name' => static::tableName(),
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
     */
    public function setDynamicLanguageValue(array $post = []): array
    {
        $db = Yii::$app->db;
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success',
        ];
        $table_name = static::tableName();
        foreach ($post as $table => $data) {
            $upsert = BaseLanguageQuery::upsert($table, $table_name, $this->id ?? null, false, $data);
            if ($upsert <= 0) {
                $response['message'] = Yii::t('multilingual', 'An error occurred while writing "{table}"', ['table' => $table]);
                $response['code'] = 'error';
                $response['status'] = false;
                break;
            }
        }
        return $response;
    }

    protected function where(): bool
    {
        foreach ($this->jsonData['where'] ?? [] as $attribute => $value) {
            if ($this->$attribute != $value) {
                $this->where = false;
                break;
            }
        }
        return $this->where;
    }
}