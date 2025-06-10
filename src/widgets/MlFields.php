<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\db\ActiveRecord;
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
CSS;

class MlFields extends Widget
{
    public ActiveForm $form;

    public ActiveRecord $model;
    public string $table_name;

    public string|array $attribute;
    public array $wrapperOptions = [];
    public array $options = [];

    public int|null $col;

    private array $params;

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        if (!isset($this->model)) {
            throw new InvalidConfigException('"model" is not defined!');
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

        $col = 'col-12';
        $this->params = [
            'tableSchema' => $tableSchema,
            'model' => $this->model,
            'form' => $this->form,
            'options' => $this->options,
            'wrapperOptions' => $this->wrapperOptions,
            'col' => !empty($this->col) ? 'col-' . $this->col : $col,
        ];
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        $dashed_ml = Html::tag('div', '', ['class' => 'dashed-ml']);

        if (is_array($this->attribute)) {
            $result = '';
            foreach ($this->attribute as $attr) {
                $this->params['attribute'] = $attr;
                $result .= $this->setAttribute($this->params, $dashed_ml);
            }
        } else {
            $this->params['attribute'] = $this->attribute;
            $result = $this->setAttribute($this->params, $dashed_ml);
        }
        global $css;
        $this->view->registerCss($css);
        return $result . $this->makeLine($dashed_ml);
    }

    /**
     * @throws Exception
     */
    public function setAttribute(array $params, string $dashed_ml): string
    {
        $form = $params['form'];
        $model = $params['model'];

        $defaultValue = (new yii\db\Query())
            ->from($model::tableName())
            ->select($params['attribute'])
            ->where(['id' => $model->id])
            ->scalar();
        $languages = Yii::$app->params['language_list'];
        $defaultLanguage = null;
        foreach ($languages as $lang) {
            if (empty($lang['table'])) {
                $defaultLanguage = $lang;
                break;
            }
        }

        $label = $model->getAttributeLabel($params['attribute']);
        $defaultLabel = $label . " ({$defaultLanguage['name']})";

        $output = Html::tag('div',
            $form->field($model, $params['attribute'], ['options' => $params['wrapperOptions']])
                ->textInput(array_merge(['placeholder' => $defaultLabel . " 🖊", 'value' => $defaultValue], $params['options']))
                ->label($defaultLabel . ' '.MlConstant::STAR),
            ['class' => $params['col']]
        );
        foreach (LanguageService::setCustomAttributes($model, $params['attribute']) as $key => $value)
        {
            preg_match('/lang_(\w+)/', $key, $matches);
            $dynamic_label = $label;
            $language = $languages[$matches[1]];
            if (!empty($language['name'])) {
                $dynamic_label .= " ({$language['name']})";
            }

            $fg_option = $params['wrapperOptions'];
            $input_options = array_merge(['class' => 'form-control', 'placeholder' => $dynamic_label . " 🖊"], $params['options']);
            if (!empty($language['rtl'])) {
                $input_options['dir'] = 'rtl';
                $input_options['placeholder'] = $dynamic_label . " ✏️";
                $fg_option['style'] = implode(' ', ["direction: rtl !important; text-align: right !important;", $fg_option['style'] ?? '']);
            }
            $output .= Html::beginTag('div', ['class' => $params['col']]);
            $output .= Html::beginTag('div', $fg_option);
            $output .= Html::label($dynamic_label, $key, ['class' => 'form-label']);
            $output .= Html::textInput($key, $value, $input_options);
            $output .= Html::endTag('div');
            $output .= Html::endTag('div');
        }
        return $this->makeLine($dashed_ml . $label . $dashed_ml) . $output;
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
}
