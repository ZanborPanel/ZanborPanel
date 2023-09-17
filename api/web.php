<?php

include_once 'Hiddify.php';

try {

    $hiddify = new Hiddify('', '');
    echo $hiddify->createUser2('test', 30, 30);

} catch (\Throwable $e) {
    echo $e;
}