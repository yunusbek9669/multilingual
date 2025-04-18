<?php

namespace Yunusbek\Multilingual\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yunusbek\Multilingual\components\LanguageService;
use Yunusbek\Multilingual\models\Multilingual;

/**
 * LanguageController implements the CRUD actions for Multilingual model.
 */
class LanguageController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all BaseLanguageList models.
     * @return string
     * @throws Exception
     */
    public function actionIndex($is_static = 0): string
    {
        if ($is_static) {
            $translates = LanguageService::getI18NData();
            return $this->render('index-static', [
                'translates' => $translates,
            ]);
        } else {
            $searchParams = Yii::$app->request->queryParams;
            $searchParams['is_all'] = true;
            $translates = LanguageService::getModelsData($searchParams);
            return $this->render('index-dynamic', [
                'searchParams' => $searchParams,
                'translates' => $translates,
            ]);
        }
    }

    /**
     * Updates an existing BaseLanguageList model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $table_name
     * @param integer $table_iteration
     * @return Response|array|string
     * @throws Exception
     * @throws NotFoundHttpException|InvalidParamException
     */
    public function actionTranslate(string $table_name, int $table_iteration, array $attributes): Response|array|string
    {
        $model = $this->findModel($table_name, $table_iteration);
        $request = Yii::$app->request;
        if ($request->isPost) {
            if ($request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
            }
            $response = [];
            $response['status'] = true;
            $response['code'] = 'error';
            $response['message'] = Yii::t('multilingual', 'Error');
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
                return $this->redirect(['index']);
            }
        }

        return $this->render('_form', [
            'attributes' => $attributes,
            'model' => $model,
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
     * @throws Exception if the model cannot be found
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
