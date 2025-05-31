<?php

use Yunusbek\Multilingual\components\MlConstant;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $default_language array */
/* @var $searchParams array */
/* @var $translates array */

$this->title = Yii::t('multilingual', 'Translating column values in the database tables of the application');
$this->params['breadcrumbs'][] = $this->title;
$page = Yii::$app->request->get();
$per_page = $page['per-page'] ?? MlConstant::LIMIT;
?>
    <div class="ml-card">
        <div class="ml-card-body">
            <div class="ml-card-header">
                <div><?php echo $this->title ?></div>
                <div style="display: flex">
                    <?php echo Html::input('search', null, $per_page,
                        [
                            'class' => 'form-control px-1',
                            'style' => 'width: 60px',
                            'type' => 'number',
                            'max' => 5000,
                            'onblur' => 'updatePerPage(this.value)',
                            'onkeydown' => "if(event.key === 'Enter') updatePerPage(this.value)"
                        ]
                    ); ?>
                    <?php echo \yii\widgets\LinkPager::widget([
                        'options' => ['class' => 'justify-content-center pagination pagination-rounded mr-2 mb-0'],
                        'pagination' => $translates['pagination'],
                    ]); ?>
                    <?php echo Yii::$app->request->get('is_all') ? Html::a('<svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24" fill="grey"><path d="M21 21L3 3V6.33726C3 6.58185 3 6.70414 3.02763 6.81923C3.05213 6.92127 3.09253 7.01881 3.14736 7.10828C3.2092 7.2092 3.29568 7.29568 3.46863 7.46863L9.53137 13.5314C9.70432 13.7043 9.7908 13.7908 9.85264 13.8917C9.90747 13.9812 9.94787 14.0787 9.97237 14.1808C10 14.2959 10 14.4182 10 14.6627V21L14 17V14M8.60139 3H19.4C19.9601 3 20.2401 3 20.454 3.10899C20.6422 3.20487 20.7951 3.35785 20.891 3.54601C21 3.75992 21 4.03995 21 4.6V6.33726C21 6.58185 21 6.70414 20.9724 6.81923C20.9479 6.92127 20.9075 7.01881 20.8526 7.10828C20.7908 7.2092 20.7043 7.29568 20.5314 7.46863L16.8008 11.1992" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                        Url::current(['is_all' => null])) : Html::a('<svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24" fill="red"><path d="M3 4.6C3 4.03995 3 3.75992 3.10899 3.54601C3.20487 3.35785 3.35785 3.20487 3.54601 3.10899C3.75992 3 4.03995 3 4.6 3H19.4C19.9601 3 20.2401 3 20.454 3.10899C20.6422 3.20487 20.7951 3.35785 20.891 3.54601C21 3.75992 21 4.03995 21 4.6V6.33726C21 6.58185 21 6.70414 20.9724 6.81923C20.9479 6.92127 20.9075 7.01881 20.8526 7.10828C20.7908 7.2092 20.7043 7.29568 20.5314 7.46863L14.4686 13.5314C14.2957 13.7043 14.2092 13.7908 14.1474 13.8917C14.0925 13.9812 14.0521 14.0787 14.0276 14.1808C14 14.2959 14 14.4182 14 14.6627V17L10 21V14.6627C10 14.4182 10 14.2959 9.97237 14.1808C9.94787 14.0787 9.90747 13.9812 9.85264 13.8917C9.7908 13.7908 9.70432 13.7043 9.53137 13.5314L3.46863 7.46863C3.29568 7.29568 3.2092 7.2092 3.14736 7.10828C3.09253 7.01881 3.05213 6.92127 3.02763 6.81923C3 6.70414 3 6.58185 3 6.33726V4.6Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                        Url::current(['is_all' => 1])) ?>
                    <?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" style="margin-left: 15px" width="33" height="33" viewBox="0 0 32 32"><defs><linearGradient id="vscodeIconsFileTypeExcel0" x1="4.494" x2="13.832" y1="-2092.086" y2="-2075.914" gradientTransform="translate(0 2100)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#18884f"></stop><stop offset=".5" stop-color="#117e43"></stop><stop offset="1" stop-color="#0b6631"></stop></linearGradient></defs><path fill="#185c37" d="M19.581 15.35L8.512 13.4v14.409A1.192 1.192 0 0 0 9.705 29h19.1A1.192 1.192 0 0 0 30 27.809V22.5Z"></path><path fill="#21a366" d="M19.581 3H9.705a1.192 1.192 0 0 0-1.193 1.191V9.5L19.581 16l5.861 1.95L30 16V9.5Z"></path><path fill="#107c41" d="M8.512 9.5h11.069V16H8.512Z"></path><path d="M16.434 8.2H8.512v16.25h7.922a1.2 1.2 0 0 0 1.194-1.191V9.391A1.2 1.2 0 0 0 16.434 8.2Z" opacity=".1"></path><path d="M15.783 8.85H8.512V25.1h7.271a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path d="M15.783 8.85H8.512V23.8h7.271a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path d="M15.132 8.85h-6.62V23.8h6.62a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path fill="url(#vscodeIconsFileTypeExcel0)" d="M3.194 8.85h11.938a1.193 1.193 0 0 1 1.194 1.191v11.918a1.193 1.193 0 0 1-1.194 1.191H3.194A1.192 1.192 0 0 1 2 21.959V10.041A1.192 1.192 0 0 1 3.194 8.85Z"></path><path fill="#fff" d="m5.7 19.873l2.511-3.884l-2.3-3.862h1.847L9.013 14.6c.116.234.2.408.238.524h.017c.082-.188.169-.369.26-.546l1.342-2.447h1.7l-2.359 3.84l2.419 3.905h-1.809l-1.45-2.711A2.355 2.355 0 0 1 9.2 16.8h-.024a1.688 1.688 0 0 1-.168.351l-1.493 2.722Z"></path><path fill="#33c481" d="M28.806 3h-9.225v6.5H30V4.191A1.192 1.192 0 0 0 28.806 3Z"></path><path fill="#107c41" d="M19.581 16H30v6.5H19.581Z"></path></svg>', array_merge(['/multilingual/language/export-to-excel-default'], $searchParams)) ?>
                </div>
            </div>

            <div class="ml-table-responsive">
                <table class="ml-table">
                    <thead>
                    <tr>
                        <th style="width: 35px;">#</th>
                        <th><?php echo Yii::t('multilingual', 'Action') ?></th>
                        <th><?php echo $translates['header']['table_translated'] ?></th>
                        <th><?php echo $translates['header']['attributes'] ?></th>
                        <?php if (!empty($translates)): ?>
                            <?php foreach ($translates['header']['language'] as $key => $not_translated): ?>
                                <th><?php echo $key . ($default_language['name'] === $key ? ' '.MlConstant::STAR : ' <span class="ml-not-translated ' . ($not_translated > 0 ? 'has' : 'not') . '">' . $not_translated . '</span>') ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <?php if (!empty($translates['body'])): ?>
                        <?php foreach ($translates['body'] as $key => $row): ?>
                            <tbody class="ml-tbody">
                            <tr>
                                <td rowspan="<?php echo 1 + count($row['translate']) ?>"><?php echo $key ?></td>
                                <td rowspan="<?php echo 1 + count($row['translate']) ?>" class="ml-click-cell"><a href="<?php echo Url::to(['translate-dynamic', 'table_name' => $row['table_name'], 'table_iteration' => $row['table_iteration'], 'attributes' => array_keys($row['translate']), 'page' => json_encode($page)]) ?>" style="<?php echo $row['is_full'] ? '' : 'color: red' ?>"><svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg></a></td>
                                <td rowspan="<?php echo 1 + count($row['translate']) ?>" style="color: #979aa6; font-style: italic; font-weight: bold;"><?php echo $row['table_translated']; ?></td>
                            </tr>
                            <?php foreach ($row['translate'] as $attribute => $languages): $count = 0; ?>
                                <tr>
                                    <td style="color: #979aa6; font-style: italic;"><?php echo $attribute; ?></td>
                                    <?php foreach ($languages as $language): $count++; empty($language) && $count !== 1 ? $color = 'ml-danger' : $color = '' ?>
                                        <td class="<?php echo $color ?>"><?php echo $language ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="<?php echo 4 + count($translates['header']['language']) ?>" style="padding: 0"></td>
                            </tr>
                            </tbody>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tbody style="cursor: text; font-size: 14px">
                        <tr>
                            <td colspan="<?php echo 4 + count($translates['header']['language']) ?>"><?php echo $translates['empty'] ?></td>
                        </tr>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

<?php
$js = <<<JS
    function updatePerPage(value) {
        const perPage = parseInt(value);
        if (!isNaN(perPage) && perPage > 0 && perPage <= 5000) {
            const url = new URL(window.location.href);
            url.searchParams.set('per-page', perPage);
            window.location.href = url.toString();
        }
    }
    
    function copyTextFallback(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(() => alert('Nusxalandi!'))
                .catch(err => alert('Xatolik: ' + err));
        } else {
            copyTextFallback(text);
        }
    }
    
    const container = document.querySelector('.ml-table-responsive');
    let isDragging = false;
    let startX, startY;
    let scrollLeft, scrollTop;
    let velocityX = 0;
    let velocityY = 0;
    let lastX, lastY;
    let animationFrame;

    const startDrag = (x, y) => {
        isDragging = true;
        startX = x;
        startY = y;
        scrollLeft = container.scrollLeft;
        scrollTop = container.scrollTop;
        lastX = x;
        lastY = y;
        cancelAnimationFrame(animationFrame);
    };

    const stopDrag = () => {
        isDragging = false;
        inertiaScroll();
    };

    const dragMove = (x, y) => {
        if (!isDragging) return;
        const dx = x - startX;
        const dy = y - startY;
        velocityX = x - lastX;
        velocityY = y - lastY;
        lastX = x;
        lastY = y;
        container.scrollLeft = scrollLeft - dx;
        container.scrollTop = scrollTop - dy;
    };

    const inertiaScroll = () => {
        velocityX *= 0.95;
        velocityY *= 0.95;
        container.scrollLeft -= velocityX;
        container.scrollTop -= velocityY;
        if (Math.abs(velocityX) > 0.5 || Math.abs(velocityY) > 0.5) {
            animationFrame = requestAnimationFrame(inertiaScroll);
        }
    };

    // Mouse Events
    container.addEventListener('mousedown', (e) => {
        container.classList.add('dragging');
        startDrag(e.clientX, e.clientY);
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        dragMove(e.clientX, e.clientY);
    });

    document.addEventListener('mouseup', () => {
        container.classList.remove('dragging');
        stopDrag();
    });

    // Touch Events
    container.addEventListener('touchstart', (e) => {
        const touch = e.touches[0];
        startDrag(touch.clientX, touch.clientY);
    });

    container.addEventListener('touchmove', (e) => {
        const touch = e.touches[0];
        dragMove(touch.clientX, touch.clientY);
    });

    container.addEventListener('touchend', () => {
        stopDrag();
    });
JS;
$this->registerJs($js, \yii\web\View::POS_END);

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
    cursor: grab;
    user-select: none;
    display: block;
    width: 100%;
    height: calc(90vh - 120px);
    overflow: auto;
    position: relative;
    -webkit-overflow-scrolling: auto;
}
.ml-table-responsive:active {
    cursor: grabbing;
}
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
    background-color: #fcb7b182;
}
.ml-warning {
    background-color: rgba(255,210,77,0.51);
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
CSS;
$this->registerCss($css);
?>