<?php
$fh = fopen(__DIR__ . '/targets.csv', 'w');
fputcsv($fh, array('file', 'case', 'title'));
foreach(glob(__DIR__ . '/extract/*/*') AS $host) {
    foreach(glob($host . '/*.json') AS $jsonFile) {
        $caseMatch = false;
        if(false !== strpos($jsonFile, '選')) {
            $caseMatch = true;
        }
        if($caseMatch || mb_substr($host, -2, 2, 'utf-8') === '刑事') {
            $pos = strpos($jsonFile, '/extract/') + 9;
            $json = json_decode(file_get_contents($jsonFile));
            if($caseMatch || false !== strpos($json->JTITLE, '選')) {
                fputcsv($fh, array(substr($jsonFile, $pos), $json->JCASE, $json->JTITLE));
            }
        }
    }
}