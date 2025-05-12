<?php

return [
    'sourcePath' => ['@app/', '@vendor/yunusbek/multilingual/src/'],
    'translator' => 'Yii::t',
    'sort' => false,
    'is_static' => true,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];