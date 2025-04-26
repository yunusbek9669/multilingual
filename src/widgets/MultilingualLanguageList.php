<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

class MultilingualLanguageList extends Widget
{
    public array $options = [];

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        $active = Yii::$app->params['active_language'];

        $list = '';
        foreach (Yii::$app->params['language_list'] as $key => $value) {
            $list .= Html::a($this->setImg($value, 12), ['/multilingual/language/select-lang', 'lang' => $key], ['class' => 'dropdown-item notify-item '.($value['active'] ? 'active' : '')]);
        }
        $html = Html::tag('div', $this->setImg($active, 16), array_merge(['data-toggle' => "dropdown", 'aria-haspopup' => "true", 'aria-expanded' => "false"], $this->options));

        $dropdown_menu = Html::tag('div', $list, ['class' => 'dropdown-menu dropdown-menu-right']);
        return Html::tag('div', $html.$dropdown_menu, ['class' => 'dropdown']);
    }

    /**
     * @throws Exception
     */
    public function setImg(array $value, int $height): string
    {
        return Html::img($value['image'], ['height' => $height, 'alt' => 'flag', 'class' => 'mr-1']).$value['name'];
    }
}
