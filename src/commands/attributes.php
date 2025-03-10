<?php

use yii\db\Query;

return [
    'sourcePath' => '@app/',
    'languages' => array_column((new Query())->select(['key'])->from('{{%language_list}}')->all(), 'key'),
    'translator' => 'MultilingualAttributes::widget',
    'sort' => false,
    'is_static' => false,
    'removeUnused' => true,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];