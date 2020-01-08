<?php
$fh = fopen(__DIR__ . '/targets.csv', 'r');
fgetcsv($fh, 2048);
$context = stream_context_create(array(
    "ssl"=>array(
        "verify_peer" => false,
        "verify_peer_name" => false,
    ),
));
$pool = array();
while($line = fgetcsv($fh, 2048)) {
    $parts = explode('/', $line[0]);
    $pool[substr($parts[2], 0, -5)] = $line;
}
fseek($fh, 0);
fgetcsv($fh, 2048);
$targetGroups = array();
while($line = fgetcsv($fh, 2048)) {
    $id = substr(explode('/', $line[0])[2], 0, -5);
    $parts = preg_split('/[,\\/]/', $line[0]);
    $partsCombined = implode('`', array($parts[2], $parts[3], $parts[4], $parts[5]));
    $linkFile = __DIR__ . '/links/' . str_replace('`', '_', $partsCombined) . '.json';
    if(!file_exists($linkFile)) {
        $url = 'https://law.judicial.gov.tw/controls/GetJudHistory.ashx?jid=' . urlencode($partsCombined);
        file_put_contents($linkFile, file_get_contents($url, false, $context));
    }
    $json = json_decode(file_get_contents($linkFile));
    $targetGroups[$id] = array($line);
    if(isset($pool[$id])) {
        unset($pool[$id]);
    }
    if($json->count > 0) {
        foreach($json->list AS $item) {
            if(!empty($item->href)) {
                $urlParts = parse_url($item->href);
                $parameters = parse_str($urlParts['query'], $query);
                if(isset($pool[$query['id']])) {
                    $targetGroups[$id][] = $pool[$query['id']];
                    unset($pool[$query['id']]);
                }
            }
            
        }
    }
}
$newLine = "\n";
foreach($targetGroups AS $group) {
    if(count($group) > 2) {
        foreach($group AS $issue) {
            $origin = json_decode(file_get_contents(__DIR__ . '/filter/' . $issue[0]));
            $jsonFile = __DIR__ . '/meta/' . $issue[0];
            if(file_exists($jsonFile)) {
                $json = json_decode(file_get_contents($jsonFile));
                if(is_array($json[0])) {
                    $itemIndex = $pos = 0;
                    $fullTextLength = mb_strlen($origin->JFULL, 'utf-8');
                    $previousLine = "";
                    while($pos < $fullTextLength) {
                        $nextLinePos = mb_strpos($origin->JFULL, $newLine, $pos, 'utf-8');
                        if($nextLinePos > $json[0][$itemIndex][0]) {
                            echo "\n{$previousLine}";
                            echo "\n" . mb_substr($origin->JFULL, $pos, $nextLinePos - $pos, 'utf-8') . "\n";
                            print_r($json[0][$itemIndex]);
                            exit();
                        }
                        $previousLine = mb_substr($origin->JFULL, $pos, $nextLinePos - $pos, 'utf-8');
                        $pos = $nextLinePos + 1;
                    }
                }
            }
            exit();
        }
    }
}