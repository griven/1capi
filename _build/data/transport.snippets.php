<?php

function getSnippetContent($filename) {
    $o = file_get_contents($filename);
    $o = trim(str_replace(array('<?php','?>'),'',$o));
    return $o;
}
$snippets = array();

$snippets[1]= $modx->newObject('modSnippet');
$snippets[1]->fromArray(array(
    'id' => 1,
    'name' => '1capi',
    'description' => 'Integration snippet with 1c',
    'snippet' => getSnippetContent($sources['elements'].'snippets/snippet.1capi.php'),
),'',true,true);

return $snippets;
