<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\Exception;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\models\BaseLanguageQuery;

trait MultilingualTrait
{
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
}