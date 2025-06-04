<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Query;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\traits\SqlHelperTrait;

class MlQuery extends Query
{
    use SqlHelperTrait;

    private string $current_table;
    protected string $langTable = MlConstant::LANG_PREFIX;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->langTable .= Yii::$app->language;
    }

    public function from($tables): static
    {
        parent::from($tables);

        if (is_array($tables)) {
            foreach ($tables as $alias => $table) {
                if (is_string($alias)) {
                    $this->customAlias = $alias;
                    $this->current_table = $table;
                } else {
                    $this->separateAlias($table);
                }
            }
        } else {
            $this->separateAlias($tables);
        }

        return $this;
    }

    /**
     * @throws InvalidConfigException
     */
    public function joinWithLang(string $joinType = 'leftJoin', string $current_table = null): static
    {
        if (Yii::$app->params['table_available'] ?? false) {
            $current_table = $current_table ?? $this->current_table;
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
                            $this->setSingleSelect($joinType, $this->join, $current_table, $attribute_name, $column);
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
            $this->from = [$this->customAlias => $this->current_table];
        }
        return parent::prepare($builder);
    }

    private function separateAlias($table): void
    {
        if (preg_match('/^([\w.]+)\s+(?:as\s+)?(\w+)$/i', $table, $matches)) {
            $this->customAlias = $matches[2];
            $this->current_table = $matches[1];
        } else {
            $this->customAlias = $table;
            $this->current_table = $table;
        }
    }
}