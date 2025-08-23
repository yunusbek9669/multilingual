<?php

namespace Yunusbek\Multilingual\components\traits;

use Yii;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\commands\Messages;

trait JsonTrait
{
    private static array $json = [];

    /**
     * @throws InvalidConfigException
     */
    public static function getJson()
    {
        $id = 'messages';
        $module = Yii::$app;
        $message = new Messages($id, $module);
        $jsonFile = Yii::getAlias('@app') .'/'. $message->json_file_name.'.json';
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $decoded = json_decode($jsonContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                self::$json = $decoded ?? ['tables' => []];
            } else {
                throw new InvalidConfigException(Yii::t('multilingual', 'Invalid JSON structure detected in {jsonPath}.', ['jsonPath' => $jsonFile]));
            }
        } else {
            throw new InvalidConfigException(Yii::t('multilingual', 'The file {jsonPath} could not be found. Please run the {command} command.', ['jsonPath' => $jsonFile, 'command' => '" php yii ml-extract/attributes "']));
        }
        foreach (self::$json['tables'] ?? [] as &$fields) {
            sort($fields);
        }
        unset($fields);
        ksort(self::$json);
        return self::$json;
    }
}