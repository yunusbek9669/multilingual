<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\db\TableSchema;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\components\MlConstant;

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
.has-required::after {
    content: '';
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace
}
CSS;

class MlFields extends Widget
{
    public ActiveForm $form;

    public ActiveRecord $model;
    public string $table_name;

    public string|array $attribute;
    public array $wrapperOptions = [];
    public array $options = [];

    public string|null $type;
    public string|null $tab;

    private array $params;

    public static array $output = [];

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        if (!isset($this->model)) {
            throw new InvalidConfigException('"model" is not defined!');
        }

        if (!$this->model instanceof \yii\base\Model) {
            throw new InvalidConfigException('"model" must be an instance of yii\base\Model.');
        }

        if (!isset($this->form)) {
            throw new InvalidConfigException('"form" is not defined!');
        }

        $model = $this->model;

        if (!in_array('id', array_keys($model->getAttributes()))) {
            throw new InvalidConfigException("The {{$model::tableName()}} table does not have an id column.");
        }

        if (!isset($this->table_name)) {
            throw new InvalidConfigException('"table_name" is important for the multilingual.json file to be saved correctly, it cannot be retrieved from "$model::tableName()" via the console command "php yii ml-extract/attributes".');
        }

        if (isset($this->type) && !in_array($this->type, ['textInput', 'textarea'])) {
            throw new InvalidConfigException('"type" can be "textInput" or "textarea" only.');
        }

        if (isset($this->tab) && !in_array($this->tab, ['basic', 'vertical'])) {
            throw new InvalidConfigException('"tab" can be "basic" or "vertical" only.');
        }

        if ($model::tableName() !== $this->table_name) {
            throw new InvalidConfigException("Invalid 'table_name' entered! \n".'$model::tableName()'." is not equal to {$this->table_name}.");
        }

        if (!isset($this->attribute)) {
            throw new InvalidConfigException("'attribute' is not defined!\n |—'attribute' => 'your_attribute_name' or
            |—'attribute' => [
            |——'your_attribute_name_1',
            |——'your_attribute_name_2'
            |—]\n must be entered in the form");
        }

        if (!empty($this->options) && isset($this->options['class'])) {
            $this->options['class'] .= ' form-control';
        }

        if (!empty($this->wrapperOptions) && isset($this->wrapperOptions['class'])) {
            $this->wrapperOptions['class'] .= ' highlight-addon';
        } else {
            $this->wrapperOptions = ['class' => 'form-group highlight-addon'];
        }

        $tableSchema = $model->getTableSchema();

        if (is_array($this->attribute)) {
            foreach ($this->attribute as $attribute) {
                $this->checkAttribute($tableSchema->columns, $attribute);
            }
        } else {
            $this->checkAttribute($tableSchema->columns, $this->attribute);
        }

        $this->params = [
            'tableSchema' => $tableSchema,
            'model' => $this->model,
            'form' => $this->form,
            'options' => $this->options,
            'wrapperOptions' => $this->wrapperOptions,
            'tab' => $this->tab ?? null,
        ];
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        self::$output = [];
        if ($this->params['tab'] !== null) {
            MlConstant::$tabId++;
        }
        $dashed_ml = Html::tag('div', '', ['class' => 'dashed-ml']);

        if (is_array($this->attribute)) {
            foreach ($this->attribute as $attr) {
                $this->params['attribute'] = $attr;
                $this->params['type'] = $this->type ?? $this->inputType($this->params['tableSchema'], $attr);
                $this->setAttribute($this->params, $dashed_ml);
            }
        } else {
            $this->params['attribute'] = $this->attribute;
            $this->params['type'] = $this->type ?? $this->inputType($this->params['tableSchema'], $this->attribute);
            $this->setAttribute($this->params, $dashed_ml);
        }

        $result = $this->makeHtmlField($dashed_ml);
        global $css;
        $this->view->registerCss($css);
        return $result;
    }

    private function setNavBar(string $id, string $name, bool $active): string
    {
        $active = $active ? 'active' : '';
        if ($this->params['tab'] === 'vertical') {
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

    private function makeHtmlField(string $dashed_ml): string
    {
        if ($this->params['tab'] !== null) {
            $li = [];
            $pane = [];
            foreach (self::$output as $key => $content) {
                $active = Yii::$app->language === $key;
                $fields = '';
                foreach ($content['field'] as $attr => $data) {
                    $fields .= $data['html'];
                }
                $key = MlConstant::$tabId.'_'.$key;
                $li[] = $this->setNavBar($key, $content['language'], $active);
                $pane[] = Html::tag('div', $fields, [
                    'role' => 'tabpanel',
                    'id' => "link-{$key}",
                    'aria-labelledby' => "{$key}-tab",
                    'class' => 'tab-pane fade ' . ($active ? 'active show' : '')
                ]);
            }
            $tabLinkParam = [
                'id' => MlConstant::$tabId."Tab",
                'role' => 'tablist',
                'class' => 'nav nav-tabs mb-4'
            ];
            if ($this->params['tab'] === 'vertical') {
                $tabLinkParam['class'] = 'nav flex-column nav-pills';
                $tabLinkParam['aria-orientation'] = $this->params['tab'];
                $tabLink = Html::tag('div', Html::tag('ul', implode('', $li), $tabLinkParam), ['class' => 'col-md-auto col-sm-12']);
                return  Html::tag('div', $tabLink . Html::tag('div', Html::tag('div', implode('', $pane), ['class' => 'tab-content mb-0', 'id' => MlConstant::$tabId."TabContent"]), ['class' => 'col-md col-sm-12']) . $this->makeLine($dashed_ml), ['class' => 'row']);
            }
            $tabLink = Html::tag('ul', implode('', $li), $tabLinkParam);
            return $tabLink . Html::tag('div', implode('', $pane) . $this->makeLine($dashed_ml), ['class' => 'tab-content mb-0', 'id' => MlConstant::$tabId."TabContent"]);
        } else {
            $fields = '';
            foreach (self::$output as $label => $content) {
                $fields .= $this->makeLine($dashed_ml . $label . $dashed_ml);
                foreach ($content as $key => $html) {
                    $fields .= $html;
                }
            }
            return $fields.$this->makeLine($dashed_ml);
        }
    }

    /**
     * @throws Exception
     */
    public function setAttribute(array $params, string $dashed_ml): void
    {
        $type = $params['type'];
        $form = $params['form'];
        $model = $params['model'];
        $label = $model->getAttributeLabel($params['attribute']);

        $defaultValue = (new yii\db\Query())
            ->from($model::tableName())
            ->select($params['attribute'])
            ->where(['id' => $model->id])
            ->scalar();
        $languages = Yii::$app->params['language_list'];
        $defaultLangKey = null;
        $defaultLanguage = null;
        foreach ($languages as $key => $lang) {
            if (empty($lang['table'])) {
                $defaultLangKey = $key;
                $defaultLanguage = $lang;
                break;
            }
        }

        $defaultLabel = $label . " ({$defaultLanguage['short_name']})";

        $field = $form->field($model, $params['attribute'], ['options' => $params['wrapperOptions']])
            ->$type(array_merge(['placeholder' => $defaultLabel . " 🖊", 'value' => $defaultValue], $params['options']))
            ->label($defaultLabel . ' '.MlConstant::STAR);

        $output = [
            'label' => $label,
            'html' => (string)$field,
        ];

        if (!empty($params['tab'])) {
            self::$output[$defaultLangKey]['language'] = $defaultLanguage['short_name'];
            self::$output[$defaultLangKey]['field'][$params['attribute']] = $output;
        } else {
            self::$output[$label][$defaultLangKey] = $output['html'];
        }
        foreach (LanguageService::setCustomAttributes($model, $params['attribute']) as $key => $value)
        {
            preg_match('/lang_(\w+)/', $key, $matches);
            $dynamic_label = $label;
            $language = $languages[$matches[1]];
            if (!empty($language['short_name'])) {
                $dynamic_label .= " ({$language['short_name']})";
            }

            $fg_option = $params['wrapperOptions'];
            $input_options = array_merge(['class' => 'form-control', 'placeholder' => $dynamic_label . " 🖊"], $params['options']);
            $input_options['id'] = str_replace(['[',']'], ['_'], $key);
            if (!empty($language['rtl'])) {
                $input_options['dir'] = 'rtl';
                $input_options['placeholder'] = $dynamic_label . " ✏️";
                $fg_option['style'] = implode(' ', ["direction: rtl !important; text-align: right !important;", $fg_option['style'] ?? '']);
            }

            $fieldLabelClass = [];
            $fieldLabel = $field->labelOptions['class'] ?? null;
            if (!empty($fieldLabel)) {
                if (is_array($fieldLabel)) {
                    $fieldLabelClass = array_values($fieldLabel);
                    unset($fieldLabelClass['has-star']);
                    $fieldLabelClass = implode(' ', $fieldLabelClass);
                } else {
                    $fieldLabelClass = str_replace('has-star', '', $fieldLabel);
                }
            }
            $fields = Html::beginTag('div', $fg_option);
            $fields .= Html::label($dynamic_label, $input_options['id'], ['class' => "$fieldLabelClass has-required"]);
            $fields .= Html::$type($key, $value, $input_options);
            $fields .= Html::endTag('div');

            if (!empty($params['tab'])) {
                self::$output[$matches[1]]['language'] = $language['short_name'];
                self::$output[$matches[1]]['field'][$params['attribute']]['label'] = $label;
                self::$output[$matches[1]]['field'][$params['attribute']]['html'] = $fields;
            } else {
                self::$output[$label][$matches[1]] = $fields;
            }
        }
    }

    private function makeLine($dashed_line): string
    {
        return Html::beginTag('div', [
                'style' => 'display: flex; color: #888'
            ]) . $dashed_line . Html::endTag('div');
    }

    /**
     * @throws InvalidConfigException
     */
    private function checkAttribute(array $columns, string $attribute): void
    {
        if (!in_array($columns[$attribute]->type, ['string', 'text'])) {
            throw new InvalidConfigException("The value of attribute - {{$attribute}} must be of type string.");
        }
    }

    private function inputType(TableSchema $tableSchema, string $attribute): string
    {
        $column = $tableSchema->columns[$attribute];

        if ($column->type === 'text') {
            return 'textarea';
        } else {
            return 'textInput';
        }
    }
}
