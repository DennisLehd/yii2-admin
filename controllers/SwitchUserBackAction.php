<?php

namespace mdm\admin\controllers;

use mdm\admin\models\User;
use Yii;
use yii\web\NotFoundHttpException;

class SwitchUserBackAction extends \yii\base\Action
{
    public $userClass = 'mdm\admin\models\User';
    public $redirectUrl = ['/site'];

    /**
     * Switch back to the original user
     *
     * @return \yii\web\Response
     */
    public function run()
    {
        if (!\Yii::$app->session->has('original_user_id')) {
            throw new \yii\web\ForbiddenHttpException(Yii::t('rbac-admin', 'You are not currently switched to another user.'));
        }

        // Get the original user ID
        $originalUserId = \Yii::$app->session->get('original_user_id');
        $originalUser = $this->userClass::findOne($originalUserId);
        if (!$originalUser) {
            throw new NotFoundHttpException(Yii::t('rbac-admin', 'Original user not found.'));
        }

        // Switch back to the original user
        \Yii::$app->user->login($originalUser);

        // Remove the original user ID from session
        \Yii::$app->session->remove('original_user_id');

        return $this->controller->redirect($this->redirectUrl);
    }
}
