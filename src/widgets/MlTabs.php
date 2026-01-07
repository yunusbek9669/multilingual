<?php
namespace Yunusbek\Multilingual\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\base\InvalidConfigException;
use Yunusbek\Multilingual\components\MlConstant;

class MlTabs extends Widget
{
    public static string $tabId = '';
    public static bool $isTab = false;
    public array $contentOptions = [];
    public array $headerOptions = [];
    public string $tab = 'basic';

    private $content = '';

    public function init()
    {
        parent::init();
        self::$isTab = true;
        self::$tabId = uniqid('ml_');
        if (isset($this->tab) && !in_array($this->tab, ['basic', 'vertical'])) {
            throw new InvalidConfigException('"tab" can be "basic" or "vertical" only.');
        }
        $this->contentOptions['class'] = 'ml-tab-content-group ' . ($this->contentOptions['class'] ?? '');
        $this->headerOptions['class'] = ($this->headerOptions['class'] ?? '');
        $this->headerOptions['class'] = 'ml-nav-links nav ' . $this->headerOptions['class'];
        if ($this->tab === 'basic') {
            if (!str_contains($this->contentOptions['class'], 'pt-')) {
                $this->contentOptions['class'] .= ' pt-3 ';
            }
            $this->headerOptions['class'] .= ' nav-tabs dash-box';
        } else {
            $this->headerOptions['class'] = ' flex-column nav-pills ' . ($this->headerOptions['class'] ?? '');
        }
        ob_start();
    }

    public function run()
    {
        $this->content = ob_get_clean();

        $li = [];
        foreach (Yii::$app->params['language_list'] as $key => $content) {
            $active = Yii::$app->language === $key;
            $key = self::$tabId.'_'.$key;
            $li[] = $this->setNavBar($key, $content, $active);
        }
        $tabLinkParam = array_merge($this->headerOptions, [
            'id' => self::$tabId."Tab",
            'role' => 'tablist'
        ]);

        if ($this->tab === 'vertical') {
            $tabLinkParam['aria-orientation'] = $this->tab;
            $tabLink = Html::tag('div', Html::tag('ul', implode('', $li), $tabLinkParam), ['class' => 'col-md-auto col-sm-12']);
            echo Html::tag('div', $tabLink . Html::tag('div', Html::tag('div', $this->content, $this->contentOptions), ['class' => 'col-md col-sm-12']) . Html::tag('div', Html::tag('div', '', ['class' => 'dashed-ml']), ['style' => 'display: flex; color: #888']), ['class' => 'row']);
        } else {
            $tabLink = Html::tag('ul', implode('', $li), $tabLinkParam);
            echo $tabLink . Html::tag('div', $this->content, $this->contentOptions);
        }
        $this->view->registerJs("
            document.querySelectorAll('.ml-nav-links [data-bs-toggle]').forEach(function (tabEl) {
                tabEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    let mainTab = new bootstrap.Tab(tabEl);
                    mainTab.show();
                    let targetClass = tabEl.getAttribute('href');
                    document.querySelectorAll(targetClass).forEach(function (el) {
                        let allSiblings = el.parentNode.querySelectorAll('.tab-pane');
                        allSiblings.forEach(sib => sib.classList.remove('active', 'show'));
                        el.classList.add('active');
                        setTimeout(() => { el.classList.add('show'); }, 1);
                    });
                });
            });
        ");
    }


    public static function end()
    {
        parent::end();
        self::$isTab = false;
    }



    private function setNavBar(string $id, array $language, bool $active): string
    {
        $has_star = '<i class="has-star" data-bs-toggle="tooltip" title="'.Yii::t('multilingual', 'Required language').'"></i>';
        if (!isset($language['table'])) {
            $name = $language['short_name'] . ' '.MlConstant::STAR . $has_star;
            $required = 'required';
        } else {
            $name = $language['short_name'] . $has_star;
            $required = $language['is_required'] ? 'required' : '';
        }
        $active = $active ? 'active' : '';
        if ($this->tab === 'vertical') {
            return Html::tag('li',
                Html::a($name, ".link-$id", [
                    'id' => "$id-tab",
                    'role' => 'tab',
                    'data-bs-toggle' => 'pill',
                    'aria-controls' => "link-$id",
                    'aria-selected' => "true",
                    'tabindex' => "-1",
                    'class' => "nav-link $active $required",
                ])
            );
        }
        return Html::tag('li',
            Html::a($name, ".link-$id", [
                'id' => "$id-tab",
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'aria-controls' => "link-$id",
                'aria-selected' => "true",
                'class' => "nav-link $active $required",
            ]),
            [
                'role' => 'presentation',
                'class' => 'nav-item'
            ]
        );
    }
}
