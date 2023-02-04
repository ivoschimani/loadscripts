<?php

$GLOBALS['TL_DCA']['tl_layout']['config']['onload_callback'][] = ['Ivo\LoadScripts\Classes\DcaCallback', 'onLoadCallback'];

$GLOBALS['TL_DCA']['tl_layout']['palettes']['default'] = str_replace(
    'external,',
    'externalCssHead,external,',
    $GLOBALS['TL_DCA']['tl_layout']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_layout']['palettes']['default'] = str_replace(
    'externalJs,',
    'externalJsHead,externalJsBody,',
    $GLOBALS['TL_DCA']['tl_layout']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_layout']['fields']['externalCssHead'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_layout']['externalCssHead'],
    'exclude'                 => true,
    'inputType'               => 'fileTree',
    'eval'                    => array('multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'extensions' => 'css,scss,less', 'isSortable' => true),
    'sql'                     => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_layout']['fields']['externalJsHead'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_layout']['externalJsHead'],
    'exclude'                 => true,
    'inputType'               => 'fileTree',
    'eval'                    => array('multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'extensions' => 'js', 'isSortable' => true),
    'sql'                     => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_layout']['fields']['externalJsBody'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_layout']['externalJsBody'],
    'exclude'                 => true,
    'inputType'               => 'fileTree',
    'eval'                    => array('multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'extensions' => 'js', 'isSortable' => true),
    'sql'                     => "blob NULL"
];