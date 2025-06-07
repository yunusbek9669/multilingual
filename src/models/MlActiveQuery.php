<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\db\ActiveQuery;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\traits\SqlHelperTrait;

class MlActiveQuery extends ActiveQuery
{
    use SqlHelperTrait;

    private string $current_table;
    protected string $langTable = MlConstant::LANG_PREFIX;
    private array $jsonTables;

    /**
     * @throws InvalidConfigException
     */
    public function __construct($modelClass, $config = [])
    {
        parent::__construct($modelClass, $config);
        $this->jsonTables = self::getJson()['tables'];
        $this->langTable .= Yii::$app->language;
        $this->current_table = $modelClass::tableName();
    }

    public function alias($alias): static
    {
        $this->customAlias = $alias;
        $this->from[$alias] = $this->modelClass::tableName();
        return $this;
    }
}