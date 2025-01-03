<?php

namespace Yunusbek\Multilingual\widgets;

use Yii;
use Exception;
use yii\base\Widget;
use yii\helpers\Html;
use yii\db\ActiveRecord;
use Yunusbek\Multilingual\models\LanguageService;

class MultilingualAttributes extends Widget
{
    /**
     * @var ActiveRecord the model associated with this widget
     */
    public ActiveRecord $model;

    public string|array $attribute;

    public int|null $col;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run()
    {
        $model = $this->model;

        $attribute = $this->attribute;

        $col = $this->col ?? 12;

        $tableSchema = $model->getTableSchema();

        $params = [
            'tableSchema' => $tableSchema,
            'model' => $model,
            'col' => $col
        ];

        if (is_array($attribute)) {
            $result = '';
            foreach ($attribute as $attr) {
                $params['attribute'] = $attr;
                $result .= $this->setAttribute($params);
            }
        } else {
            $params['attribute'] = $attribute;
            $result = $this->setAttribute($params);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function setAttribute(array $params): string
    {
        /** @var ActiveRecord $model */
        $model = $params['model'];
        $attribute = $params['attribute'];
        $columnType = $params['tableSchema']->columns[$attribute]->type;
        if ($columnType !== 'string') {
            throw new Exception('The value of attribute - "'.$attribute.'" must be of type string.');
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
        $modelName = basename(get_class($model));
        $defaultLabel = $label.' ('.$defaultLanguage['name'].')';
        $options = ['class' => 'form-control', 'placeholder' => $defaultLabel." 🖊", 'data-validation' => json_encode($model->getActiveValidators($attribute))];
        if (!empty($defaultValue)) {
            $options = array_merge($options, ['value' => $defaultValue]);
        }
        $output = '<div class="col-'.$params['col'].'">'.
                '<div class="form-group highlight-addon field-'.strtolower($modelName).'-'.$attribute.' required">'.
                    '<label class="form-label has-star" for="'.Html::getInputId($model, $attribute).'">'.$label.'</label>'.
                    Html::activeTextInput($model, $attribute, $options).
                    '<div class="help-block"></div>'.
                '</div>'.
            '</div>';
        foreach (LanguageService::setCustomAttributes($model, $attribute) as $key => $value)
        {
            preg_match('/lang_(\w+)/', $key, $matches);
            $dynamic_label = $label;
            $language = $languages[$matches[1]];
            if (!empty($language['name'])) {
                $dynamic_label .= ' ('.$language['name'].')';
            }
            $output .= '<div class="col-'.$params['col'].'"><div class="form-group highlight-addon">';
            $output .= Html::label($dynamic_label, $key, ['class' => 'form-label']);
            $output .= Html::textInput($key, $value, [
                'placeholder' => $dynamic_label . " 🖊",
                'class' => 'form-control',
            ]);
            $output .= '</div></div>';
        }

        return $output;
    }
}
