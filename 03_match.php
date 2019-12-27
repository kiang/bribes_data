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
foreach($targetGroups AS $group) {
    if(count($group) > 2) {
        foreach($group AS $issue) {
            $jsonFile = __DIR__ . '/extract/' . $issue[0];
            $json = json_decode(file_get_contents($jsonFile));
            print_r($json);
        }
        exit();
    }
}