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
/*
$line = Array
(
    [0] => 199601/最高法院刑事/TPSM,85,台上,372,19960124.json
    [1] => 台上
    [2] => 違反選舉罷免法
)
*/
while($line = fgetcsv($fh, 2048)) {
    /*
$parts = Array
(
    [0] => 199601
    [1] => 最高法院刑事
    [2] => TPSM,85,台上,372,19960124.json
)
    */
    $parts = explode('/', $line[0]);
    /*
$pool = Array
(
    [TPSM,85,台上,372,19960124] => Array
        (
            [0] => 199601/最高法院刑事/TPSM,85,台上,372,19960124.json
            [1] => 台上
            [2] => 違反選舉罷免法
        )

)
    */
    $pool[substr($parts[2], 0, -5)] = $line;
}
fseek($fh, 0);
fgetcsv($fh, 2048);
$targetGroups = array();
while($line = fgetcsv($fh, 2048)) {
    /*
$id = TPSM,85,台上,372,19960124
    */
    $id = substr(explode('/', $line[0])[2], 0, -5);
    if(!isset($pool[$id])) {
        continue;
    }
    /*
$parts = Array
(
    [0] => 199601
    [1] => 最高法院刑事
    [2] => TPSM
    [3] => 85
    [4] => 台上
    [5] => 372
    [6] => 19960124.json
)
    */
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
        /*
        https://law.judicial.gov.tw/FJUD/data.aspx ...
$json = (
    [count] => 2
    [list] => Array
        (
            [0] => stdClass Object
                (
                    [desc] => 最高法院 86 年度 台上 字第 164 號判決(86.01.15)
                    [href] => data.aspx?ty=JD&id=TPSM%2c86%2c%e5%8f%b0%e4%b8%8a%2c164%2c19970115
                    [red] => 0
                )

            [1] => stdClass Object
                (
                    [desc] => 臺灣高等法院 85 年度 上更(二) 字第 137 號判決
                    [href] => 
                    [red] => 0
                )

        )

)
        */
        foreach($json->list AS $item) {
            if(!empty($item->href)) {
                $urlParts = parse_url($item->href);
                parse_str($urlParts['query'], $query);
                if(isset($pool[$query['id']])) {
                    $targetGroups[$id][] = $pool[$query['id']];
                    unset($pool[$query['id']]);
                }
            }
            
        }
    }
}
function cmp($a, $b)
{
    if ($a[0] == $b[0]) {
        return 0;
    }
    return ($a[0] < $b[0]) ? -1 : 1;
}
$newLine = "\n";
foreach($targetGroups AS $id => $group) {
    $case = array(
        'id' => $id,
        'href' => 'https://law.judicial.gov.tw/FJUD/data.aspx?ty=JD&id=' . urlencode($id),
        'history' => array(),
    );
    foreach($group AS $issue) {
        $origin = json_decode(file_get_contents(__DIR__ . '/filter/' . $issue[0]));
        $case['history'][$origin->JID] = array();
        $jsonFile = __DIR__ . '/meta/' . $issue[0];
        $fullTextLength = mb_strlen($origin->JFULL, 'utf-8');
        $itemIndex = $pos = $nextLinePos = 0;
        if(file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile));
            if(is_array($json[0])) {
                usort($json[0], "cmp");
            }
            while($pos < $fullTextLength && false !== $nextLinePos) {
                $nextLinePos = mb_strpos($origin->JFULL, $newLine, $pos + 1, 'utf-8');
                $currentLine = trim(mb_substr($origin->JFULL, $pos, $nextLinePos - $pos, 'utf-8'));
    
                while(isset($json[0][$itemIndex]) && $json[0][$itemIndex][2] !== 'MONEY') {
                    ++$itemIndex;
                }
                $keywords = array();
                while(isset($json[0][$itemIndex]) && ($nextLinePos > $json[0][$itemIndex][0])) {
                    if($json[0][$itemIndex][2] === 'MONEY') {
                        $keywords[] = $json[0][$itemIndex][3];
                    }
                    ++$itemIndex;
                }
                $case['history'][$origin->JID][] = array(
                    $currentLine,
                    implode(',', $keywords),
                );
                $pos = $nextLinePos;
            }
        } else {
            while($pos < $fullTextLength && false !== $nextLinePos) {
                $nextLinePos = mb_strpos($origin->JFULL, $newLine, $pos + 1, 'utf-8');
                $currentLine = trim(mb_substr($origin->JFULL, $pos, $nextLinePos - $pos, 'utf-8'));
                $case['history'][$origin->JID][] = array(
                    $currentLine,
                    '',
                );
                $pos = $nextLinePos;
            }
        }
    }
    file_put_contents(__DIR__ . '/case/' . $case['id'] . '.json', json_encode($case,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}