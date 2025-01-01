<?php

use yii\widgets\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model ActiveRecord */
/* @var $attributes array */
/* @var $form ActiveForm */
$this->title = Yii::t('app', 'Tarjimalarni tahrirlash')
?>

<div class="reference-gender-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <?php foreach ($attributes as $attribute): ?>
            <?php echo \Yunusbek\Multilingual\widgets\SetLanguageAttributes::widget([
                'form' => $form,
                'model' => $model,
                'attribute' => $attribute,
            ]) ?>
        <?php endforeach; ?>
    </div>
    <hr class="my-4">
    <div class="form-group">
        <?php echo Html::submitButton('<i class="fas fa-save"></i> '.Yii::t('app', 'Save'), ['class' => 'btn btn-lg btn-block btn-outline-primary shadow-lg shadow-primary font-weight-bolder text-uppercase waves-effect']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$js = <<<JS
$('.modal-header').html("<button type='button' class='close' data-dismiss='modal'><span aria-hidden='true'>×</span></button><h4 style='margin-bottom:-3px'>{$this->title}</h4>");
JS;
$this->registerJs($js);
?> 