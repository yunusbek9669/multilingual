<?php

use yii\widgets\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $table array */
/* @var $form ActiveForm */
$this->title = Yii::t('multilingual', 'Edit translations');
$this->params['breadcrumbs'][] = ['label' => Yii::t('multilingual', 'All i18n'), 'url' => ['index', 'is_static' => 1]];
$this->params['breadcrumbs'][] = $this->title;

$table_name = key($table);
$values = json_decode($table[$table_name], true);
asort($values);
?>

    <div style="padding: 2rem 3rem; background-color: white">

        <div style="margin-bottom: 2rem; border-bottom: 2px solid #999; font-weight: bold; font-size: 18px; display: flex; justify-content: space-between"><?php echo $this->title ?><?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" viewBox="0 0 24 24"><g><path d="M0 0h24v24H0z" fill="none"/><path d="M8 7v4L2 6l6-5v4h5a8 8 0 1 1 0 16H4v-2h9a6 6 0 1 0 0-12H8z"/></g></svg> ' . Yii::t('multilingual', 'Back'), ['index', 'is_static' => 1], ['class' => 'btn btn-outline-danger font-weight-bolder', 'style' => 'margin: -.5em 0 .5em']) ?></div>

        <?php $form = ActiveForm::begin(); ?>

        <table class="ml-table">
            <thead>
            <tr>
                <th style="width: 40px">#</th>
                <th style="width: 47%"><?php echo reset(Yii::$app->params['default_language'])['name'] ?></th>
                <th><?php echo Yii::t('multilingual', 'translate') ?></th>
            </tr>
            </thead>
            <tbody class="ml-tbody">
            <?php $iteration = 1; foreach ($values as $key => $value): ?>
                <tr>
                    <td><?php echo $iteration++ ?></td>
                    <td style="color: #979aa6; font-style: italic;"><?php echo $key ?></td>
                    <td><input type="text" name="<?php echo $table_name.'['.$key.']' ?>" value="<?php echo $value ?>" class="ml-form-control <?php echo empty($value) ? 'ml-danger' : '' ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <?php echo Html::submitButton('<svg fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 30 30" xml:space="preserve"><path d="M22,4h-2v6c0,0.552-0.448,1-1,1h-9c-0.552,0-1-0.448-1-1V4H6C4.895,4,4,4.895,4,6v18c0,1.105,0.895,2,2,2h18  c1.105,0,2-0.895,2-2V8L22,4z M22,24H8v-6c0-1.105,0.895-2,2-2h10c1.105,0,2,0.895,2,2V24z"/><rect fill="currentColor" height="5" width="2" x="16" y="4"/></svg> ' . Yii::t('multilingual', 'Save'), ['class' => 'btn btn-outline-success font-weight-bolder']) ?>

        <?php ActiveForm::end(); ?>

    </div>
<?php
$css = <<<CSS
.ml-table {
    width: 100%;
    color: #495057;
    vertical-align: top;
    border-color: #eff2f7;
    caption-side: bottom;
    border-collapse: collapse;
}
.ml-table .ml-tbody:hover {
    background-color: #f8fafb;
}
.ml-table th, .ml-table td {
    padding: .2rem;
    vertical-align: middle;
    border-top: 1px solid #eff2f7;
    border-left: 1px solid #eff2f7;
    border-right: 1px solid #eff2f7;
}
.ml-table thead {
    vertical-align: bottom;
}
.ml-table thead th {
    position: sticky;
    top: -0.3px;
    background: #f1f1f1;
    z-index: 2;
    border-bottom: 2px solid #dee2e6;
}
thead, tbody, tfoot, tr, td, th {
    border-color: inherit;
    border-style: solid;
    border-width: 0;
}
.ml-danger {
    background-color: #fcb7b1;
}
.ml-not-translated {
    display: inline-block;
    padding: 0.15em 0.3em;
    font-size: 85%;
    font-weight: 500;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}
.ml-not-translated.has {
    background-color: #dc3545;
}
.ml-not-translated.not {
    background-color: #4bdc35;
}
.ml-form-control {
    display: block;
    width: 100%;
    padding: 0.2rem 0.3rem;
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    border-radius: 0.25rem;
    -webkit-transition: border-color 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
    transition: border-color 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
}
.ml-form-control:focus {
    outline: 0;
}
.ml-danger {
    background-color: #fcb7b182;
}
CSS;
$this->registerCss($css);
?>