<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\db\TableSchema;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Yunusbek\Multilingual\assets\MlAsset;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\components\MlConstant;

class MlFields extends Widget
{
    public ActiveForm $form;

    public ActiveRecord $model;
    public string $table_name;

    public $label = null;
    private array $labelOption = [];
    private string|bool|null $labelValue = null;

    public string|array $attribute;
    public array $wrapperOptions = [];
    public bool $multiple = false;
    public array $options = [];

    public string|null $type;
    public int $order = 0;

    private array $params;

    public array $output = [];

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        MlAsset::register($this->view);

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

        if (isset($this->label)) {
            if (gettype($this->label) === 'string' || gettype($this->label) === 'boolean') {
                $this->labelValue = $this->label;
            } elseif (gettype($this->label) === 'array') {
                $label = $this->label;
                $this->labelValue = $label['text'];
                unset($label['text']);
                $this->labelOption = $label['options'] ?? array_values($label);
            }
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
            'wrapperOptions' => $this->wrapperOptions
        ];
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        $this->output = [];
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

        return $this->makeHtmlField($dashed_ml);
    }

    private function makeHtmlField(string $dashed_ml): string
    {
        if (MlTabs::$isTab) {
            $pane = [];
            foreach ($this->output as $key => $content) {
                $active = Yii::$app->language === $key;
                $fields = '';
                foreach ($content['field'] as $attr => $data) {
                    $fields .= $data['html'];
                }
                $key = MlTabs::$tabId.'_'.$key;
                $pane[] = Html::tag('div', $fields, [
                    'role' => 'tabpanel',
                    'aria-labelledby' => "{$key}-tab",
                    'class' => "link-{$key} tab-pane fade " . ($active ? 'active show' : '')
                ]);
            }
            return Html::tag('div', implode('', $pane), ['class' => 'tab-content mb-0']);
        } else {
            $fields = '';
            foreach ($this->output as $label => $content) {
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
        $basicLabel = $model->getAttributeLabel($params['attribute']);
        $label = $this->labelValue ?? $basicLabel;

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

        $defaultPlaceholder = $basicLabel . " ({$defaultLanguage['short_name']})";
        $defaultLabel = is_bool($this->labelValue) && !$this->labelValue ? false : $label . " ({$defaultLanguage['short_name']}) " . MlConstant::STAR;

        $inputId = Html::getInputId($model, $params['attribute']);
        $inputName = Html::getInputName($model, $params['attribute']);
        $inputNameBasic = $inputName;
        $index = MlConstant::$multiAttributes[$inputNameBasic] ?? $this->order;
        if ($this->multiple) {
            $baseName = (new \ReflectionClass($model))->getShortName();
            MlConstant::$multiAttributes[$inputNameBasic] = $index + 1;
            $inputName = "{$baseName}[{$index}][{$params['attribute']}]";
            $inputId = strtolower(str_replace(['[]','[',']'], ['','-',''], $inputName));
        }
        $field = $form->field($model, $params['attribute'], ['options' => $params['wrapperOptions']])
            ->$type(array_merge(['placeholder' => $defaultPlaceholder . " 🖊", 'value' => $defaultValue, 'id' => $inputId, 'name' => $inputName], $params['options']))
            ->label($defaultLabel, $this->labelOption);

        $output = [
            'label' => $label,
            'html' => (string)$field,
        ];

        if (MlTabs::$isTab) {
            $this->output[$defaultLangKey]['language'] = $defaultLanguage['short_name'];
            $this->output[$defaultLangKey]['field'][$params['attribute']] = $output;
        } else {
            $this->output[$label][$defaultLangKey] = $output['html'];
        }

        $this->langFields($field, $model, $params, $languages, $type, $basicLabel, $label, $index);
    }


    private function langFields($field, $model, $params, $languages, $type, $basicLabel, $label, $index): void
    {
        $customAttributes = LanguageService::setCustomAttributes($model, $params['attribute'], $this->multiple, $index);
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $key => $value)
            {
                preg_match_all('/\[([^\]]*)\]/', $key, $matches);
                $langKey = $matches[1][0];
                $dynamic_label = $label;
                $language = $languages[$langKey];
                if (!empty($language['short_name'])) {
                    $dynamic_label .= " ({$language['short_name']})";
                    $dynamic_placeholder = $basicLabel . " ({$language['short_name']})";
                }

                $fg_option = $params['wrapperOptions'];
                $input_options = array_merge(['class' => 'form-control', 'placeholder' => $dynamic_placeholder . " 🖊"], $params['options']);
                $input_options['id'] = str_replace(['[',']'], ['-'], $key);
                if (!empty($language['rtl'])) {
                    $input_options['dir'] = 'rtl';
                    $input_options['placeholder'] = $dynamic_placeholder . " ✏️";
                    $fg_option['style'] = implode(' ', ["direction: rtl !important; text-align: right !important;", $fg_option['style'] ?? '']);
                }

                $fieldLabelClass = null;
                $fieldLabel = $field->labelOptions['class'] ?? null;
                if (!empty($fieldLabel)) {
                    if (is_array($fieldLabel)) {
                        if (in_array('has-star', $fieldLabel)) {
                            $fieldLabel = array_values($fieldLabel);
                            unset($fieldLabel['has-star']);
                            $fieldLabelClass = implode(' ', $fieldLabel) . ' has-required';
                        } else {
                            $fieldLabelClass = implode(' ', $fieldLabel);
                        }
                    } elseif (is_string($fieldLabel)) {
                        $fieldLabelClass = $fieldLabel;
                        if (str_contains($fieldLabelClass, 'has-star')) {
                            $fieldLabelClass = str_replace('has-star', '', $fieldLabelClass) . ' has-required';
                        }
                    }
                }
                $fields = Html::beginTag('div', $fg_option);
                if (!empty($label)) {
                    $fields .= Html::label($dynamic_label, $input_options['id'], array_merge(['class' => $fieldLabelClass], $this->labelOption));
                }
                $fields .= Html::$type($key, $value, $input_options);
                $fields .= Html::endTag('div');

                if (MlTabs::$isTab) {
                    $this->output[$langKey]['language'] = $language['short_name'];
                    $this->output[$langKey]['field'][$params['attribute']]['label'] = $label;
                    $this->output[$langKey]['field'][$params['attribute']]['html'] = $fields;
                } else {
                    $this->output[$label][$langKey] = $fields;
                }
            }
        } else {
            $field->inputOptions['class'] = ($field->inputOptions['class'] ?? '') . ' bg-light text-muted';
            foreach ($languages as $key => $language) {
                if (!empty($language['table'])) {
                    $l = $label;
                    if (!empty($label)) {
                        $l = Html::label("$label ({$language['short_name']})", $key, $this->labelOption);
                    }
                    if (MlTabs::$isTab) {
                        $this->output[$key]['language'] = $language['short_name'];
                        $this->output[$key]['field'][$params['attribute']]['label'] = $l;
                        $this->output[$key]['field'][$params['attribute']]['html'] = Html::tag('div', ($l ?? '') . Html::tag('div',
                                Yii::t('multilingual', 'No tables to translate were found. Please run the {command} command.', [
                                    'command' => '<code style="cursor: pointer">php yii ml-extract/attributes</code>'
                                ]),
                                array_merge($field->inputOptions, ['id' => $key])
                            ), $params['wrapperOptions']);
                    } else {
                        $this->output[$l][$key] = Html::tag('div', ($l ?? '') . Html::tag('div',
                                Yii::t('multilingual', 'No tables to translate were found. Please run the {command} command.', [
                                    'command' => '<code style="cursor: pointer">php yii ml-extract/attributes</code>'
                                ]),
                                array_merge($field->inputOptions, ['id' => $key])
                            ), $params['wrapperOptions']);
                    }
                }
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
