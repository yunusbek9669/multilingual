<?php

namespace Yunusbek\Multilingual\controllers;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Controller;
use yii\web\Response;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\models\BaseLanguageList;
use Yunusbek\Multilingual\models\Multilingual;

/**
 * LanguageController implements the CRUD actions for Multilingual model.
 */
class LanguageController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all BaseLanguageList models.
     * @param int $is_static
     * @return string
     * @throws Exception
     */
    public function actionIndex(int $is_static): string
    {
        $searchParams = Yii::$app->request->queryParams;
        if ((int)$searchParams['is_static'] === 1) {
            return $this->render('index-static', [
                'translates' => LanguageService::getI18NData($searchParams),
                'searchParams' => $searchParams,
            ]);
        } else {
            return $this->render('index-dynamic', [
                'default_language' => current(array_filter(Yii::$app->params['language_list'], fn($lang) => empty($lang['table']))),
                'translates' => LanguageService::getModelsData($searchParams),
                'searchParams' => $searchParams,
            ]);
        }
    }

    /**
     * Updates an existing BaseLanguageList model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $table_name
     * @param integer $table_iteration
     * @param array $attributes
     * @return Response|array|string
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionTranslateDynamic(string $table_name, int $table_iteration, array $attributes): Response|array|string
    {
        $model = $this->findModel($table_name, $table_iteration);
        $request = Yii::$app->request;
        if ($request->isPost) {
            $response = [
                'status' => true,
                'code' => 'error',
                'message' => Yii::t('multilingual', 'Error')
            ];
            $Multilingual = $request->post((new \ReflectionClass($model))->getShortName());
            if ($model->load($request->post())) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    foreach ($Multilingual as $attribute => $value) {
                        $model->$attribute = $value;
                    }
                    if (!$model->save()) {
                        $response['status'] = false;
                        $response['message'] = $model->getErrors();
                    }
                } catch (\Exception $e) {
                    $response['message'] = $e->getMessage();
                    $response['errors'] = $e->getTrace();
                    $response['status'] = false;
                }
                if ($response['status']) {
                    $response['status'] = true;
                    $response['code'] = 'success';
                    $response['message'] = Yii::t('multilingual', 'Saved Successfully');
                    $transaction->commit();
                } else {
                    $response['code'] = 'error';
                    $transaction->rollBack();
                }
                if (Yii::$app->request->isAjax) {
                    return $response;
                }
                Yii::$app->session->setFlash($response['code'], $response['message']);
                return $this->redirect(['index', 'is_static' => 0]);
            }
        }

        return $this->render('_form-dynamic', [
            'attributes' => $attributes,
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing BaseLanguageList model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $lang
     * @param string $category
     * @return Response|array|string
     * @throws Exception
     */
    public function actionTranslateStatic(string $lang, string $category): Response|array|string
    {
        $request = Yii::$app->request;
        if ($request->isPost) {
            $response = [
                'status' => true,
                'code' => 'error',
                'message' => Yii::t('multilingual', 'Error')
            ];
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $response = Multilingual::setStaticLanguageValue($lang, $category, $request->post($lang));
            } catch (\Exception $e) {
                $response['message'] = $e->getMessage();
                $response['errors'] = $e->getTrace();
                $response['status'] = false;
            }
            if ($response['status']) {
                $response['status'] = true;
                $response['code'] = 'success';
                $response['message'] = Yii::t('multilingual', 'Saved Successfully');
                $transaction->commit();
            } else {
                $response['code'] = 'error';
                $transaction->rollBack();
            }
            if (Yii::$app->request->isAjax) {
                return $response;
            }
            Yii::$app->session->setFlash($response['code'], $response['message']);
            return $this->redirect(['index', 'is_static' => 1]);
        }

        return $this->render('_form-static', [
            'table' => LanguageService::getMessages($lang, $category, Yii::$app->request->queryParams),
            'table_name' => $lang,
            'translating_language' => Yii::$app->params['language_list'][str_replace(BaseLanguageList::LANG_TABLE_PREFIX, '', $lang)]['name'] ?? $lang,
            'category' => $category,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function actionExportToExcel(string $table_name = null, bool $is_static = false)
    {
        $response = Multilingual::exportToExcel($table_name, $is_static);
        if (Yii::$app->request->isAjax) {
            return $response;
        } else {
            $url = json_decode($response)->fileUrl;
            $file = Yii::getAlias("@webroot{$url}");
            if (file_exists($file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); // Excel fayl turi
                header('Content-Disposition: attachment; filename="' . basename($file) . '"'); // Fayl nomi
                header('Expires: 0'); // Yaroqlilik muddati
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file)); // Fayl hajmi

                // Faylni o'qish va yuklab berish
                readfile($file);
                exit;
            }
            return true;
        }
    }

    /**
     * @throws \Exception
     */
    public function actionExportToExcelDefault()
    {
        $response = Multilingual::exportToExcelDefault(Yii::$app->request->queryParams);
        if (Yii::$app->request->isAjax) {
            return $response;
        } else {
            $url = json_decode($response)->fileUrl;
            $file = Yii::getAlias("@webroot{$url}");
            if (file_exists($file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); // Excel fayl turi
                header('Content-Disposition: attachment; filename="' . basename($file) . '"'); // Fayl nomi
                header('Expires: 0'); // Yaroqlilik muddati
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file)); // Fayl hajmi

                // Faylni o'qish va yuklab berish
                readfile($file);
                exit;
            }
            return true;
        }
    }

    /**
     * @param $lang
     * @return yii\web\Response
     */
    public function actionSelectLang($lang): Response
    {
        Yii::$app->session->set('lang', $lang);
        Yii::$app->language = $lang;
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Finds the BaseLanguageList model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $table_name
     * @param integer $id
     * @return array|ActiveRecord the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(string $table_name, int $id): array|ActiveRecord
    {
        Multilingual::$tableName = $table_name;
        $model = Multilingual::find()
            ->from($table_name)
            ->where(['id' => $id])
            ->one();
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('multilingual', 'The requested page does not exist.'));
    }
}