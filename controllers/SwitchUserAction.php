<?php
namespace mdm\admin\controllers;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class SwitchUserAction extends \yii\base\Action
{
    public $userClass = 'mdm\admin\models\User';
    public $redirectUrl = ['/site'];

    /**
     * Switch user action
     *
     * @param string $username
     * @return \yii\web\Response
     */
    public function run($id)
    {
        if (Yii::$app->session->has('original_user_id')) {
            throw new ForbiddenHttpException(Yii::t('rbac-admin', 'You are already switched to another user. Please switch back first.'));
        }

        $user = $this->userClass::findOne($id);
        if (!$user) {
            throw new NotFoundHttpException(Yii::t('rbac-admin', 'User not found.'));
        }

        // Store original user ID
        Yii::$app->session->set('original_user_id', Yii::$app->user->id);

        // Switch to target user
        Yii::$app->user->login($user);

        return $this->controller->redirect($this->redirectUrl);
    }

    public static function isSwitched()
    {
        return Yii::$app->session->has('original_user_id');
    }   
}