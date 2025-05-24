<?php

use Yunusbek\Multilingual\components\MlConstant;

return [
    'sourcePath' => ['@app/', '@vendor/yunusbek/'.MlConstant::MULTILINGUAL.'/src/'],
    'translator' => 'Yii::t',
    'json_file_name' => MlConstant::MULTILINGUAL,
    'sort' => false,
    'is_static' => true,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];