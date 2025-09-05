<?php

namespace Yunusbek\Multilingual\models;

use Yii;
use yii\db\Query;
use yii\db\Connection;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\components\MlConstant;
use Yunusbek\Multilingual\components\traits\SqlHelperTrait;

class MlQuery extends Query
{
    use SqlHelperTrait;

    private string $current_table = '';
    protected string $langTable = MlConstant::LANG_PREFIX;
    private static array $jsonTables;

    /**
     * @throws InvalidConfigException
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        self::$jsonTables = self::getJson()['tables'];
        $this->langTable .= Yii::$app->language;
    }

    public function from($tables): static
    {
        $this->explodeTableAlias($tables, $this->current_table, $this->customAlias);
        return parent::from($tables);
    }


    /**
     * Executes the query and returns all results as an array.
     * @param Connection|null $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }
}