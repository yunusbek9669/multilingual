<?php

namespace Yunusbek\Multilingual\assets;

use yii\web\AssetBundle;

class MlAsset extends AssetBundle
{
    public $sourcePath = '@vendor/yunusbek/multilingual/dist';
    public $css = [
        'css/mlcss.css',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
