<?php

use yii\widgets\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $table array */
/* @var $table_name string */
/* @var $translating_language string */
/* @var $category string */
/* @var $form ActiveForm */

$this->title = Yii::t('multilingual', 'Edit {{category}} category from i18n', ['category' => $category]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('multilingual', 'Translating static (i18n) messages in the application'), 'url' => ['index', 'is_static' => 1]];
$this->params['breadcrumbs'][] = $this->title;

$is_all = Yii::$app->request->get('is_all', 0);
$page = Yii::$app->request->get('page', 0);
$page_countable = $table['total'] === 0 || count($table[$table_name]) < 1000;
?>

    <div style="padding: 2rem 3rem; background-color: white">

        <?php $form = ActiveForm::begin(); ?>
        <div style="margin-bottom: 1rem; border-bottom: 2px solid #999; font-weight: bold; font-size: 18px; display: flex; justify-content: space-between">
            <?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" viewBox="0 0 24 24"><g><path d="M0 0h24v24H0z" fill="none"/><path d="M8 7v4L2 6l6-5v4h5a8 8 0 1 1 0 16H4v-2h9a6 6 0 1 0 0-12H8z"/></g></svg> ' . Yii::t('multilingual', 'Back'), ['index', 'is_static' => 1], ['class' => 'btn btn-outline-danger font-weight-bolder', 'style' => 'margin: -.5em 0 .5em']) ?>
            <?php echo $this->title ?>
            <div>
                <div style="display: flex">
                    <div class="ml-btn-group" style="margin: -.3em 30px .3em 0">
                        <?php echo Html::a('<svg xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24" fill="none"><path d="M18 7L10 12L18 17V7Z" fill="#000000"/><path d="M6 7H9V12V17H6V7Z" fill="#000000"/></svg>',
                            ['translate-static', 'lang' => $table_name, 'category' => $category, 'page' => 0], ['class' => 'ml-btn '.($page == 0 ? 'disabled' : ''), 'style' => 'width: 45px']) ?>
                        <?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000" height="30px" width="30px" version="1.1" viewBox="20 0 512 512" enable-background="new 0 0 512 512" xml:space="preserve"><path d="M512,381.4l-237.7-118.9L512,143.6V381.4z M274.3,262.5v118.9l-237.7-118.9L274.3,143.6V262.5z"/></svg>',
                            ['translate-static', 'lang' => $table_name, 'category' => $category, 'page' => $page - 1], ['class' => 'ml-btn '.($page == 0 ? 'disabled' : ''), 'style' => 'width: 45px']) ?>
                        <?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000" height="30px" width="30px" version="1.1" viewBox="-20 0 512 512" enable-background="new 0 0 512 512" xml:space="preserve"><path d="M0,381.4l237.7-118.9L0,143.6V381.4z M237.7,262.5v118.9l237.7-118.9L237.7,143.6V262.5z"/></svg>',
                            ['translate-static', 'lang' => $table_name, 'category' => $category, 'page' => $page + 1], ['class' => 'ml-btn '.($page_countable ? 'disabled' : ''), 'style' => 'width: 45px']) ?>
                        <?php echo Html::a('<svg xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24" fill="none"><path d="M6 17L14 12L6 7V17Z" fill="#000000"/><path d="M18 7H15V12V17H18V7Z" fill="#000000"/></svg>',
                            ['translate-static', 'lang' => $table_name, 'category' => $category, 'page' => $table['total']], ['class' => 'ml-btn '.($page_countable ? 'disabled' : ''), 'style' => 'width: 45px']) ?>
                    </div>
                    <?php echo Html::submitButton('<svg fill="currentColor" aria-hidden="true" style="display: inline-block; font-size: inherit; height: 1.3em; overflow: visible; vertical-align: -.25em; width: 1.3em" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 30 30" xml:space="preserve"><path d="M22,4h-2v6c0,0.552-0.448,1-1,1h-9c-0.552,0-1-0.448-1-1V4H6C4.895,4,4,4.895,4,6v18c0,1.105,0.895,2,2,2h18  c1.105,0,2-0.895,2-2V8L22,4z M22,24H8v-6c0-1.105,0.895-2,2-2h10c1.105,0,2,0.895,2,2V24z"/><rect fill="currentColor" height="5" width="2" x="16" y="4"/></svg> ' . Yii::t('multilingual', 'Save'), ['class' => 'btn btn-outline-success font-weight-bolder', 'style' => 'margin: -.5em 0 .5em']) ?>
                </div>
            </div>
        </div>

        <div class="ml-table-responsive">
            <table class="ml-table">
                <thead>
                <tr>
                    <th style="width: 40px">#</th>
                    <th style="width: 47%"><?php echo reset(Yii::$app->params['default_language'])['name'] ?></th>
                    <th><?php echo $translating_language ?></th>
                </tr>
                </thead>
                <tbody class="ml-tbody">
                <?php $iteration = 1; foreach ($table[$table_name] as $key => $value): ?>
                    <tr>
                        <td><?php echo $iteration++ ?></td>
                        <td style="font-style: italic; white-space: pre-wrap;"><span style="background-color: rgba(105,255,0,0.31); color: rgb(96,96,96);"><?php echo $key ?></span></td>
                        <td><input type="text" name="<?php echo $table_name.'['.$key.']' ?>" value="<?php echo $value ?>" class="ml-form-control <?php echo empty($value) ? 'ml-danger' : '' ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php ActiveForm::end(); ?>

    </div>
<?php
$css = <<<CSS

.ml-btn {
    display: inline-block;
    font-weight: 400;
    line-height: 1.5;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    color: #212529;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    background-color: transparent;
    border: 1px solid #eff2f7;
    font-size: 0.8125rem;
    border-radius: 0.25rem;
    -webkit-transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, -webkit-box-shadow 0.15s ease-in-out;
}
.ml-btn:hover {
    color: #000;
    background-color: #eff2f7;
    border-color: #eff2f7;
}
.ml-btn-group {
    display: flex;
    flex-wrap: nowrap;
    justify-content: center;
}

.ml-btn-group .ml-btn {
    border-radius: 0;
    border-radius: 0;
}
.ml-btn-group .ml-btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}
.ml-btn-group .ml-btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}
.ml-table-responsive {
    display: block;
    width: 100%;
    height: calc(90vh - 120px);
    overflow: auto;
    position: relative;
    -webkit-overflow-scrolling: auto;
}
.ml-table {
    width: 100%;
    color: #495057;
    vertical-align: top;
    border-color: #eff2f7;
    caption-side: bottom;
    background-color: rgba(248,250,251,0.54);
    border-collapse: collapse;
}
.ml-table .ml-tbody tr:hover {
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
    padding: 2px 0;
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 0.5px solid rgba(206,212,218,0.62);
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
.disabled {
    pointer-events: none;
    background: #f1f1f1;
    color: grey;
}
.disabled svg {
    opacity: 0.5;
}
CSS;
$this->registerCss($css);
?>