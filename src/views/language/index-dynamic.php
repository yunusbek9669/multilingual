<?php

use Yunusbek\Multilingual\components\helpers\MlHelper;
use Yunusbek\Multilingual\components\MlConstant;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $languages array */
/* @var $tables array */

$this->title = Yii::t('multilingual', 'Translating column values in the database tables of the application');
$this->params['breadcrumbs'][] = ['label' => $this->title, 'url' => '#'];

?>
    <div class="ml-card">
        <div class="ml-card-body">
            <div class="ml-card-header">
                <div><?php echo $this->title ?></div>
            </div>

            <div class="ml-table-responsive">
                <table class="ml-table">
                    <thead>
                    <tr>
                        <th style="width: 35px;">#</th>
                        <th><?php echo Yii::t('multilingual', 'Action') ?></th>
                        <th><?php echo Yii::t('multilingual', 'Table List') ?></th>
                        <?php if (!empty($languages)): ?>
                            <?php foreach ($languages as $langugae): ?>
                                <th style="text-align: center"><?php echo $langugae['name'] . (!isset($langugae['table']) ? ' '.MlConstant::STAR : '') ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <?php if (!empty($tables)): ?>
                        <tbody class="ml-tbody">
                        <?php foreach ($tables as $key => $table): ?>
                            <tr>
                                <td><?php echo $key + 1 ?></td>
                                <td class="ml-click-cell"><a href="<?php echo Url::to(['index-table', 'table_name' => $table['table_name']]) ?>"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1.125em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M573 241C518 136 411 64 288 64S58 136 3 241a32 32 0 000 30c55 105 162 177 285 177s230-72 285-177a32 32 0 000-30zM288 400a144 144 0 11144-144 144 144 0 01-144 144zm0-240a95 95 0 00-25 4 48 48 0 01-67 67 96 96 0 1092-71z"></path></svg></a></td>
                                <td style="font-weight: bold;">
                                    <?php echo MlHelper::tableTextFormat($table['table_name'], true) ?>
                                    <br>
                                    <span style="color: #979aa6; font-style: italic;"><?php echo $table['table_name'] ?></span>
                                </td>
                                <?php foreach ($table['count_list'] as $count_key => $count): ?>
                                    <td style="text-align: center"><span class="<?php echo $count_key === 'default_count' ? '' : ($count !== 0 ? 'ml-not-translated has' : 'ml-not-translated not'); ?>"><?php echo $count; ?></span></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    <?php else: ?>
                        <tbody style="cursor: text; font-size: 14px">
                        <tr>
                            <td colspan="<?php echo 3 + count($languages) ?>"><?php echo Yii::t('multilingual', 'Translation tables does\'n exist.') ?></td>
                        </tr>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

<?php
$css = <<<CSS
.ml-card {
    position: relative;
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-box-orient: vertical;
    -webkit-box-direction: normal;
    -ms-flex-direction: column;
    flex-direction: column;
    min-width: 0;
    word-wrap: break-word;
    background-color: #fff;
    background-clip: border-box;
    border: 0 solid #f6f6f6;
}
.ml-card-body {
    -webkit-box-flex: 1;
    -ms-flex: 1 1 auto;
    flex: 1 1 auto;
    padding: 1.25rem 1.25rem;
}
.ml-card-header {
    display: flex;
    justify-content: space-between;
    font-size: 20px;
    font-weight: bold;
    padding-bottom: .5em;
    margin-bottom: 1em;
    border-bottom: 1px solid #ddd;
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
.ml-click-cell {
    width: 50px;
    text-align: center;
    padding: 0;
    position: relative;
}
.ml-click-cell a {
    cursor: pointer;
    display: block;
    height: 100%;
    width: 100%;
    position: absolute;
    top: 0;
    left: 0;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;
$this->registerCss($css);
?>