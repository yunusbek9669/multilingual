<?php

namespace Yunusbek\Multilingual\widgets;

use Yii;
use Exception;
use yii\base\Model;
use yii\base\Widget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Yunusbek\Multilingual\models\LanguageService;

class SetLanguageAttributes extends Widget
{
    /**
     * @var ActiveForm the ActiveForm instance
     */
    public ActiveForm $form;

    /**
     * @var Model the model associated with this widget
     */
    public Model $model;

    public string $attribute;

    public int|null $col;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run()
    {
        $form = $this->form;

        $model = $this->model;

        $attribute = $this->attribute;

        $col = $this->col ?? 12;

        $tableSchema = $model->getTableSchema();
        $columnType = $tableSchema->columns[$attribute]->type;
        if ($columnType !== 'string') {
            throw new Exception('The value of "attribute" must be of type string.');
        }


        $defaultValue = (new yii\db\Query())
            ->from($model::tableName())
            ->select($attribute)
            ->where([
                'id' => $model->id,
            ])
            ->scalar();
        $languages = Yii::$app->params['language_list'];
        $defaultLanguage = null;
        foreach ($languages as $lang) {
            if ($lang['table'] === null) { $defaultLanguage = $lang; break; }
        }
        $label = $model->getAttributeLabel($attribute);
        $defaultLabel = $label.' ('.$defaultLanguage['name'].')';
        $output = '<div class="col-'.$col.'">'.$form->field($model, $attribute)->textInput(['placeholder' => $defaultLabel." 🖊", 'value' => $defaultValue ?? ''])->label($defaultLabel).'</div>';
        foreach (LanguageService::setCustomAttributes($model, $attribute) as $key => $value)
        {
            preg_match('/lang_(\w+)/', $key, $matches);
            $dynamic_label = $label;
            $language = $languages[$matches[1]];
            if (!empty($language['name'])) {
                $dynamic_label .= ' ('.$language['name'].')';
            }
            $output .= '<div class="col-'.$col.'"><div class="form-group highlight-addon">';
            $output .= Html::label($dynamic_label, $key, ['class' => 'form-label']);
            $output .= Html::textInput($key, $value, [
                'name' => $key,
                'placeholder' => $dynamic_label . " 🖊",
                'class' => 'form-control',
            ]);
            $output .= '</div></div>';
        }

        return $output;
    }
}
