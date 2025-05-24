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

class MultilingualAttributes extends Widget
{
    public ActiveForm $form;

    public ActiveRecord $model;
    public string $table_name;

    public string|array $attribute;

    public int|null $col;

    private array $params;

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        $model = $this->model;

        $tableSchema = $model->getTableSchema();

        if (!in_array('id', array_keys($model->getAttributes()))) {
            throw new InvalidConfigException("The {{$model::tableName()}} table does not have an id column.");
        }

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
        $defaultLabel = $label . ' (' . $defaultLanguage['name'] . ')';

        $output = Html::tag('div',
            $form->field($model, $params['attribute'])
                ->textInput(['placeholder' => $defaultLabel . " 🖊", 'value' => $defaultValue])
                ->label($defaultLabel . ' '.MlConstant::STAR),
            ['class' => $params['col']]
        );
        foreach (LanguageService::setCustomAttributes($model, $params['attribute']) as $key => $value)
        {
            preg_match('/lang_(\w+)/', $key, $matches);
            $dynamic_label = $label;
            $language = $languages[$matches[1]];
            if (!empty($language['name'])) {
                $dynamic_label .= ' (' . $language['name'] . ')';
            }

            $fg_option = ['class' => 'form-group highlight-addon'];
            $input_options = ['class' => 'form-control', 'placeholder' => $dynamic_label . " 🖊"];
            if (!empty($language['rtl'])) {
                $input_options['dir'] = 'rtl';
                $input_options['placeholder'] = $dynamic_label . " ✏️";
                $fg_option['style'] = "direction: rtl !important; text-align: right !important;";
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
