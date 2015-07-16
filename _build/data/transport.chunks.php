<?php
$chunk = $modx->newObject('modChunk');
$chunk->fromArray(array(
    'name' => 'test1capi',
    'description' => 'test form',
    'snippet' => file_get_contents($sources['elements']."chunks/test1capi.tpl"),
    'static' => 0,
    'source' => 1,
    'static_file' => ''
),'',true,true);