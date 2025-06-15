<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $translates array */

$this->title = Yii::t('multilingual', 'Translating static (i18n) messages in the application');
$this->params['breadcrumbs'][] = $this->title;
?>
    <div class="ml-card">
        <div class="ml-card-body">
            <div class="ml-card-header">
                <div><?php echo $this->title ?></div>
                <div style="display: flex">
                    <?php echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" style="margin-left: 20px" width="33" height="33" viewBox="0 0 32 32"><defs><linearGradient id="vscodeIconsFileTypeExcel0" x1="4.494" x2="13.832" y1="-2092.086" y2="-2075.914" gradientTransform="translate(0 2100)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#18884f"></stop><stop offset=".5" stop-color="#117e43"></stop><stop offset="1" stop-color="#0b6631"></stop></linearGradient></defs><path fill="#185c37" d="M19.581 15.35L8.512 13.4v14.409A1.192 1.192 0 0 0 9.705 29h19.1A1.192 1.192 0 0 0 30 27.809V22.5Z"></path><path fill="#21a366" d="M19.581 3H9.705a1.192 1.192 0 0 0-1.193 1.191V9.5L19.581 16l5.861 1.95L30 16V9.5Z"></path><path fill="#107c41" d="M8.512 9.5h11.069V16H8.512Z"></path><path d="M16.434 8.2H8.512v16.25h7.922a1.2 1.2 0 0 0 1.194-1.191V9.391A1.2 1.2 0 0 0 16.434 8.2Z" opacity=".1"></path><path d="M15.783 8.85H8.512V25.1h7.271a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path d="M15.783 8.85H8.512V23.8h7.271a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path d="M15.132 8.85h-6.62V23.8h6.62a1.2 1.2 0 0 0 1.194-1.191V10.041a1.2 1.2 0 0 0-1.194-1.191Z" opacity=".2"></path><path fill="url(#vscodeIconsFileTypeExcel0)" d="M3.194 8.85h11.938a1.193 1.193 0 0 1 1.194 1.191v11.918a1.193 1.193 0 0 1-1.194 1.191H3.194A1.192 1.192 0 0 1 2 21.959V10.041A1.192 1.192 0 0 1 3.194 8.85Z"></path><path fill="#fff" d="m5.7 19.873l2.511-3.884l-2.3-3.862h1.847L9.013 14.6c.116.234.2.408.238.524h.017c.082-.188.169-.369.26-.546l1.342-2.447h1.7l-2.359 3.84l2.419 3.905h-1.809l-1.45-2.711A2.355 2.355 0 0 1 9.2 16.8h-.024a1.688 1.688 0 0 1-.168.351l-1.493 2.722Z"></path><path fill="#33c481" d="M28.806 3h-9.225v6.5H30V4.191A1.192 1.192 0 0 0 28.806 3Z"></path><path fill="#107c41" d="M19.581 16H30v6.5H19.581Z"></path></svg>', ['/multilingual/language/export-to-excel-default', 'is_static' => true]) ?>
                </div>
            </div>
            <div class="ml-table-responsive">
                <table class="ml-table">
                    <thead>
                    <tr>
                        <th><?php echo $translates['header']['languages'] ?></th>
                        <th colspan="<?php echo count(reset($translates['body'])) ?>"><?php echo $translates['header']['categories'] ?></th>
                    </tr>
                    </thead>
                    <?php foreach ($translates['body'] as $language => $categories): ?>
                        <tbody class="ml-tbody">
                        <tr>
                            <td style="color: #979aa6; font-style: italic; font-weight: bold"><?php echo $language ?></td>
                            <?php foreach ($categories as $key => $category): ?>
                                <td class="ml-category" style="font-weight: bold"><?php echo $category ?></td>
                            <?php endforeach; ?>
                        </tr>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

<?php
$js = <<<JS
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
    user-select: none;
    display: block;
    width: 100%;
    height: calc(90vh - 100px);
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
CSS;
$this->registerCss($css);
?>