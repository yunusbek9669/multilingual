<?php

use yii\db\Query;

return [
    'sourcePath' => '@app/',
    'languages' => array_column((new Query())->select(['key'])->from('{{%language_list}}')->all(), 'key'),
    'translator' => 'Yii::t',
    'sort' => false,
    'is_static' => true,
    'removeUnused' => true,
    'only' => ['*.php'],
    'except' => [
        '.git',
        'vendor',
        'tests',
    ],
];