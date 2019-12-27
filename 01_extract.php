<?php
// fetching files using command below and extract them
// wget --mirror http://data.judicial.gov.tw/
$targetPath = __DIR__ . '/extract';
foreach(glob(__DIR__ . '/data.judicial.gov.tw/rar/*.rar') AS $rarFile) {
    $rarFile = str_replace(array('(', ')'), array('\\(', '\\)'), addslashes($rarFile));
    exec("unrar x -o+ {$rarFile} {$targetPath}");
    error_log("{$rarFile} done");
}
