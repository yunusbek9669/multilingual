<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Yunusbek\Multilingual\components\LanguageService;

global $css;
$css = <<<CSS
.dashed-ml {
    -webkit-box-flex: 1;
    flex: 1 0 0%;
    border: none;
    height: 1px;
    width: -webkit-fill-available;
    background: repeating-linear-gradient(
        to right,
        #ccc 0px,
        #ccc 15px,
        transparent 15px,
        transparent 20px
    );
    margin: 15px 0 20px 0;
}
CSS;

class MultilingualLanguageList extends Widget
{

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        $active = Yii::$app->params['active_language'];
        $list = '<div class="dropdown-menu dropdown-menu-right">';
        foreach (Yii::$app->params['language_list'] as $key => $value) {
            $list .= Html::a(Html::img($value['image'], ['height' => 12, 'alt' => 'flag']), ['/multilingual/language/select-lang', 'lang' => $key], ['class' => 'dropdown-item notify-item language']);
        }
        $list .= '</div>';
        $html = <<<HTML
            <button type="button" class="btn header-item waves-effect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img id="header-lang_img" src="{$active['image']}" alt="Header Language" height="16"> <span class="align-middle">{$active['name']}</span>
            </button>
            {$list}
        HTML;

        global $css;
        $this->view->registerCss($css);
        return $html;
    }
}
