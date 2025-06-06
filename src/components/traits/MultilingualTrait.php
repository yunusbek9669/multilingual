<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\db\Query;
use yii\db\Exception;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\models\MlActiveQuery;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\LanguageService;

trait MultilingualTrait
{
    use SqlRequestTrait;
    private bool $where = true;
    private array $jsonData = [];

    /**
     * @throws InvalidConfigException
     */
    public function __construct()
    {
        foreach (self::getJson()['where'] ?? [] as $attribute => $value) {
            if (isset($this->$attribute) && $this->$attribute != $value) {
                $this->where = false;
                break;
            }
        }
        $this->bootMultilingual();
    }

    /** Avto tarjimani ulash */
    public static function find(): MlActiveQuery
    {
        return new MlActiveQuery(static::class);
    }

    public function bootMultilingual(): void
    {
        $this->on(self::EVENT_AFTER_INSERT, [$this, 'multilingualAfterSave']);
        $this->on(self::EVENT_AFTER_UPDATE, [$this, 'multilingualAfterSave']);
    }

    /**
     * @throws InvalidConfigException
     */
    public function afterDelete()
    {
        $this->multilingualAfterDelete();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function multilingualAfterSave(): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        $response = [
            'status' => true,
            'message' => Yii::t('multilingual', 'Error')
        ];

        $post = Yii::$app->request->post(MlConstant::MULTILINGUAL);
        if (!empty($post)) {
            $response = $this->setDynamicLanguageValue($post);
        } elseif (!$this->where) {
            $response = $this->deleteLanguageValue();
        }

        if ($response['status']) {
            $transaction->commit();
        } else {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', $response['message']);
        }
    }

    /**
     * @throws InvalidConfigException
     */
    private function multilingualAfterDelete(): void
    {
        $this->deleteLanguageValue();
        if (method_exists(get_parent_class($this), 'afterDelete')) {
            parent::afterDelete();
        }
    }

    /** Tarjimalarni o‘chirish
     * @throws InvalidConfigException
     */
    public function deleteLanguageValue(): array
    {
        $db = Yii::$app->db;
        $response = [];
        $response['status'] = true;
        $response['message'] = 'success';
        $data = LanguageService::setCustomAttributes($this);
        foreach ($data as $key => $value) {
            try {
                if (isset($this->id)) {
                    $execute = $db->createCommand()
                        ->delete($key, [
                            'table_name' => static::tableName(),
                            'table_iteration' => $this->id,
                        ])
                        ->execute();
                    if ($execute <= 0) {
                        Yii::error("Failed to delete language value from the {$key} table. {table_name: {$this::tableName()}, table_iteration: {$this->id}}.", ' ' . $response['message']);
                    }
                }
            } catch (Exception $e) {
                $response['message'] = self::errToStr($e);
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
     * @throws InvalidConfigException
     */
    public function setDynamicLanguageValue(array $post = []): array
    {
        $response = [
            'status' => true,
            'code' => 'success',
            'message' => 'success',
        ];
        foreach ($post as $lang_table => $data) {
            foreach ($data as $table_index => $datum) {
                $table_name = array_keys(self::getJson()['tables'])[$table_index] ?? null;
                if (isset($this->id) && !empty($table_name)) {
                    $upsert = self::singleUpsert($lang_table, $table_name, $this->id, false, $datum);
                    if ($upsert <= 0) {
                        Yii::error("An error occurred while writing {{$lang_table}} table. {table_name: $table_name, table_iteration: {$this->id}}. Attributes: " . json_encode($this->attributes), ' ' . $response['message']);
                        $response['message'] = Yii::t('multilingual', 'An error occurred while writing "{table}"', ['table' => $lang_table]);
                        $response['code'] = 'error';
                        $response['status'] = false;
                        break;
                    }
                }
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
     * @throws InvalidConfigException
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
        $upsert = self::singleUpsert($langTable, $category, 0, true, array_replace($allMessages, $value));
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
}