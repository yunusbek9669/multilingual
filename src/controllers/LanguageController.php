<?php

namespace Yunusbek\Multilingual\controllers;

use Yunusbek\Multilingual\models\BaseLanguageQuery;
use Yunusbek\Multilingual\models\LanguageModel;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * LanguageController implements the CRUD actions for LanguageModel model.
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
     * Lists all MultiLanguage models.
     * @return string
     * @throws Exception
     */
    public function actionIndex(): string
    {
        $searchParams = Yii::$app->request->queryParams;
        $searchParams['is_all'] = true;
        $translates = BaseLanguageQuery::searchAllLanguage($searchParams);
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('index-all-language', [
                'searchParams' => $searchParams,
                'translates' => $translates,
            ]);
        }
        return $this->render('index', [
            'searchParams' => $searchParams,
            'translates' => $translates,
        ]);
    }

    /**
     * Updates an existing MultiLanguage model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $table_name
     * @param integer $table_iteration
     * @return Response|array|string
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionTranslate(string $table_name, int $table_iteration, array $attributes): Response|array|string
    {
        $model = $this->findModel($table_name, $table_iteration);
        $request = Yii::$app->request;
        if ($request->isPost)
        {
            if ($request->isAjax) { Yii::$app->response->format = Response::FORMAT_JSON; }
            $response = [];
            $response['status'] = true;
            $response['code'] = 'error';
            $response['message'] = Yii::t('app', 'Error');
            $languageModel = $request->post((new \ReflectionClass($model))->getShortName());
            if ($model->load($request->post())) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    foreach ($languageModel as $attribute => $value) {
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
                    $response['message'] = Yii::t('app', 'Saved Successfully');
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
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('_form', [
                'attributes' => $attributes,
                'model' => $model,
            ]);
        }

        return $this->render('_form', [
            'attributes' => $attributes,
            'model' => $model,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function actionExportToExcel(string $table_name = null, bool $is_all = false)
    {
        if ($is_all) {
            $response = LanguageModel::exportBasicToExcel();
        } else {
            $response = LanguageModel::exportToExcel($table_name);
        }
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
     * Finds the MultiLanguage model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return array|ActiveRecord the loaded model
     * @throws NotFoundHttpException|Exception if the model cannot be found
     */
    protected function findModel(string $table_name, int $id): array|ActiveRecord
    {
        LanguageModel::$tableName = $table_name;
        $model = LanguageModel::find()
            ->from($table_name)
            ->where(['id' => $id])
            ->one();
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
