<?php
namespace Yunusbek\Multilingual\widgets;

use yii\bootstrap5\Html;
use yii\bootstrap5\Widget;

class MlTabs extends Widget
{
    private string $tabId;
    public $options = [];
    public string $tab = 'basic';

    private $content = '';

    public function init()
    {
        parent::init();
        ob_start(); // umumiy contentni yig‘ib olish uchun
    }

    public function run()
    {
        $this->content = ob_get_clean();

        $li = [];
        foreach (MlFields::$output as $key => $content) {
            $active = Yii::$app->language === $key;
            $key = $this->tabId.'_'.$key;
            $li[] = $this->setNavBar($key, $content['language'], $active);
        }
        $tabLinkParam = [
            'id' => $this->tabId."Tab",
            'role' => 'tablist',
            'class' => 'nav nav-tabs mb-4'
        ];

        if ($this->tab === 'vertical') {
            $tabLinkParam['class'] = 'nav flex-column nav-pills';
            $tabLinkParam['aria-orientation'] = $this->tab;
            $tabLink = Html::tag('div', Html::tag('ul', implode('', $li), $tabLinkParam), ['class' => 'col-md-auto col-sm-12']);
            return  Html::tag('div', $tabLink . Html::tag('div', Html::tag('div', implode("\n", $this->content), ['class' => 'tab-content mb-0', 'id' => $this->tabId."TabContent"]), ['class' => 'col-md col-sm-12']) . $this->makeLine($dashed_ml), ['class' => 'row']);
        }
        $tabLink = Html::tag('ul', implode('', $li), $tabLinkParam);
        return $tabLink . Html::tag('div', implode("\n", $this->content) . Html::tag('div', Html::tag('div', '', ['class' => 'dashed-ml']), [
                    'style' => 'display: flex; color: #888'
                ]), ['class' => 'tab-content mb-0', 'id' => $this->tabId."TabContent"]);
    }



    private function setNavBar(string $id, string $name, bool $active): string
    {
        $active = $active ? 'active' : '';
        if ($this->tab === 'vertical') {
            return Html::tag('li',
                Html::a($name, "#link-$id", [
                    'id' => "$id-tab",
                    'role' => 'tab',
                    'data-bs-toggle' => 'pill',
                    'aria-controls' => "link-$id",
                    'aria-selected' => "true",
                    'tabindex' => "-1",
                    'class' => "nav-link $active",
                ])
            );
        }
        return Html::tag('li',
            Html::a($name, "#link-$id", [
                'id' => "$id-tab",
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'aria-controls' => "link-$id",
                'aria-selected' => "true",
                'class' => "nav-link $active",
            ]),
            [
                'role' => 'presentation',
                'class' => 'nav-item'
            ]
        );
    }
}
