<?php

use yii\widgets\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model ActiveRecord */
/* @var $attributes array */
/* @var $form ActiveForm */

$this->title = Yii::t('multilingual', 'Edit translations');
$this->params['breadcrumbs'][] = ['label' => Yii::t('multilingual', 'All columns'), 'url' => ['index', 'is_static' => 0]];
$this->params['breadcrumbs'][] = $this->title;
?>

<div style="padding: 2rem 3rem; background-color: white">

    <div style="margin-bottom: 2rem; border-bottom: 2px solid #999; font-weight: bold; font-size: 18px; display: flex; justify-content: space-between"><?php echo $this->title ?><?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" viewBox="0 0 24 24"><g><path d="M0 0h24v24H0z" fill="none"/><path d="M8 7v4L2 6l6-5v4h5a8 8 0 1 1 0 16H4v-2h9a6 6 0 1 0 0-12H8z"/></g></svg> ' . Yii::t('multilingual', 'Back'), ['index', 'is_static' => 0], ['class' => 'btn btn-outline-danger font-weight-bolder', 'style' => 'margin: -.5em 0 .5em']) ?></div>

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <?php echo \Yunusbek\Multilingual\widgets\MultilingualAttributes::widget([
            'form' => $form,
            'model' => $model,
            'attribute' => $attributes,
        ]) ?>
    </div>

    <?php echo Html::submitButton('<svg fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 30 30" xml:space="preserve"><path d="M22,4h-2v6c0,0.552-0.448,1-1,1h-9c-0.552,0-1-0.448-1-1V4H6C4.895,4,4,4.895,4,6v18c0,1.105,0.895,2,2,2h18  c1.105,0,2-0.895,2-2V8L22,4z M22,24H8v-6c0-1.105,0.895-2,2-2h10c1.105,0,2,0.895,2,2V24z"/><rect fill="currentColor" height="5" width="2" x="16" y="4"/></svg> ' . Yii::t('multilingual', 'Save'), ['class' => 'btn btn-outline-success font-weight-bolder']) ?>

    <?php ActiveForm::end(); ?>

</div>