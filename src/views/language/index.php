<?php

use Yunusbek\Multilingual\models\BaseLanguageList;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchParams array */
/* @var $translates array */

$this->title = Yii::t('multilingual', 'Translates');
$this->params['breadcrumbs'][] = $this->title;
$languages = Yii::$app->params['language_list'];
$default_language = current(array_filter($languages, fn($lang) => empty($lang['table'])));
?>
    <div class="card card-custom manuals-language-index">
        <div class="card-body">
            <div class="card-header bg-white d-flex justify-content-between">
            <span class="font-size-20 font-weight-bolder text-uppercase">
                <?php echo  $this->title ?>
            </span>
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
                                <th><?php echo $key.($default_language['name'] === $key ? ' <i class="fas fa-star text-warning"></i>' : '') ?> <span class="badge badge-danger"><?php echo $not_translated ?></span></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th><?php echo Yii::t('multilingual', 'Action') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($translates['body'] as $key => $row): $not_tran_count = 0; ?>
                        <tr>
                            <td rowspan="<?php echo 1+count($row['translate']) ?>"><span class="badge badge-success"><?php echo $row['table_name']; ?></span></td>
                            <td colspan="<?php echo 1+count($translates['header']['language']) ?>" style="padding: 0; border: 0; height: 0"></td>
                            <td rowspan="<?php echo 1+count($row['translate']) ?>" style="width: 50px; text-align: center"><a href="<?php echo Url::to(['translate', 'table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration'], 'attributes' => array_keys($row['translate'])]) ?>" class="btn btn-<?php echo $row['is_full'] ? 'info' : 'warning' ?> open_sm_modal" data-row="<?php echo count($translates['header']['language'])*count($row['translate']) ?>"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg></a></td>
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