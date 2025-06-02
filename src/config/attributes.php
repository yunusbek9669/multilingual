<?php

return [
    'sourcePath' => '@app/',
    'translator' => 'MlFields::widget',
    'sort' => false,
    'is_static' => false,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];