<?php
/* @var $this \yii\web\View */
/* @var $content string */

$controller = $this->context;
$menus = $controller->module->menus;
$route = $controller->route;
foreach ($menus as $i => $menu) {
    $menus[$i]['active'] = strpos($route, trim((string)$menu['url'][0], '/')) === 0;
}

$menus = $menus + [
    [
        'label' => Yii::t('rbac-admin', 'Save/Restore'),
        'visible' => $controller->module->defaultSettingsPath !== null,
        'items' => [
            [
                'label' => Yii::t('rbac-admin', 'Set current as default'),
                'url' => ['default/set-current-to-default'],
                'visible' => $controller->module->defaultSettingsPath !== null,
            ],
            [
                'label' => Yii::t('rbac-admin', 'Restore default'),
                'url' => ['default/reset-to-default'],
                'visible' => $controller->module->defaultSettingsPath !== null,
            ],
            [
                'label' => Yii::t('rbac-admin', 'Import'),
                'url' => ['default/import'],
                'visible' => $controller->module->defaultSettingsPath !== null,
            ],
            [
                'label' => Yii::t('rbac-admin', 'Export'),
                'url' => ['default/export'],
                'visible' => $controller->module->defaultSettingsPath !== null,
            ],
            [
                'label' => Yii::t('rbac-admin', 'Clear cache'),
                'url' => ['default/clear-cache'],
                'visible' => $controller->module->defaultSettingsPath !== null,
            ],
        ]
    ],
    [
        'label' => Yii::t('rbac-admin', 'RBAC Graph'),
        'url' => ['default/graph'],        
    ],
    [
        'label' => Yii::t('rbac-admin', 'Signup user'),
        'url' => ['user/signup'],
    ],
    [
        'label' => Yii::t('rbac-admin', 'Switch Back'),
        'url' => ['default/switch-user-back'],
        'visible' => \mdm\admin\controllers\SwitchUserAction::isSwitched(),
    ],
];

$this->params['nav-items'] = $menus;
$this->params['top-menu'] = true;
?>
<?php $this->beginContent($controller->module->mainLayout) ?>
<div class="row">
    <div class="col-sm-12">
        <?= $content ?>
    </div>
</div>
<?php $this->endContent(); ?>