<?php

namespace mdm\admin\controllers;

use mdm\admin\models\form\SettingsFileModel;
use Yii;
use yii\helpers\Json;
use yii\web\UploadedFile;

/**
 * DefaultController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class DefaultController extends \yii\web\Controller
{

    public function actions()
    {
        return [
            'switch-user' => [
                'class' => 'mdm\admin\controllers\SwitchUserAction',
            ],
            'switch-user-back' => [
                'class' => 'mdm\admin\controllers\SwitchUserBackAction',
            ],
        ];
    }

    /**
     * Action index
     */
    public function actionIndex($page = 'README.md')
    {
        if (preg_match('/^docs\/images\/image\d+\.png$/', $page)) {
            $file = Yii::getAlias("@mdm/admin/{$page}");
            return Yii::$app->getResponse()->sendFile($file);
        }
        return $this->render('index', ['page' => $page]);
    }

    public function actionGraph()
    {
        $model = new SettingsFileModel;
        $definitions = $model->databaseExport();
        if (empty($definitions)) {
            Yii::$app->session->setFlash('error', Yii::t('rbac-admin', 'No RBAC definitions found.'));
            return $this->redirect(['index']);
        }
        $mermaid = $model->generateMermaidRBAC($definitions);
        return $this->render('graph', ['mermaid' => $mermaid]);
    }


    /**
     * Action import
     */
    public function actionImport()
    {
        $model = new SettingsFileModel;

        if (Yii::$app->request->isPost) {
            $model->file = UploadedFile::getInstance($model, 'file');
            if ($model->importFile()) {
                // file is uploaded successfully
                Yii::$app->session->setFlash('success', Yii::t('rbac-admin', 'RBAC definitions imported successfully.'));
                return $this->redirect(['graph']);
            }
        }

        return $this->render('import', [
            'model' => $model,
        ]);
    }

    /**
     * Action export
     */
    public function actionExport()
    {
        $module = $this->module;
        if ($module->defaultSettingsPath === null) {
            throw new \yii\web\ForbiddenHttpException(Yii::t('rbac-admin', 'Import/Export not allowed.'));
        }

        $model = new SettingsFileModel;
        $json = $model->databaseExport(true);
        return Yii::$app->response->sendContentAsFile(
            $json,
            'rbac-definitions-export.json',
            [
                'mimeType' => 'application/json',
                'inline' => false,
            ]
        );
    }

    /**
     * Action set default
     */
    public function actionSetCurrentToDefault()
    {
        $module = $this->module;
        if ($module->defaultSettingsPath === null) {
            throw new \yii\web\ForbiddenHttpException(Yii::t('rbac-admin', 'Import/Export not allowed.'));
        }

        $model = new SettingsFileModel;
        $filePath = Yii::getAlias($module->defaultSettingsPath . '/rbac-definitions-export.json');
        if (file_exists($filePath)) {
            unlink($filePath); // Remove existing file
        }
        $json = $model->databaseExport(true);
        file_put_contents($filePath, $json);
        Yii::$app->session->setFlash('success', Yii::t('rbac-admin', 'Current RBAC definitions set as default.'));
        return $this->redirect(Yii::$app->request->referrer ?: [$module->defaultRoute]);
    }

    /**
     * Action reset default/restore saved values to database
     */
    public function actionResetToDefault()
    {
        $module = $this->module;
        if ($module->defaultSettingsPath === null) {
            throw new \yii\web\ForbiddenHttpException(Yii::t('rbac-admin', 'Import/Export not allowed.'));
        }

        $model = new SettingsFileModel;
        $filePath = Yii::getAlias($module->defaultSettingsPath . '/' .  '/rbac-definitions-export.json');
        if (!file_exists($filePath)) {
            Yii::$app->session->setFlash('danger', Yii::t('rbac-admin', 'Default RBAC definitions file not found.'));
            return $this->redirect(Yii::$app->request->referrer ?: [$module->defaultRoute]);
        }
        $json = file_get_contents($filePath);
        if ($model->databaseImport(Json::decode($json, true))) {
            Yii::$app->session->setFlash('success', Yii::t('rbac-admin', 'Default RBAC definitions restored successfully.'));
        } else {
            Yii::$app->session->setFlash('danger', Yii::t('rbac-admin', 'Failed to restore default RBAC definitions.'));
        }

        return $this->redirect(Yii::$app->request->referrer ?: [$module->defaultRoute]);
    }


    public function actionClearCache()
    {
        Yii::$app->authManager->flush();
        Yii::$app->session->setFlash('success', Yii::t('rbac-admin', 'RBAC cache cleared successfully.'));
        return $this->redirect(Yii::$app->request->referrer);
    }
}
