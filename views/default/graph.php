<?php

use mdm\admin\models\form\SettingsFileModel;
use yii\helpers\Html;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js', [
    'position' => \yii\web\View::POS_HEAD,
]);
$this->registerJs(<<<JS
    mermaid.initialize({ startOnLoad: true });
JS, \yii\web\View::POS_END);

echo Html::tag('h1', Yii::t('rbac-admin', 'RBAC Graph'));
echo Html::tag('div', Html::encode($mermaid), ['class' => 'mermaid']);


