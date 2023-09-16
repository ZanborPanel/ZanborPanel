<?php

include_once 'config.php';

$count = 0;
$users = $sql->query("SELECT * FROM `users`");
$send = $sql->query("SELECT * FROM `sends`")->fetch_assoc();

if ($send['send'] == 'yes') {
    if ($send['step'] == 'send') {
        if ($send['type'] == 'text') {
            while ($row = $users->fetch_assoc()) {
                sendMessage($row['from_id'], $send['text']);
                $count++;
            }
        }
    } elseif ($user['step'] == 'forward') {
        while ($row = $users->fetch_assoc()) {
            forwardMessage($from_id, $row['from_id'], $send['text']);
            $count++;
        }
    } else {
        die('Error');
    }
}

echo json_encode(['status' => true, 'msg' => 'send is successfuly.', 'status_code' => 200], 448);;