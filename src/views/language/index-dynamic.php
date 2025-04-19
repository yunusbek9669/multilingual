<?php

use Yunusbek\Multilingual\models\BaseLanguageList;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $default_language array */
/* @var $searchParams array */
/* @var $translates array */

$this->title = Yii::t('multilingual', 'Translating column values in the database tables of the application');
$this->params['breadcrumbs'][] = $this->title;
?>
    <div class="ml-card">
        <div class="ml-card-body">
            <div class="ml-card-header">
                <?php echo $this->title ?>
            </div>
            <div class="ml-table-responsive">
                <table class="ml-table">
                    <thead>
                    <tr>
                        <th><?php echo Yii::t('multilingual', 'Action') ?></th>
                        <th><?php echo $translates['header']['table_name'] ?></th>
                        <th><?php echo $translates['header']['attributes'] ?></th>
                        <?php if (!empty($translates)): ?>
                            <?php foreach ($translates['header']['language'] as $key => $not_translated): ?>
                                <th><?php echo $key . ($default_language['name'] === $key ? ' <i class="fas fa-star text-warning"></i>' : ' <span class="ml-not-translated ' . ($not_translated > 0 ? 'has' : 'not') . '">' . $not_translated . '</span>') ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <?php foreach ($translates['body'] as $key => $row): $not_tran_count = 0; ?>
                        <tbody class="ml-tbody">
                        <tr>
                            <td rowspan="<?php echo 1 + count($row['translate']) ?>" style="width: 50px; text-align: center"><a href="<?php echo Url::to(['translate', 'table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration'], 'attributes' => array_keys($row['translate'])]) ?>" class="btn btn-<?php echo $row['is_full'] ? 'info' : 'warning' ?>"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg></a></td>
                            <td rowspan="<?php echo 1 + count($row['translate']) ?>" style="color: #979aa6; font-style: italic; font-weight: bold;">{<?php echo $row['table_name']; ?>}</td>
                        </tr>
                        <?php foreach ($row['translate'] as $attribute => $languages): ?>
                            <tr>
                                <td style="color: #979aa6; font-style: italic;"><?php echo $attribute; ?></td>
                                <?php foreach ($languages as $language): empty($language) ? [$not_tran_count++, $color = 'ml-danger'] : $color = '' ?>
                                    <td class="<?php echo $color ?>"><?php echo $language ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="<?php echo 3 + count($translates['header']['language']) ?>" style="padding: 0"></td>
                        </tr>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

<?php
$js = <<<JS
    const container = document.querySelector('.ml-table-responsive');
    let isDown = false;
    let startX;
    let scrollLeft;
    
    container.addEventListener('mousedown', (e) => {
      isDown = true;
      container.classList.add('active');
      container.style.cursor = 'grabbing';
      startX = e.pageX - container.offsetLeft;
      scrollLeft = container.scrollLeft;
    });
    
    container.addEventListener('mouseleave', () => {
      isDown = false;
      container.style.cursor = 'grab';
    });
    
    container.addEventListener('mouseup', () => {
      isDown = false;
      container.style.cursor = 'grab';
    });
    
    container.addEventListener('mousemove', (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - container.offsetLeft;
      const walk = (x - startX) * 1; // tezlik (1x)
      container.scrollLeft = scrollLeft - walk;
    });
JS;
$this->registerJs($js);

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
    font-size: 20px;
    font-weight: bold;
    padding-bottom: .5em;
    margin-bottom: 1em;
    border-bottom: 1px solid #ddd;
}
.ml-table-responsive {
    cursor: grab;
    user-select: none;
    display: block;
    width: 100%;
    overflow: auto;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}
.ml-table-responsive:active {
    cursor: grabbing;
}
.ml-table {
    min-width: 1200px;
    width: 100%;
    margin-bottom: 1rem;
    color: #495057;
    vertical-align: top;
    border-color: #eff2f7;
    caption-side: bottom;
    border-collapse: collapse;
    border: 1px solid #eff2f7;
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
CSS;
$this->registerCss($css);
?>