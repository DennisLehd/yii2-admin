<?php

use yii\bootstrap5\Alert;
use yii\bootstrap5\NavBar;
use yii\bootstrap5\Nav;
use yii\helpers\Html;

/* @var $this \yii\web\View */
/* @var $content string */

list(, $url) = Yii::$app->assetManager->publish('@mdm/admin/assets');
$this->registerCssFile($url . '/main.css');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>

<body class="d-flex flex-column gap-3">
    <?php $this->beginBody() ?>
    <header>
        <?php
        NavBar::begin([
            'brandLabel' => false,
            'options' => ['class' => 'navbar navbar-expand-md navbar-inverse navbar-fixed-top bg-dark navbar-dark'],
        ]);

        if (!empty($this->params['top-menu']) && isset($this->params['nav-items'])) {
            echo Nav::widget([
                'options' => ['class' => 'nav navbar-nav'],
                'items' => $this->params['nav-items'],
            ]);
        }

        echo Nav::widget([
            'options' => ['class' => 'nav navbar-nav navbar-right'],
            'items' => $this->context->module->navbar,
        ]);
        NavBar::end();
        ?>
    </header>
    <main class="flex-grow-1">
        <div class="container ">
            <?php
            foreach (Yii::$app->session->getAllFlashes() as $key => $msg)
                echo Alert::widget(['options' => ['class' => 'alert-' . $key], 'body' => $msg]);
            ?>
            <?= $content ?>
        </div>
    </main>
    <footer class="footer mt-auto">
        <div class="container">
            <p class="pull-right"><?= Yii::powered() ?></p>
        </div>
    </footer>

    <?php $this->endBody() ?>
</body>

</html>
<?php $this->endPage() ?>