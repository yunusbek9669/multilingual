<?php

namespace Yunusbek\Multilingual\widgets;

use Exception;
use Yii;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Yunusbek\Multilingual\components\LanguageService;

class MultilingualAttributes extends Widget
{
    public ActiveForm $form;

    public ActiveRecord $model;
    public string $table_name;

    public string|array $attribute;

    public int|null $col;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function run(): string
    {
        $form = $this->form;

        $model = $this->model;

        $attribute = $this->attribute;

        $col = $this->col ?? 12;

        $tableSchema = $model->getTableSchema();

        $params = [
            'tableSchema' => $tableSchema,
            'model' => $model,
            'form' => $form,
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
        $form = $params['form'];
        $model = $params['model'];
        $attribute = $params['attribute'];
        $columnType = $params['tableSchema']->columns[$attribute]->type;
        if (!in_array($columnType, ['string', 'text'])) {
            throw new Exception('The value of attribute - "'.$attribute.'" must be of type string.');
        }
        if (!in_array('id', array_keys($model->getAttributes()))) {
            throw new Exception('The "'.$model::tableName().'" table does not have an id column.');
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
            if (empty($lang['table'])) { $defaultLanguage = $lang; break; }
        }
        $label = $model->getAttributeLabel($attribute);
        $defaultLabel = $label.' ('.$defaultLanguage['name'].')';
        $output = '<div class="col-'.$params['col'].'">'.$form->field($model, $attribute)->textInput(['placeholder' => $defaultLabel." 🖊", 'value' => $defaultValue])->label($defaultLabel.' <i class="fas fa-star text-warning"></i>').'</div>';
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
