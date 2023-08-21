<?php

header('Content-type: application/json;');

function convertPersianToEnglish($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $response = str_replace($persian, $english, $string);
    return $response;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://arzdigital.com/coins');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36");
$response = curl_exec($ch);
curl_close($ch);

preg_match_all('#pulser-change="(.*?)">(.*?)</span>#', $response, $dollar1);
$dollar = $dollar1[2];
$rep = str_replace(['$'], [''], $dollar);
preg_match_all('#<span class="(.*?)">(.*?)</span><span class="arz-toman arz-value-unit">#', $response, $toman1);
$toman = $toman1[2];

for ($i=0;$i<=count($dollar)-1;$i++) {
    $value = ['p-toman' => convertPersianToEnglish($toman[$i]), 'p-dolar'=> $rep[$i]];
    $arz[] = $value;
}

echo json_encode($arz[23], 448);

?>