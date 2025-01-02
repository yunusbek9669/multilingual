<?php

use Yunusbek\Multilingual\models\BaseLanguageList;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchParams array */
/* @var $translates array */

$this->title = Yii::t('app', 'Translates');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-none">
    <?php echo GridView::widget([
        'dataProvider' => new ActiveDataProvider([
            'query' => $query = BaseLanguageList::find()
                ->where(['status' => 0])
                ->orderBy(['order_number' => SORT_ASC]),
        ])
    ]); ?>
</div>
<div class="card card-custom manuals-language-index">
    <div class="card-body">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="font-size-20 font-weight-bolder text-uppercase">
                <?php echo  $this->title ?>
            </span>
            <div>
                <a href="<?php echo Url::to(['/common-language/language/export-to-excel', 'is_all' => true]) ?>" class="btn btn-lg btn-primary font-weight-bolder mb-3"  data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo Yii::t('app','Asosiy tildan export qilish')?>">
                    <i class="fa fa-cog"></i>
                    <?php echo Yii::t('app', 'Export') ?>
                </a>
            </div>
        </div>
        <hr>
        <div class="table-responsive">
            <table class="table table-bordered table-xs">
                <thead>
                    <tr>
                        <th><?php echo $translates['header']['table_name'] ?></th>
                        <th><?php echo $translates['header']['attributes'] ?></th>
                        <?php if (!empty($translates)): ?>
                            <?php foreach ($translates['header']['language'] as $key => $not_translated): ?>
                                <th><?php echo $key ?> <span class="badge badge-danger"><?php echo $not_translated ?></span></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th><?php echo Yii::t('app', 'Action') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($translates['body'] as $key => $row): $not_tran_count = 0; ?>
                    <tr>
                        <td rowspan="<?php echo 1+count($row['translate']) ?>"><span class="badge badge-success"><?php echo $row['table_name']; ?></span></td>
                        <td colspan="<?php echo 1+count($translates['header']['language']) ?>" style="padding: 0; border: 0; height: 0"></td>
                        <td rowspan="<?php echo 1+count($row['translate']) ?>" style="width: 50px; text-align: center"><a href="<?php echo Url::to(['translate', 'table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration'], 'attributes' => array_keys($row['translate'])]) ?>" class="btn btn-<?php echo $row['is_full'] ? 'info' : 'warning' ?> open_sm_modal" data-row="<?php echo count($translates['header']['language'])*count($row['translate']) ?>"><i class="fas fa-cog"></i></a></td>
                    </tr>
                    <?php foreach ($row['translate'] as $attribute => $languages): ?>
                        <tr>
                            <td class="font-italic text-muted"><?php echo $attribute; ?></td>
                            <?php foreach ($languages as $language): empty($language) ? [$not_tran_count++,$color = 'bg-danger'] : $color = '' ?>
                                <td class="bg-soft <?php echo $color ?>">
                                    <?php echo $language ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
$js = <<<JS
JS;
$this->registerJs($js);
?>