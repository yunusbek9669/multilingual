<?php

return [
    'sourcePath' => '@app/',
    'translator' => 'MultilingualAttributes::widget',
    'sort' => false,
    'is_static' => false,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];