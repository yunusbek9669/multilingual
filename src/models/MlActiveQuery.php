<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\traits\SqlHelperTrait;

/**
 *
 * @property-write mixed $customAttributes
 */
class MlActiveQuery extends ActiveQuery
{
    use SqlHelperTrait;
    protected string $langTable = MlConstant::LANG_PREFIX;

    public function __construct($modelClass, $config = [])
    {
        parent::__construct($modelClass, $config);
        $this->langTable .= Yii::$app->language;
    }

    public function alias($alias): static
    {
        $this->customAlias = $alias;
        $this->from[$alias] = $this->modelClass::tableName();
        return $this;
    }

    /**
     * @throws InvalidConfigException
     */
    public function joinWithLang(string $joinType = 'leftJoin', string $current_table = null): static
    {
        if (Yii::$app->params['table_available'] ?? false) {
            $current_table = $current_table ?? $this->modelClass::tableName();
            $alias = $this->customAlias ?? $current_table;
            $ml_attributes = self::getJson()['tables'][$current_table] ?? [];
            if (empty($this->select)) {
                $this->setFullSelect($joinType, $current_table, $alias, $ml_attributes);
            } else {
                foreach ($this->select as $attribute_name => $column) {
                    if (!str_contains($column, 'COUNT') && !empty($this->join)) {
                        $full = str_contains($column, '.*') ? $column : (str_contains($attribute_name, '.*') ? $attribute_name : null);
                        if (!empty($full) && $this->customAlias === explode('.', $full)[0]) {
                            $this->setFullSelect($joinType, $current_table, $alias, $ml_attributes);
                        } else {
                            $this->setSingleSelect($joinType, $this->join, $this->modelClass::tableName(), $attribute_name, $column);
                        }
                    } elseif (in_array($column, $ml_attributes)) {
                        $this->setSingleSelectNotAlias($joinType, $current_table, $alias, $column);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @throws InvalidConfigException
     */
    public function prepare($builder)
    {
        $this->joinWithLang();
        if ($this->customAlias) {
            $tableName = $this->modelClass::tableName();
            $this->from = [$this->customAlias => $tableName];
        }
        return parent::prepare($builder);
    }
}