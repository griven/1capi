<?php
$settings = array();

$tmp = array(
    'catalog_id' => array(
        'xtype' => 'textfield',
        'value' => '14',
    ),
    'product_template' => array(
        'xtype' => 'textfield',
        'value' => '4',
    ),
    'catalog_template' => array(
        'xtype' => 'textfield',
        'value' => '3',
    ),
    'debug' => array(
        'xtype' => 'combo-boolean',
        'value' => true,
    ),
    'salt' => array(
        'xtype' => 'textfield',
        'value' => 'solt',
    ),
    'limit' => array(
        'xtype' => 'textfield',
        'value' => '1500',
    ),
);

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(
        array(
            'key' => PKG_NAME_LOWER.".".$k,
            'namespace' => PKG_NAME_LOWER,
        ), $v
    ),'',true,true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;
