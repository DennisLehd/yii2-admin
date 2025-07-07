<?php
// Import .json file with RBAC definitions
// this is the View file with the form for importing
// $model is an instance of SettingsFileModel
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this View */
/* @var $model mdm\admin\models\form\SettingsFileModel */

$this->title = Yii::t('rbac-admin', 'Import RBAC Definitions');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="settings-file-import">

    <h1><?= Html::encode($this->title) ?></h1>

    <p class="alert alert-info">
        <?= Yii::t('rbac-admin', 'Import RBAC definitions from a JSON file. Ensure that the JSON file is correctly formatted and contains valid RBAC definitions.') ?>
    </p>
    <p class="alert alert-warning">
        <?= Yii::t('rbac-admin', '<span style="font-size:30px;border:1px solid red;padding:10px;border-radius:40px;margin-right:20px;">&#10071;</span><span style="font-size:20px;">This will overwrite existing definitions.</span>') ?>
    </p>

    <?php $form = ActiveForm::begin([
        'id' => 'import-form',
        'options' => ['enctype' => 'multipart/form-data'],
    ]); ?>

    <?= $form->field($model, 'file')->fileInput() ?>

    <div class="form-group my-5">
        <?= Html::submitButton(Yii::t('rbac-admin', 'Import'), ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
    <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger">
            <?= Html::errorSummary($model) ?>
        </div>
    <?php endif; ?>
</div>