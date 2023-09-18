<?php

# -- #
/**
* Project name: ZanborPanel
* Channel: @ZanborPanel
* Group: @ZanborPanelGap
 * Version: 2.5
**/

include_once 'config.php';
include_once 'api/sanayi.php';
# include_once  'api/hiddify.php';


if ($data == 'join') {
	if (isJoin($from_id)){
		deleteMessage($from_id, $message_id);
		sendMessage($from_id, $texts['success_joined'], $start_key);
	} else {
		alert($texts['not_join']);
	}
}

elseif(isJoin($from_id) == false){
    joinSend($from_id);
}

elseif($user['status'] == 'inactive' and $from_id != $config['dev']){
    sendMessage($from_id, $texts['block']);
}

elseif ($text == '/start' or $text == '🔙 بازگشت' or $text == '/back') {
    step('none');
    sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif ($text == '❌  انصراف' and $user['step'] == 'confirm_service') {
    step('none');
    foreach ([$from_id . '-location.txt', $from_id . '-protocol.txt'] as $file) if (file_exists($file)) unlink($file);
	if($sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->num_rows > 0) $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
	sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif ($text == '🛒 خرید سرویس') {
	$servers = $sql->query("SELECT * FROM `panels` WHERE `status` = 'active'");
	if ($servers->num_rows > 0) {
		step('buy_service');
		if ($sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->num_rows > 0) $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
	    while ($row = $servers->fetch_assoc()) {
			$location[] = ['text' => $row['name']];
		}
		$location = array_chunk($location, 2);
	    $location[] = [['text' => '🔙 بازگشت']];
		$location = json_encode(['keyboard' => $location, 'resize_keyboard' => true]);
		sendMessage($from_id, $texts['select_location'], $location);
	} else {
	    sendmessage($from_id, $texts['inactive_buy_service'], $start_key);
	}
}

elseif ($user['step'] == 'buy_service') {
	$response = $sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'");
	if ($response->num_rows == 0) {
	    step('none');
	    sendMessage($from_id, $texts['choice_error']);
	} else {
    	step('select_plan');
        $plans = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'");
        while ($row = $plans->fetch_assoc()) {
            $plan[] = ['text' => $row['name']];
        }
        $plan = array_chunk($plan, 2);
    	$plan[] = [['text' => '🔙 بازگشت']];
    	$plan = json_encode(['keyboard' => $plan, 'resize_keyboard' => true]);
    	file_put_contents("$from_id-location.txt", $text);
    	sendMessage($from_id, $texts['select_plan'], $plan);
	}
}

elseif ($user['step'] == 'select_plan') {
	$response = $sql->query("SELECT `name` FROM `category` WHERE `name` = '$text'")->num_rows;
	if ($response > 0) {
    	step('confirm_service');
    	sendMessage($from_id, $texts['create_factor'], $confirm_service);
    	$location = file_get_contents("$from_id-location.txt");
    	$plan = $text;
    	$code = rand(111111, 999999);
    	
    	$fetch = $sql->query("SELECT * FROM `category` WHERE `name` = '$text'")->fetch_assoc();
    	$price = $fetch['price'] ?? 0;
    	$limit = $fetch['limit'] ?? 0;
    	$date = $fetch['date'] ?? 0;
    	
    	$sql->query("INSERT INTO `service_factors` (`from_id`, `location`, `protocol`, `plan`, `price`, `code`, `status`) VALUES ('$from_id', '$location', 'null', '$plan', '$price', '$code', 'active')");
    	$copen_key = json_encode(['inline_keyboard' => [[['text' => '🎁 کد تخفیف', 'callback_data' => 'use_copen-'.$code]]]]);
    	sendMessage($from_id, sprintf($texts['service_factor'], $location, $limit, $date, $code, number_format($price)), $copen_key);
	} else {
	    sendMessage($from_id, $texts['choice_error']);
	}
}

elseif ($data == 'cancel_copen') {
    step('confirm_service');
    deleteMessage($from_id, $message_id);
}

elseif (strpos($data, 'use_copen') !== false and $user['step'] == 'confirm_service') {
    $code = explode('-', $data)[1];
    step('send_copen-'.$code);
    sendMessage($from_id, $texts['send_copen'], $cancel_copen);
}

elseif (strpos($user['step'], 'send_copen-') !== false) {
    $code = explode('-', $user['step'])[1];
    $copen = $sql->query("SELECT * FROM `copens` WHERE `copen` = '$text'");
    $service = $sql->query("SELECT * FROM `service_factors` WHERE `code` = '$code'")->fetch_assoc();
    if ($copen->num_rows > 0) {
        $copen = $copen->fetch_assoc();
        if ($copen['status'] == 'active') {
            if ($copen['count_use'] > 0) {
                step('confirm_service');
                $price =  $service['price'] * (intval($copen['percent']) / 100);
                $sql->query("UPDATE `service_factors` SET `price` = price - $price WHERE `code` = '$code'");
                sendMessage($from_id, sprintf($texts['success_copen'], $copen['percent']), $confirm_service);
            } else {
                sendMessage($from_id, $texts['copen_full'], $cancel_copen);
            }
        } else {
            sendMessage($from_id, $texts['copen_error'], $cancel_copen);
        }
    } else {
        sendMessage($from_id, $texts['copen_error'], $cancel_copen);
    }
}

elseif($user['step'] == 'confirm_service' and $text == '☑️ ایجاد سرویس'){
    step('none');
    sendMessage($from_id, $texts['create_service_proccess']);
    # ---------------- delete extra files ---------------- #
    foreach ([$from_id . '-location.txt', $from_id . '-protocol.txt'] as $file) if (file_exists($file)) unlink($file);
    # ---------------- get all information for create service ---------------- #
    $select_service = $sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->fetch_assoc();
    $location = $select_service['location'];
    $plan = $select_service['plan'];
    $price = $select_service['price'];
    $code = $select_service['code'];
    $status = $select_service['status'];
    $name = base64_encode($code) . '_' . $from_id;
    $get_plan = $sql->query("SELECT * FROM `category` WHERE `name` = '$plan'");
    $get_plan_fetch = $get_plan->fetch_assoc();
    $date = $get_plan_fetch['date'] ?? 0;
    $limit = $get_plan_fetch['limit'] ?? 0;
    $info_panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '$location'");
    $panel = $info_panel->fetch_assoc();
    # ---------------- check coin for create service ---------------- #
    if ($user['coin'] < $select_service['price']) {
        sendMessage($from_id, sprintf($texts['not_coin'], number_format($price)), $start_key);
        exit();
    }
    # ---------------- check database ----------------#
    if ($get_plan->num_rows == 0) {
        sendmessage($from_id, sprintf($texts['create_error'], 0), $start_key);
        exit();
    }
    # ---------------- create service proccess ---------------- #
    if ($panel['type'] == 'marzban') {
        # ---------------- set proxies and inbounds proccess for marzban panel ---------------- #
        $protocols = explode('|', $panel['protocols']);
        unset($protocols[count($protocols)-1]);
        if ($protocols[0] == '') unset($protocols[0]);
        $proxies = array();
        foreach ($protocols as $protocol) {
            if ($protocol == 'vless' and $panel['flow'] == 'flowon'){
                $proxies[$protocol] = array('flow' => 'xtls-rprx-vision');
            } else {
                $proxies[$protocol] = array();
            }
        }
        $panel_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '{$panel['code']}'");
        $inbounds = array();
        foreach ($protocols as $protocol) {
            while ($row = $panel_inbounds->fetch_assoc()) {
                $inbounds[$protocol][] = $row['inbound'];
            }
        }
        # ---------------- create service ---------------- #
        $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
        $create_service = createService($name, convertToBytes($limit.'GB'), strtotime("+ $date day"), $proxies, ($panel_inbounds->num_rows > 0) ? $inbounds : 'null', $token, $panel['login_link']);
        $create_status = json_decode($create_service, true);
        # ---------------- check errors ---------------- #
        if (!isset($create_status['username'])) {
            sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
            exit();
        }
        # ---------------- get links and subscription_url for send the user ---------------- #
        $links = "";
        foreach ($create_status['links'] as $link) $links .= $link . "\n\n";
        
        if ($info_panel->num_rows > 0) {
            $getMe = json_decode(file_get_contents("https://api.telegram.org/bot{$config['token']}/getMe"), true);
            $subscribe = (strpos($create_status['subscription_url'], 'http') !== false) ? $create_status['subscription_url'] : $panel['login_link'] . $create_status['subscription_url'];
            if ($panel['qr_code'] == 'active') {
                $encode_url = urlencode($subscribe);
                bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => sprintf($texts['success_create_service'], $name, $location, $date, $limit, number_format($price), $subscribe, '@' . $getMe['result']['username']), 'parse_mode' => 'html', 'reply_markup' => $start_key]);
            } else {
                sendmessage($from_id, sprintf($texts['success_create_service'], $name, $location, $date, $limit, number_format($price), $subscribe, '@' . $getMe['result']['username']), $start_key);
            }
            $sql->query("INSERT INTO `orders` (`from_id`, `location`, `protocol`, `date`, `volume`, `link`, `price`, `code`, `status`, `type`) VALUES ('$from_id', '$location', 'null', '$date', '$limit', '$links', '$price', '$code', 'active', 'marzban')");
            // sendmessage($config['dev'], sprintf($texts['success_create_notif']), $first_name, $username, $from_id, $user['count_service'], $user['coin'], $location, $plan, $limit, $date, $code, number_format($price));
        }else{
            sendmessage($from_id, sprintf($texts['create_error'], 2), $start_key);
            exit();
        }

    } elseif ($panel['type'] == 'sanayi') {

        include_once 'api/sanayi.php';
        $xui = new Sanayi($panel['login_link'], $panel['token']);
        $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
        $create_service = $xui->addClient($name, $san_setting['inbound_id'], $date, $limit);
        $create_status = json_decode($create_service, true);
        # ---------------- check errors ---------------- #
        if ($create_status['status'] == false) {
            sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
            exit();
        }
        # ---------------- get links and subscription_url for send the user ---------------- #
        if ($info_panel->num_rows > 0) {
            $getMe = json_decode(file_get_contents("https://api.telegram.org/bot{$config['token']}/getMe"), true);
            $link = str_replace(['%s1', '%s2', '%s3'], [$create_status['results']['id'], str_replace(parse_url($panel['login_link'])['port'], json_decode($xui->getPortById($san_setting['inbound_id']), true)['port'], str_replace(['https://', 'http://'], ['', ''], $panel['login_link'])), $create_status['results']['remark']], $san_setting['example_link']);
            if ($panel['qr_code'] == 'active') {
                $encode_url = urlencode($link);
                bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => sprintf($texts['success_create_service_sanayi'], $name, $location, $date, $limit, number_format($price), $link, $create_status['results']['subscribe'], '@' . $getMe['result']['username']), 'parse_mode' => 'html', 'reply_markup' => $start_key]);
            } else {
                sendMessage($from_id, sprintf($texts['success_create_service_sanayi'], $name, $location, $date, $limit, number_format($price), $link, $create_status['results']['subscribe'], '@' . $getMe['result']['username']), $start_key);
            }
            $sql->query("INSERT INTO `orders` (`from_id`, `location`, `protocol`, `date`, `volume`, `link`, `price`, `code`, `status`, `type`) VALUES ('$from_id', '$location', 'null', '$date', '$limit', '$link', '$price', '$code', 'active', 'sanayi')");
            // sendMessage($config['dev'], sprintf($texts['success_create_notif']), $first_name, $username, $from_id, $user['count_service'], $user['coin'], $location, $plan, $limit, $date, $code, number_format($price));
        }else{
            sendMessage($from_id, sprintf($texts['create_error'], 2), $start_key);
            exit();
        }
    }
    $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
    $sql->query("UPDATE `users` SET `coin` = coin - $price, `count_service` = count_service + 1 WHERE `from_id` = '$from_id' LIMIT 1");
}

elseif ($text == '🎁 سرویس تستی (رایگان)' and $test_account_setting['status'] == 'active') {
    step('none');
    if ($user['test_account'] == 'no') {
        sendMessage($from_id, '⏳', $start_key);
        
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '{$test_account_setting['panel']}'");
        $panel_fetch = $panel->fetch_assoc();
        
        try {
            if ($panel_fetch['type'] == 'marzban') {
                # ---------------- set proxies and inbounds proccess for marzban panel ---------------- #
                $protocols = explode('|', $panel_fetch['protocols']);
                unset($protocols[count($protocols)-1]);
                if ($protocols[0] == '') unset($protocols[0]);
                $proxies = array();
                foreach ($protocols as $protocol) {
                    if ($protocol == 'vless' and $panel_fetch['flow'] == 'flowon'){
                        $proxies[$protocol] = array('flow' => 'xtls-rprx-vision');
                    } else {
                        $proxies[$protocol] = array();
                    }
                }
                
                $panel_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '{$panel_fetch['code']}'");
                $inbounds = array();
                foreach ($protocols as $protocol) {
                    while ($row = $panel_inbounds->fetch_assoc()) {
                        $inbounds[$protocol][] = $row['inbound'];
                    }
                }
                # ---------------------------------------------- #
                $code = rand(111111, 999999);
                $name = base64_encode($code) . '_' . $from_id;
                $create_service = createService($name, convertToBytes($test_account_setting['volume'].'GB'), strtotime("+ {$test_account_setting['time']} hour"), $proxies, ($panel_inbounds->num_rows > 0) ? $inbounds : 'null', $panel_fetch['token'], $panel_fetch['login_link']);
                $create_status = json_decode($create_service, true);
                if (isset($create_status['username'])) {
                    $links = "";
                    foreach ($create_status['links'] as $link) $links .= $link . "\n\n";
		    $subscribe = (strpos($create_status['subscription_url'], 'http') !== false) ? $create_status['subscription_url'] : $panel_fetch['login_link'] . $create_status['subscription_url'];
                    $sql->query("UPDATE `users` SET `count_service` = count_service + 1, `test_account` = 'yes' WHERE `from_id` = '$from_id'");
                    $sql->query("INSERT INTO `test_account` (`from_id`, `location`, `date`, `volume`, `link`, `price`, `code`, `status`) VALUES ('$from_id', '{$panel_fetch['name']}', '{$test_account_setting['date']}', '{$test_account_setting['volume']}', '$links', '0', '$code', 'active')");
                    deleteMessage($from_id, $message_id + 1);
                    sendMessage($from_id, sprintf($texts['create_test_account'], $test_account_setting['time'], $subscribe, $panel_fetch['name'], $test_account_setting['time'], $test_account_setting['volume'], base64_encode($code)), $start_key);
                } else {
                    deleteMessage($from_id, $message_id + 1);
                    sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
                }
            }

            if ($panel_fetch['type'] == 'sanayi') {
                include_once 'api/sanayi.php';
                $code = rand(111111, 999999);
                $name = base64_encode($code) . '_' . $from_id;
                $xui = new Sanayi($panel_fetch['login_link'], $panel_fetch['token']);
                $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel_fetch['code']}'")->fetch_assoc();
                $create_service = $xui->addClient($name, $san_setting['inbound_id'], $test_account_setting['volume'], ($test_account_setting['time'] / 24));
                $create_status = json_decode($create_service, true);
                $link = str_replace(['%s1', '%s2', '%s3'], [$create_status['results']['id'], str_replace(parse_url($panel_fetch['login_link'])['port'], json_decode($xui->getPortById($san_setting['inbound_id']), true)['port'], str_replace(['https://', 'http://'], ['', ''], $panel_fetch['login_link'])), $create_status['results']['remark']], $san_setting['example_link']);
                # ---------------- check errors ---------------- #
                if ($create_status['status'] == false) {
                    sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
                    exit();
                }
                # ---------------------------------------------- #
                $sql->query("UPDATE `users` SET `count_service` = count_service + 1, `test_account` = 'yes' WHERE `from_id` = '$from_id'");
                $sql->query("INSERT INTO `test_account` (`from_id`, `location`, `date`, `volume`, `link`, `price`, `code`, `status`) VALUES ('$from_id', '{$panel_fetch['name']}', '{$test_account_setting['date']}', '{$test_account_setting['volume']}', '$link', '0', '$code', 'active')");
                deleteMessage($from_id, $message_id + 1);
                sendMessage($from_id, sprintf($texts['create_test_account'], $test_account_setting['time'], $link, $panel_fetch['name'], $test_account_setting['time'], $test_account_setting['volume'], base64_encode($code)), $start_key);
            }
        } catch (\Throwable $e) {
            sendMessage($config['dev'], $e);
        }

    } else {
        sendMessage($from_id, $texts['already_test_account'], $start_key);
    }
}

elseif ($text == '🛍 سرویس های من' or $data == 'back_services') {
    $services = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id'");
    if ($services->num_rows > 0) {
        while ($row = $services->fetch_assoc()) {
            $status = ($row['status'] == 'active') ? '🟢 | ' : '🔴 | ';
            $key[] = ['text' => $status . base64_encode($row['code']) . ' - ' . $row['location'], 'callback_data' => 'service_status-'.$row['code']];
        }
        $key = array_chunk($key, 1);
        $key = json_encode(['inline_keyboard' => $key]);
        if (isset($text)) {
            sendMessage($from_id, sprintf($texts['my_services'], $services->num_rows), $key);
        } else {
        	editMessage($from_id, sprintf($texts['my_services'], $services->num_rows), $message_id, $key);
        }
    } else {
    	if (isset($text)) {
            sendMessage($from_id, $texts['my_services_not_found'], $start_key);
        } else {
        	editMessage($from_id, $texts['my_services_not_found'], $message_id, $start_key);
        }
    }
}

elseif (strpos($data, 'service_status-') !== false) {
    $code = explode('-', $data)[1];
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($panel['type'] == 'marzban') {

        $getUser = getUserInfo(base64_encode($code) . '_' . $from_id, $panel['token'], $panel['login_link']);
        if (isset($getUser['links']) and $getUser != false) {
            $links = implode("\n\n", $getUser['links']) ?? 'NULL';
            $subscribe = (strpos($getUser['subscription_url'], 'http') !== false) ? $getUser['subscription_url'] : $panel['login_link'] . $getUser['subscription_url'];
            $note = $sql->query("SELECT * FROM `notes` WHERE `code` = '$code'");

            $manage_service_btns = json_encode(['inline_keyboard' => [    
                // [['text' => 'تنظیمات دسترسی', 'callback_data' => 'access_settings-'.$code.'-marzban']],
                [['text' => 'خرید حجم اضافه', 'callback_data' => 'buy_extra_volume-'.$code.'-marzban'], ['text' => 'افزایش اعتبار زمانی', 'callback_data' => 'buy_extra_time-'.$code.'-marzban']],
                [['text' => 'نوشتن یادداشت', 'callback_data' => 'write_note-'.$code.'-marzban'], ['text' => 'دریافت QrCode', 'callback_data' => 'getQrCode-'.$code.'-marzban']],
                [['text' => '🔙 بازگشت', 'callback_data' => 'back_services']]
            ]]);
            
            if ($note->num_rows == 0) {
                editMessage($from_id, sprintf($texts['your_service'], ($getUser['status'] == 'active') ? '🟢 فعال' : '🔴 غیرفعال', $getService['location'], base64_encode($code), Conversion($getUser['used_traffic'], 'GB'), Conversion($getUser['data_limit'], 'GB'), date('Y-d-m H:i:s',  $getUser['expire']), $subscribe), $message_id, $manage_service_btns);
            } else {
                $note = $note->fetch_assoc();
                editMessage($from_id, sprintf($texts['your_service_with_note'], ($getUser['status'] == 'active') ? '🟢 فعال' : '🔴 غیرفعال', $note['note'],$getService['location'], base64_encode($code), Conversion($getUser['used_traffic'], 'GB'), Conversion($getUser['data_limit'], 'GB'), date('Y-d-m H:i:s',  $getUser['expire']), $subscribe), $message_id, $manage_service_btns);
            }
        } else {
            $sql->query("DELETE FROM `orders` WHERE `code` = '$code'");
            alert($texts['not_found_service']);
        }

    } elseif ($panel['type'] == 'sanayi') {

        include_once 'api/sanayi.php';
        $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
        $xui = new Sanayi($panel['login_link'], $panel['token']);
        $getUser = $xui->getUserInfo(base64_encode($code) . '_' . $from_id, $san_setting['inbound_id']);
        $getUser = json_decode($getUser, true);
        if ($getUser['status']) {
            $note = $sql->query("SELECT * FROM `notes` WHERE `code` = '$code'");
            $order = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
            $link = $order['link'];

            $manage_service_btns = json_encode(['inline_keyboard' => [    
                // [['text' => 'تنظیمات دسترسی', 'callback_data' => 'access_settings-'.$code.'-sanayi']],
                [['text' => 'خرید حجم اضافه', 'callback_data' => 'buy_extra_volume-'.$code.'-sanayi'], ['text' => 'افزایش اعتبار زمانی', 'callback_data' => 'buy_extra_time-'.$code.'-sanayi']],
                [['text' => 'نوشتن یادداشت', 'callback_data' => 'write_note-'.$code.'-sanayi'], ['text' => 'دریافت QrCode', 'callback_data' => 'getQrCode-'.$code.'-sanayi']],
                [['text' => '🔙 بازگشت', 'callback_data' => 'back_services']]
            ]]);

            if ($note->num_rows == 0) {
                editMessage($from_id, sprintf($texts['your_service'], ($getUser['result']['enable'] == true) ? '🟢 فعال' : '🔴 غیرفعال', $getService['location'], base64_encode($code), Conversion($getUser['result']['up'] + $getUser['result']['down'], 'GB'), ($getUser['result']['total'] == 0) ? 'نامحدود' : Conversion($getUser['result']['total'], 'GB') . ' MB', date('Y-d-m H:i:s',  $getUser['result']['expiryTime']), $link), $message_id, $manage_service_btns);
            } else {
                $note = $note->fetch_assoc();
                editMessage($from_id, sprintf($texts['your_service_with_note'], ($getUser['result']['enable'] == true) ? '🟢 فعال' : '🔴 غیرفعال', $note['note'], $getService['location'], base64_encode($code), Conversion($getUser['result']['up'] + $getUser['result']['down'], 'GB'), ($getUser['result']['total'] == 0) ? 'نامحدود' : Conversion($getUser['result']['total'], 'GB') . ' MB', date('Y-d-m H:i:s',  $getUser['result']['expiryTime']), $link), $message_id, $manage_service_btns);
            }
        } else {
            $sql->query("DELETE FROM `orders` WHERE `code` = '$code'");
            alert($texts['not_found_service']);
        }

    }
}

elseif (strpos($data, 'getQrCode') !== false) {
    alert($texts['wait']);

    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($type == 'marzban') {
        $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
        $getUser = getUserInfo(base64_encode($code) . '_' . $from_id, $token, $panel['login_link']);
        if (isset($getUser['links']) and $getUser != false) {
            $subscribe = (strpos($getUser['subscription_url'], 'http') !== false) ? $getUser['subscription_url'] : $panel['login_link'] . $getUser['subscription_url'];
            $encode_url = urldecode($subscribe);
            bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => "<code>$subscribe</code>", 'parse_mode' => 'html']);
        } else {
            alert('❌ Error', true);
        }
    } elseif ($type == 'sanayi') {
        $order = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
        $link = $order['link'];
        $encode_url = urlencode($link);
        bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => "<code>$link</code>", 'parse_mode' => 'html']);
    } else {
        alert('❌ Error -> not found type !', true);
    }
}

elseif (strpos($data, 'write_note') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    step('set_note-'.$code.'-'.$type);
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, sprintf($texts['send_note'], $code), $back);
}

elseif (strpos($user['step'], 'set_note') !== false) {
    $code = explode('-', $user['step'])[1];
    $type = explode('-', $user['step'])[2];
    if ($sql->query("SELECT `code` FROM `notes` WHERE `code` = '$code'")->num_rows == 0) {
        $sql->query("INSERT INTO `notes` (`note`, `code`, `type`, `status`) VALUES ('$text', '$code', '$type', 'active')");
    } else {
        $sql->query("UPDATE `notes` SET `note` = '$text' WHERE `code` = '$code'");
    }
    sendMessage($from_id, sprintf($texts['set_note_success'], $code), $start_key);
}

elseif (strpos($data, 'buy_extra_time') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $category_date = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'");

    if ($category_date->num_rows > 0) {
        while ($row = $category_date->fetch_assoc()) {
            $key[] = ['text' => $row['name'], 'callback_data' => 'select_extra_time-'.$row['code'].'-'.$code];
        }
        $key = array_chunk($key, 2);
        $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'service_status-'.$code]];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, sprintf($texts['select_extra_time_plan'], $code), $message_id, $key);
    } else {
        alert($texts['not_found_plan_extra_time'], true);
    }
}

elseif (strpos($data, 'buy_extra_volume') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $category_limit = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'");

    if ($category_limit->num_rows > 0) {
        while ($row = $category_limit->fetch_assoc()) {
            $key[] = ['text' => $row['name'], 'callback_data' => 'select_extra_volume-'.$row['code'].'-'.$code];
        }
        $key = array_chunk($key, 2);
        $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'service_status-'.$code]];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, sprintf($texts['select_extra_volume_plan'], $code), $message_id, $key);
    } else {
        alert($texts['not_found_plan_extra_volume'], true);
    }
}

elseif ($data == 'cancel_buy') {
    step('none');
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, $texts['cancel_extra_factor'], $start_key);
}

elseif (strpos($data, 'select_extra_time') !== false) {
    $service_code = explode('-', $data)[2];
    $plan_code = explode('-', $data)[1];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$plan_code'")->fetch_assoc();
    
    $access_key = json_encode(['inline_keyboard' => [
        [['text' => '❌ لغو', 'callback_data' => 'cancel_buy'], ['text' => '✅ تایید', 'callback_data' => 'confirm_extra_time-'.$service_code.'-'.$plan_code]],
    ]]);
    
    editMessage($from_id, sprintf($texts['create_buy_extra_time_factor'], $service_code, $service_code, $plan['name'], number_format($plan['price']), $service_code), $message_id, $access_key);
}

elseif (strpos($data, 'confirm_extra_time') !== false) {
    alert($texts['wait']);
    $service_code = explode('-', $data)[1];
    $plan_code = explode('-', $data)[2];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$plan_code'")->fetch_assoc();
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($user['coin'] >= $plan['price']) {
        if ($service['type'] == 'marzban') {
            $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
            $getUser = getUserInfo(base64_encode($service_code) . '_' . $from_id, $token, $panel['login_link']);
            $response = Modifyuser(base64_encode($service_code) . '_' . $from_id, array('expire' => $getUser['expire'] += 86400 * $plan['date']), $token, $panel['login_link']);
        } elseif ($service['type'] == 'sanayi') {
            include_once 'api/sanayi.php';
            $panel_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
            $xui = new Sanayi($panel['login_link'], $panel['token']);
            $getUser = $xui->getUserInfo(base64_encode($service_code) . '_' . $from_id, $panel_setting['inbound_id']);
            $getUser = json_decode($getUser, true);
            if ($getUser['status'] == true) {
                $response = $xui->addExpire(base64_encode($service_code) . '_' . $from_id, $plan['date'], $panel_setting['inbound_id']);
                // sendMessage($from_id, $response);
            } else {
                alert('❌ Error --> not found service');
            }
        }

        $sql->query("UPDATE `users` SET `coin` = coin - {$plan['price']} WHERE `from_id` = '$from_id'");
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, sprintf($texts['success_extra_time'], $plan['date'], $plan['name'], number_format($plan['price'])), $start_key);
    } else {
        alert($texts['not_coin_extra'], true);
    }
}

elseif (strpos($data, 'select_extra_volume') !== false) {
    $service_code = explode('-', $data)[2];
    $plan_code = explode('-', $data)[1];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$plan_code'")->fetch_assoc();
    
    $access_key = json_encode(['inline_keyboard' => [
        [['text' => '❌ لغو', 'callback_data' => 'cancel_buy'], ['text' => '✅ تایید', 'callback_data' => 'confirm_extra_volume-'.$service_code.'-'.$plan_code]],
    ]]);
    
    editMessage($from_id, sprintf($texts['create_buy_extra_volume_factor'], $service_code, $service_code, $plan['name'], number_format($plan['price']), $service_code), $message_id, $access_key);
}

elseif (strpos($data, 'confirm_extra_volume') !== false) {
    alert($texts['wait']);
    $service_code = explode('-', $data)[1];
    $plan_code = explode('-', $data)[2];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$plan_code'")->fetch_assoc();
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($user['coin'] >= $plan['price']) {
        if ($service['type'] == 'marzban') {
            $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
            $getUser = getUserInfo(base64_encode($service_code) . '_' . $from_id, $token, $panel['login_link']);
            $response = Modifyuser(base64_encode($service_code) . '_' . $from_id, array('data_limit' => $getUser['data_limit'] += $plan['limit'] * pow(1024, 3)), $token, $panel['login_link']);
        } elseif ($service['type'] == 'sanayi') {
            include_once 'api/sanayi.php';
            $panel_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
            $xui = new Sanayi($panel['login_link'], $panel['token']);
            $getUser = $xui->getUserInfo(base64_encode($service_code) . '_' . $from_id, $panel_setting['inbound_id']);
            $getUser = json_decode($getUser, true);
            if ($getUser['status'] == true) {
                $response = $xui->addVolume(base64_encode($service_code) . '_' . $from_id, $plan['limit'], $panel_setting['inbound_id']);
            } else {
                alert('❌ Error --> not found service');
            }
        }

        $sql->query("UPDATE `users` SET `coin` = coin - {$plan['price']} WHERE `from_id` = '$from_id'");
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, sprintf($texts['success_extra_volume'], $plan['limit'], $plan['name'], number_format($plan['price'])), $start_key);
    } else {
        alert($texts['not_coin_extra'], true);
    }
}

elseif ($text == '💸 شارژ حساب') {
    if ($auth_setting['status'] == 'active') {
        if ($auth_setting['iran_number'] == 'active' or $auth_setting['virtual_number'] == 'active' or $auth_setting['both_number'] == 'active') {
            if (is_null($user['phone'])) {
                step('authentication');
                sendMessage($from_id, $texts['send_phone'], $send_phone);
            } else {
                step('diposet');
                sendMessage($from_id, $texts['diposet'], $back);
            }
        } else {
            step('diposet');
            sendMessage($from_id, $texts['diposet'], $back);
        }
    } else {
        step('diposet');
        sendMessage($from_id, $texts['diposet'], $back);
    }
}

elseif ($user['step'] == 'authentication') {
    $contact = $update->message->contact;
    if (isset($contact)) {
        if ($contact->user_id == $from_id) {
            if ($auth_setting['iran_number'] == 'active') {
                if (strpos($contact->phone_number, '+98') !== false) {
                    $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                    sendMessage($from_id, $texts['send_phone_success'], $start_key);
                } else {
                    sendMessage($from_id, $texts['only_iran'], $back);
                }
            } elseif ($auth_setting['virtual_number'] == 'active') {
                if (strpos($contact->phone_number, '+98') === false) {
                    $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                    sendMessage($from_id, $texts['send_phone_success'], $start_key);
                } else {
                    sendMessage($from_id, $texts['only_virtual'], $back);
                }
            } elseif ($auth_setting['both_number'] == 'active') {
                $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                sendMessage($from_id, $texts['send_phone_success'], $start_key);   
            }
        } else {
            sendMessage($from_id, $texts['send_phone_with_below_btn'], $send_phone);    
        }
    } else {
        sendMessage($from_id, $texts['send_phone_with_below_btn'], $send_phone);
    }
}

elseif ($user['step'] == 'diposet') {
    if (is_numeric($text) and $text >= 2000) {
        step('sdp-' . $text);
        sendMessage($from_id, sprintf($texts['select_diposet_payment'], number_format($text)), $select_diposet_payment);
    } else {
        sendMessage($from_id, $texts['diposet_input_invalid'], $back);
    }
}

elseif ($data == 'cancel_payment_proccess') {
    step('none');
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif (in_array($data, ['zarinpal', 'idpay']) and strpos($user['step'], 'sdp-') !== false) {
    if ($payment_setting[$data . '_status'] == 'active') {
        $status = $sql->query("SELECT `{$data}_token` FROM `payment_setting`")->fetch_assoc()[$data . '_token'];
        if ($status != 'none') {
            step('none');
            $price = explode('-', $user['step'])[1];
            $code = rand(11111111, 99999999);
            $sql->query("INSERT INTO `factors` (`from_id`, `price`, `code`, `status`) VALUES ('$from_id', '$price', '$code', 'no')");
            $response = ($data == 'zarinpal') ? zarinpalGenerator($from_id, $price, $code) : idpayGenerator($from_id, $price, $code);
            if ($response) $pay = json_encode(['inline_keyboard' => [[['text' => '💵 پرداخت', 'url' => $response]]]]);
            deleteMessage($from_id, $message_id);
            sendMessage($from_id, sprintf($texts['create_diposet_factor'], $code, number_format($price)), $pay);
            sendMessage($from_id, $texts['back_to_menu'], $start_key);
        } else {
            alert($texts['error_choice_pay']);
        }
    } else {
        alert($texts['not_active_payment']);
    }
}

elseif ($data == 'nowpayment' and strpos($user['step'], 'sdp-') !== false) {
    if ($payment_setting[$data . '_status'] == 'active') {
        alert('⏱ لطفا چند ثانیه صبر کنید.');
        if ($payment_setting[$data . '_status'] == 'active') {
            $code = rand(111111, 999999);
            $price = explode('-', $user['step'])[1];
            $dollar = json_decode(file_get_contents($config['domain'] . '/api/arz.php'), true)['price'];
            $response_gen = nowPaymentGenerator((intval($price) / intval($dollar)), 'usd', 'trx', $code);
            if (!is_null($response_gen)) {
                $response = json_decode($response_gen, true);
                $sql->query("INSERT INTO `factors` (`from_id`, `price`, `code`, `status`) VALUES ('$from_id', '$price', '{$response['payment_id']}', 'no')");
                $key = json_encode(['inline_keyboard' => [[['text' => '✅ پرداخت کردم', 'callback_data' => 'checkpayment-' . $response['payment_id']]]]]);
                deleteMessage($from_id, $message_id);
                sendMessage($from_id, sprintf($texts['create_nowpayment_factor'], $response['payment_id'], number_format($price), number_format($dollar), $response['pay_amount'], $response['pay_address']), $key);
                sendMessage($from_id, $texts['back_to_menu'], $start_key);
            } else {
                deleteMessage($from_id, $message_id);
                sendMessage($from_id, $texts['error_nowpayment'] . "\n◽- <code>USDT: $dollar</code>", $start_key);
            }
        } else {
            alert($texts['not_active_payment']);
        }
    } else {
        alert($texts['not_active_payment']);
    }
}

elseif (strpos($data, 'checkpayment') !== false) {
    $payment_id = explode('-', $data)[1];
    $get = checkNowPayment($payment_id);
    $status = json_decode($get, true)['payment_status'];
    if ($status != 'waiting') {
        $factor = $sql->query("SELECT * FROM `factors` WHERE `code` = '$payment_id'")->fetch_assoc();
        if ($factor['status'] == 'no') {
            $sql->query("UPDATE `users` SET `coin` = coin + {$factor['price']}, `count_charge` = count_charge + 1 WHERE `from_id` = '$from_id'");
            $sql->query("UPDATE `factors` SET `status` = 'yes' WHERE `code` = '$payment_id'");
            deleteMessage($from_id, $message_id);
            sendMessage($from_id, sprintf($texts['success_nowpayment'], number_format($factor['price'])), $start_key);
            // sendMessage($config['dev'], $texts['success_payment_notif']);
        } else {
            alert($texts['not_success_nowpayment']);
        }
    } else {
        alert($texts['not_success_nowpayment']);
    }
}

elseif ($data == 'kart') {
	if ($payment_setting['card_status'] == 'active') {
	    $price = explode('-', $user['step'])[1];
	    step('send_fish-'.$price);
	    $code = rand(11111111, 99999999);
	    $card_number = $sql->query("SELECT `card_number` FROM `payment_setting`")->fetch_assoc()['card_number'];
	    $card_number_name = $sql->query("SELECT `card_number_name` FROM `payment_setting`")->fetch_assoc()['card_number_name'];
	    deleteMessage($from_id, $message_id);
	    sendMessage($from_id, sprintf($texts['create_kart_factor'], $code, number_format($price), ($card_number != 'none') ? $card_number : '❌ تنظیم نشده', ($card_number_name != 'none') ? $card_number_name : ''), $back);
	} else {
        alert($texts['not_active_payment']);
    }
}

elseif (strpos($user['step'], 'send_fish') !== false) {
    $price = explode('-', $user['step'])[1];
    if (isset($update->message->photo)) {
        step('none');
        $key = json_encode(['inline_keyboard' => [[['text' => '❌', 'callback_data' => 'cancel_fish-'.$from_id], ['text' => '✅', 'callback_data' => 'accept_fish-'.$from_id.'-'.$price]]]]);
        sendMessage($from_id, $texts['success_send_fish'], $start_key);
        sendMessage($config['dev'], sprintf($texts['success_send_fish_notif'], $from_id, $username, $price), $key);
        forwardMessage($from_id, $config['dev'], $message_id);
        if (!is_null($settings['log_channel'])) {
            sendMessage($settings['log_channel'], sprintf($texts['success_send_fish_notif'], $from_id, $username, $price));
            forwardMessage($from_id, $settings['log_channel'], $message_id);
        }
    } else {
        sendMessage($from_id, $texts['error_input_kart'], $back);
    }
}

elseif ($text == '🛒 تعرفه خدمات') {
    sendMessage($from_id, $texts['service_tariff']);
}

elseif ($text == '👤 پروفایل') {
    $count_all = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id'")->num_rows;
    $count_all_active = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id' AND `status` = 'active'")->num_rows;
    $count_all_inactive = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id' AND `status` = 'inactive'")->num_rows;
    sendMessage($from_id, sprintf($texts['my_account'], $from_id, number_format($user['coin']), $count_all, $count_all_active, $count_all_inactive), $start_key);
}

elseif ($text == '📮 پشتیبانی آنلاین') {
    step('support');
    sendMessage($from_id, $texts['support'], $back);
}

elseif ($user['step'] == 'support') {
    step('none');
    sendMessage($from_id, $texts['success_support'], $start_key);
    sendMessage($config['dev'], sprintf($texts['new_support_message'], $from_id, $from_id, $username, $user['coin']), $manage_user);
    forwardMessage($from_id, $config['dev'], $message_id);
}

elseif ($text == '🔗 راهنمای اتصال') {
	step('select_sys');
	sendMessage($from_id, $texts['select_sys'], $education);
}

elseif (strpos($data, 'edu') !== false) {
	$sys = explode('_', $data)[1];
	deleteMessage($from_id, $message_id);
	sendMessage($from_id, $texts['edu_'.$sys], $education);
}
# ------------ panel ------------ #

$admins = $sql->query("SELECT * FROM `admins`")->fetch_assoc() ?? [];
if ($from_id == $config['dev'] or in_array($from_id, $admins)) {
    if (in_array($text, ['/panel', 'panel', '🔧 مدیریت', 'پنل', '⬅️ بازگشت به مدیریت'])) {
        step('panel');
        sendMessage($from_id, "👮‍♂️ - سلام ادمین [ <b>$first_name</b> ] عزیز !\n\n⚡️به پنل مدیریت ربات خوش آمدید.\n🗃 ورژن فعلی ربات : <code>{$config['version']}</code>\n\n⚙️ جهت مدیریت ربات ، یکی از گزینه های زیر را انتخاب کنید.\n\n🐝 | برای اطلاع از تمامی آپدیت ها و نسخه های بعدی ربات زنبور پنل در کانال زنبور پنل عضو شید :↓\n◽️@ZanborPanel\n🐝 و همچنین برای نظر دهی آپدیت یا باگ ها به گروه زنبور پنل بپیوندید :↓\n◽️@ZanborPanelGap", $panel);    
    }
    
    elseif($text == '👥 مدیریت آمار ربات'){
        sendMessage($from_id, "👋 به مدیریت آمار کلی ربات خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید:\n\n◽️@ZanborPanel", $manage_statistics);
    }
    
    elseif($text == '🌐 مدیریت سرور'){
        sendMessage($from_id, "⚙️ به مدیریت پلن ها خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_server);
    }
    
    elseif($text == '👤 مدیریت کاربران'){
        sendMessage($from_id, "👤 به مدیریت کاربران خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_user);
    }
    
    elseif($text == '📤 مدیریت پیام'){
        sendMessage($from_id, "📤 به مدیریت پیام خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_message);
    }
    
    elseif($text == '👮‍♂️مدیریت ادمین'){
        sendMessage($from_id, "👮‍♂️ به مدیریت ادمین خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_admin);
    }
    
    elseif($text == '⚙️ تنظیمات'){
        sendMessage($from_id, "⚙️️ به تنظیمات ربات خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_setting);
    }
    
    
    // ----------- do not touch this part ----------- //
    elseif ($text == base64_decode('YmFzZTY0X2RlY29kZQ==')('8J+TniDYp9i32YTYp9i524zZhyDYotm+2K/bjNiqINix2KjYp9iq')) {
        base64_decode('c2VuZE1lc3NhZ2U=')($from_id, base64_decode('8J+QnSB8INio2LHYp9uMINin2LfZhNin2Lkg2KfYsiDYqtmF2KfZhduMINii2b7Yr9uM2Kog2YfYpyDZiCDZhtiz2K7ZhyDZh9in24wg2KjYudiv24wg2LHYqNin2Kog2LLZhtio2YjYsSDZvtmG2YQg2K/YsSDaqdin2YbYp9mEINiy2YbYqNmI2LEg2b7ZhtmEINi52LbZiCDYtNuM2K8gOuKGkwril73vuI9AWmFuYm9yUGFuZWwK8J+QnSB8INmIINmH2YXahtmG24zZhiDYqNix2KfbjCDZhti42LEg2K/Zh9uMINii2b7Yr9uM2Kog24zYpyDYqNin2q8g2YfYpyDYqNmHINqv2LHZiNmHINiy2YbYqNmI2LEg2b7ZhtmEINio2b7bjNmI2YbYr9uM2K8gOuKGkwril73vuI9AWmFuYm9yUGFuZWxHYXAK8J+QnSB8INmG2YXZiNmG2Ycg2LHYqNin2Kog2KLYrtix24zZhiDZhtiz2K7ZhyDYsdio2KfYqiDYstmG2KjZiNixINm+2YbZhCA64oaTCuKXve+4j0BaYW5ib3JQYW5lbEJvdA=='), $panel);
    }
    
    // ----------- manage auth ----------- //
    elseif ($text == '🔑 سیستم احراز هویت' or $data == 'manage_auth') {
        if (isset($text)) {
            sendMessage($from_id, "🀄️ به بخش سیستم احراز هویت ربات خوش آمدید !\n\n📚 راهنمای این بخش :↓\n\n🟢 : فعال \n🔴 : غیرفعال", $manage_auth);
        } else {
            editMessage($from_id, "🀄️ به بخش سیستم احراز هویت ربات خوش آمدید !\n\n📚 راهنمای این بخش :↓\n\n🟢 : فعال \n🔴 : غیرفعال", $message_id, $manage_auth);
        }
    }

    elseif ($data == 'change_status_auth') {
        if ($auth_setting['status'] == 'active') {
            $sql->query("UPDATE `auth_setting` SET `status` = 'inactive'");
        } else {
            $sql->query("UPDATE `auth_setting` SET `status` = 'active'");
        }
        alert('✅ تغییرات با موفقیت انجام شد.', true);
        editMessage($from_id, "🆙 برای آپدیت تغییرات بر روی دکمه زیر کلیک کنید !", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 آپدیت تغییرات', 'callback_data' => 'manage_auth']]]]));
    }

    elseif ($data == 'change_status_auth_iran') {
        if ($auth_setting['status'] == 'active') {
            if ($auth_setting['virtual_number'] == 'inactive' and $auth_setting['both_number'] == 'inactive') {
                if ($auth_setting['iran_number'] == 'active') {
                    $sql->query("UPDATE `auth_setting` SET `iran_number` = 'inactive'");
                } else {
                    $sql->query("UPDATE `auth_setting` SET `iran_number` = 'active'");
                }
                alert('✅ تغییرات با موفقیت انجام شد.', true);
                editMessage($from_id, "🆙 برای آپدیت تغییرات بر روی دکمه زیر کلیک کنید !", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 آپدیت تغییرات', 'callback_data' => 'manage_auth']]]]));
            } else {
                alert('⚠️ برای فعال کردن سیستم احراز هویت شماره های ایرانی باید بخش ( 🏴󠁧󠁢󠁥󠁮󠁧󠁿 شماره مجازی ) و ( 🌎 همه شماره ها ) غیرفعال شود !', true);
            }
        } else {
            alert('🔴 برای فعال سازی این بخش ابتدا باید ( ℹ️ سیستم احراز هویت ) را فعال کنید !', true);
        }
    }

    elseif ($data == 'change_status_auth_virtual') {
        if ($auth_setting['status'] == 'active') {
            if ($auth_setting['iran_number'] == 'inactive' and $auth_setting['both_number'] == 'inactive') {
                if ($auth_setting['virtual_number'] == 'active') {
                    $sql->query("UPDATE `auth_setting` SET `virtual_number` = 'inactive'");
                } else {
                    $sql->query("UPDATE `auth_setting` SET `virtual_number` = 'active'");
                }
                alert('✅ تغییرات با موفقیت انجام شد.', true);
                editMessage($from_id, "🆙 برای آپدیت تغییرات بر روی دکمه زیر کلیک کنید !", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 آپدیت تغییرات', 'callback_data' => 'manage_auth']]]]));
            } else {
                alert('⚠️ برای فعال کردن سیستم احراز هویت شماره های مجازی باید بخش ( 🇮🇷 شماره ایران ) و ( 🌎 همه شماره ها ) غیرفعال شود !', true);
            }
        } else {
            alert('🔴 برای فعال سازی این بخش ابتدا باید ( ℹ️ سیستم احراز هویت ) را فعال کنید !', true);
        }
    }

    elseif ($data == 'change_status_auth_all_country') {
        if ($auth_setting['status'] == 'active') {
            if ($auth_setting['iran_number'] == 'inactive' and $auth_setting['virtual_number'] == 'inactive') {
                if ($auth_setting['both_number'] == 'active') {
                    $sql->query("UPDATE `auth_setting` SET `both_number` = 'inactive'");
                } else {
                    $sql->query("UPDATE `auth_setting` SET `both_number` = 'active'");
                }
                alert('✅ تغییرات با موفقیت انجام شد.', true);
                editMessage($from_id, "🆙 برای آپدیت تغییرات بر روی دکمه زیر کلیک کنید !", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 آپدیت تغییرات', 'callback_data' => 'manage_auth']]]]));
            } else {
                alert('⚠️ برای فعال کردن سیستم احراز هویت همه شماره ها باید بخش ( 🇮🇷 شماره ایران ) و ( 🏴󠁧󠁢󠁥󠁮󠁧󠁿 شماره مجازی ) غیرفعال شود !', true);
            }
        } else {
            alert('🔴 برای فعال سازی این بخش ابتدا باید ( ℹ️ سیستم احراز هویت ) را فعال کنید !', true);
        }
    }
    // ----------- manage status ----------- //
    elseif($text == '👤 آمار ربات'){
        $state1 = $sql->query("SELECT `status` FROM `users`")->num_rows;
        $state2 = $sql->query("SELECT `status` FROM `users` WHERE `status` = 'inactive'")->num_rows;
        $state3 = $sql->query("SELECT `status` FROM `users` WHERE `status` = 'active'")->num_rows;
        $state4 = $sql->query("SELECT `status` FROM `factors` WHERE `status` = 'yes'")->num_rows;
        sendMessage($from_id, "⚙️ آمار ربات شما به شرح زیر می‌باشد :↓\n\n▫️تعداد کل کاربر ربات : <code>$state1</code> عدد\n▫️تعداد کاربر های مسدود : <code>$state2</code> عدد\n▫️تعداد کاربر های آزاد : <code>$state3</code> عدد\n\n🔢 تعداد کل پرداختی : <code>$state4</code> عدد\n\n🤖 @ZanborPanel", $manage_statistics);
    }
    
    // ----------- manage servers ----------- //
    elseif ($text == '❌ انصراف و بازگشت') {
        step('none');
        if (file_exists('add_panel.txt')) unlink('add_panel.txt');
        sendMessage($from_id, "⚙️ به مدیریت پلن ها خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :\n\n◽️@ZanborPanel", $manage_server);
    }
    
    elseif ($data == 'close_panel') {
        step('none');
        editMessage($from_id, "✅ پنل مدیریت سرور ها با موفقیت بسته شد !", $message_id);
    }
    
    elseif ($text == '⏱ مدیریت اکانت تست' or $data == 'back_account_test') {
        step('none');
        // sendMessage($from_id, "{$test_account_setting['status']} - {$test_account_setting['panel']} - {$test_account_setting['volume']} - {$test_account_setting['time']}");
        // exit();
        if (isset($text)) {
            sendMessage($from_id, "⏱ به تنظیمات اکانت تست خوش آمدید.\n\n🟢 حجم را به صورت GB به ربات ارسال کنید | برای مثال 200 مگ : 0.2\n🟢 زمان را به صورت ساعت ارسال کنید | برای مثال 5 ساعت : 5\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :\n◽️@ZanborPanel", $manage_test_account);
        } else {
            editMessage($from_id, "⏱ به تنظیمات اکانت تست خوش آمدید.\n\n🟢 حجم را به صورت GB به ربات ارسال کنید | برای مثال 200 مگ : 0.2\n🟢 زمان را به صورت ساعت ارسال کنید | برای مثال 5 ساعت : 5\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :\n◽️@ZanborPanel", $message_id, $manage_test_account);
        }
    }
    
    elseif ($data == 'null') {
        alert('#️⃣ این دکمه نمایشی است !');
    }
    
    elseif ($data == 'change_test_account_status') {
        $status = $sql->query("SELECT `status` FROM `test_account_setting`")->fetch_assoc()['status'];
        if($status == 'active'){
            $sql->query("UPDATE `test_account_setting` SET `status` = 'inactive'");
        }else{
            $sql->query("UPDATE `test_account_setting` SET `status` = 'active'");
        }
        $manage_test_account = json_encode(['inline_keyboard' => [
            [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_test_account_status'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
            [['text' => ($test_account_setting['panel'] == 'none') ? '🔴 وصل نیست' : '🟢 وصل است', 'callback_data' => 'change_test_account_panel'], ['text' => '▫️متصل به پنل :', 'callback_data' => 'null']],
            [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️تعداد اکانت تست :', 'callback_data' => 'null']],
            [['text' => $test_account_setting['volume'] . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️حجم :', 'callback_data' => 'null']],
            [['text' => $test_account_setting['time'] . ' ساعت', 'callback_data' => 'change_test_account_time'], ['text' => '▫️زمان :', 'callback_data' => 'null']],
        ]]);
        editMessage($from_id, "⏱ به تنظیمات اکانت تست خوش آمدید.\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :\n◽️@ZanborPanel", $message_id, $manage_test_account);
    }
    
    elseif ($data == 'change_test_account_volume') {
        step('change_test_account_volume');
        editMessage($from_id, "🆕 مقدار جدید را به صورت عدد صحیح ارسال کنید :", $message_id, $back_account_test);
    }
    
    elseif ($user['step'] == 'change_test_account_volume') {
        if (isset($text)) {
            if (is_numeric($text)) {
                step('none');
                $sql->query("UPDATE `test_account_setting` SET `volume` = '$text'");
                $manage_test_account = json_encode(['inline_keyboard' => [
                    [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_test_account_status'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
                    [['text' => ($test_account_setting['panel'] == 'none') ? '🔴 وصل نیست' : '🟢 وصل است', 'callback_data' => 'change_test_account_panel'], ['text' => '▫️متصل به پنل :', 'callback_data' => 'null']],
                    [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️تعداد اکانت تست :', 'callback_data' => 'null']],
                    [['text' => $text . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️حجم :', 'callback_data' => 'null']],
                    [['text' => $test_account_setting['time'] . ' ساعت', 'callback_data' => 'change_test_account_time'], ['text' => '▫️زمان :', 'callback_data' => 'null']],
                ]]);
                sendMessage($from_id, "✅ عملیات تغییرات با موفقیت انجام شد.\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید .\n◽️@ZanborPanel", $manage_test_account);
            } else {
                sendMessage($from_id, "❌ ورودی ارسالی اشتباه است !", $back_account_test);
            }
        }
    }
    
    elseif ($data == 'change_test_account_time') {
        step('change_test_account_time');
        editMessage($from_id, "🆕 مقدار جدید را به صورت عدد صحیح ارسال کنید :", $message_id, $back_account_test);
    }
    
    elseif ($user['step'] == 'change_test_account_time') {
        if (isset($text)) {
            if (is_numeric($text)) {
                step('none');
                $sql->query("UPDATE `test_account_setting` SET `time` = '$text'");
                $manage_test_account = json_encode(['inline_keyboard' => [
                    [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_test_account_status'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
                    [['text' => ($test_account_setting['panel'] == 'none') ? '🔴 وصل نیست' : '🟢 وصل است', 'callback_data' => 'change_test_account_panel'], ['text' => '▫️متصل به پنل :', 'callback_data' => 'null']],
                    [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️تعداد اکانت تست :', 'callback_data' => 'null']],
                    [['text' => $test_account_setting['volume'] . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️حجم :', 'callback_data' => 'null']],
                    [['text' => $text . ' ساعت', 'callback_data' => 'change_test_account_time'], ['text' => '▫️زمان :', 'callback_data' => 'null']],
                ]]);
                sendMessage($from_id, "✅ عملیات تغییرات با موفقیت انجام شد.\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید .\n◽️@ZanborPanel", $manage_test_account);
            } else {
                sendMessage($from_id, "❌ ورودی ارسالی اشتباه است !", $back_account_test);
            }
        }
    }
    
    elseif ($data == 'change_test_account_panel') {
        $panels = $sql->query("SELECT * FROM `panels`");
        if ($panels->num_rows > 0) {
            step('change_test_account_panel');
            while ($row = $panels->fetch_assoc()) {
                $key[] = [['text' => $row['name'], 'callback_data' => 'select_test_panel-'.$row['code']]];
            }
            $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'back_account_test']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "🔧 یکی از پنل های زیر را برای بخش تست اکانت انتخاب کنید :", $message_id, $key);
        } else {
            alert('❌ هیچ پنلی در ربات ثبت نشده است !');
        }
    }
    
    elseif (strpos($data, 'select_test_panel-') !== false) {
        $code = explode('-', $data)[1];
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'");
        if ($panel->num_rows > 0) {
            $sql->query("UPDATE `test_account_setting` SET `panel` = '$code'");
            $panel = $panel->fetch_assoc();
            $manage_test_account = json_encode(['inline_keyboard' => [
                [['text' => ($test_account_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_test_account_status'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
                [['text' => $panel['name'], 'callback_data' => 'change_test_account_panel'], ['text' => '▫️متصل به پنل :', 'callback_data' => 'null']],
                [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️تعداد اکانت تست :', 'callback_data' => 'null']],
                [['text' => $test_account_setting['volume'] . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️حجم :', 'callback_data' => 'null']],
                [['text' => $test_account_setting['time'] . ' ساعت', 'callback_data' => 'change_test_account_time'], ['text' => '▫️زمان :', 'callback_data' => 'null']],
            ]]);
            editMessage($from_id, "✅ عملیات تغییرات با موفقیت انجام شد.\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید .\n◽️@ZanborPanel", $message_id, $manage_test_account);
        } else {
            alert('❌ پنل مورد نظر یافت نشد !');
        }
    }
    
    elseif  ($text == '➕ افزودن سرور') {
        step('add_server_select');
        sendMessage($from_id, "ℹ️ قصد اضافه کردن کدام یک از پنل های زیر را دارید ؟", $select_panel);
    }

    # ------------- hedifay ------------- #
    elseif ($data == 'hedifay') {
        alert('❌ در حال تکمیل کردن این بخش هستیم لطفا صبور باشید !', true);
        exit();
        // step('add_server_hedifay');
        // deleteMessage($from_id, $message_id);
        // sendMessage($from_id, "‌👈🏻⁩ اسم پنل خود را به دلخواه ارسال کنید :↓\n\nمثال نام : 🇳🇱 - هلند\n• این اسم برای کاربران قابل نمایش است.", $cancel_add_server);
    }

    elseif ($user['step'] == 'add_server_hedifay') {
        if ($sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'")->num_rows == 0) {
            step('send_address_hedifay');
            file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🌐 آدرس لاگین به پنل را ارسال کنید.\n\n- example:\n\n<code>https://1.1.1.1.sslip.io/8itQkDU30qCOwzUkK3LnMf58qfsw/175dbb13-95d7-3807-a987-gbs3434bd1b412/admin</code>", $cancel_add_server);
        } else {
            sendMessage($from_id, "❌ پنلی با نام [ <b>$text</b> ] قبلا در ربات ثبت شده !", $cancel_add_server);
        }
    }

    elseif ($user['step'] == 'send_address_hedifay') {
        if (strlen($text) > 50 and substr($text, -1) != '/') {
            if (checkUrl($text) == 200) {
                $info = explode("\n", file_get_contents('add_panel.txt'));
                preg_match('#https:\/\/.*?\/(.*)\/admin#', $text, $matches);
                $token = $matches[1];
                $code = rand(111111, 999999);
                $sql->query("INSERT INTO `hiddify_panels` (`name`, `login_link`, `token`, `code`, `status`, `type`) VALUES ('{$info[0]}', '$text', '$token', '$code', 'active', 'hiddify')");
                sendMessage($from_id, "✅ پنل هیدیفای  شما با موفقیت به ربات اضافه شد !", $manage_server);
            }
        } else {
            sendMessage($from_id, "❌ آدرس ارسالی شما اشتباه است !", $cancel_add_server);
        }
    }

    # ------------- sanayi ------------- #

    elseif ($data == 'sanayi') {
        step('add_server_sanayi');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "‌👈🏻⁩ اسم پنل خود را به دلخواه ارسال کنید :↓\n\nمثال نام : 🇳🇱 - هلند\n• این اسم برای کاربران قابل نمایش است.", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'add_server_sanayi') {
        if ($sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'")->num_rows == 0) {
            step('send_address_sanayi');
            file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🌐 آدرس لاگین به پنل را ارسال کنید.\n\n- example:\n http://1.1.1.1:8000\n http://1.1.1.1:8000/vrshop\n http://domain.com:8000", $cancel_add_server);
        } else {
            sendMessage($from_id, "❌ پنلی با نام [ <b>$text</b> ] قبلا در ربات ثبت شده !", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_address_sanayi') {
        if (preg_match("/^(http|https):\/\/(\d+\.\d+\.\d+\.\d+|.*)\:.*$/", $text)) {
            if ($sql->query("SELECT `login_link` FROM `panels` WHERE `login_link` = '$text'")->num_rows == 0) {
                step('send_username_sanayi');
                file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
                sendMessage($from_id, "🔎 - یوزرنیم ( <b>username</b> ) پنل خود را ارسال کنید :", $cancel_add_server);
            } else {
            sendMessage($from_id, "❌ پنلی با ادرس [ <b>$text</b> ] قبلا در ربات ثبت شده !", $cancel_add_server);
        }
        } else {
            sendMessage($from_id, "🚫 لینک ارسالی شما اشتباه است !", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_username_sanayi') {
        step('send_password_sanayi');
        file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔎 - پسورد ( <b>password</b> ) سرور خود را ارسال کنید :", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'send_password_sanayi') {
        step('none');
        $info = explode("\n", file_get_contents('add_panel.txt'));
        $response = loginPanelSanayi($info[1], $info[2], $text);
        if ($response['success']) {
            $code = rand(11111111, 99999999);
            $session = str_replace([" ", "\n", "\t"], ['', '', ''], explode('session	', file_get_contents('cookie.txt'))[1]);
            $sql->query("INSERT INTO `panels` (`name`, `login_link`, `username`, `password`, `token`, `code`, `status`, `type`) VALUES ('{$info[0]}', '{$info[1]}', '{$info[2]}', '$text', '$session', '$code', 'inactive', 'sanayi')");
            $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', 'none', 'none', 'offflow')");
            sendMessage($from_id, "✅ ربات با موفقیت به پنل شما لاگین شد!\n\n▫️یوزرنیم : <code>{$info[2]}</code>\n▫️پسورد : <code>{$text}</code>\n▫️کد پیگیری : <code>$code</code>", $manage_server);
        } else {
            sendMessage($from_id, "❌ لاگین به پنل با خطا مواجه شد , بعد از گذشت چند دقیقه مجددا تلاش کنید !\n\n🎯 دلایل ممکن متصل نشدن ربات به پنل شما :↓\n\n◽باز نبودن پورت مورد نظر\n◽باز نشدن آدرس ارسالی\n◽آدرس ارسالی اشتباه\n◽یوزرنیم یا پسورد اشتباه\n◽قرار گرفتن آی‌پی در بلاک لیست\n◽️باز نبودن دسترسی CURL\n◽️مشکل کلی هاست", $manage_server);
        }
        foreach (['add_panel.txt', 'cookie.txt'] as $file) if (file_exists($file)) unlink($file);
    }
    
    # ------------- marzban ------------- #
    
    elseif ($data == 'marzban') {
        step('add_server');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "‌👈🏻⁩ اسم پنل خود را به دلخواه ارسال کنید :↓\n\nمثال نام : 🇳🇱 - هلند\n• این اسم برای کاربران قابل نمایش است.", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'add_server') {
        if ($sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'")->num_rows == 0) {
            step('send_address');
            file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🌐 آدرس لاگین به پنل را ارسال کنید.\n\n- example : http://1.1.1.1:8000", $cancel_add_server);
        } else {
            sendMessage($from_id, "❌ پنلی با نام [ <b>$text</b> ] قبلا در ربات ثبت شده !", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_address') {
        if (preg_match("/^(http|https):\/\/(\d+\.\d+\.\d+\.\d+|.*)\:\d+$/", $text)) {
            if ($sql->query("SELECT `login_link` FROM `panels` WHERE `login_link` = '$text'")->num_rows == 0) {
                step('send_username');
                file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
                sendMessage($from_id, "🔎 - یوزرنیم ( <b>username</b> ) پنل خود را ارسال کنید :", $cancel_add_server);
            } else {
            sendMessage($from_id, "❌ پنلی با ادرس [ <b>$text</b> ] قبلا در ربات ثبت شده !", $cancel_add_server);
        }
        } else {
            sendMessage($from_id, "🚫 لینک ارسالی شما اشتباه است !", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_username') {
        step('send_password');
        file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔎 - پسورد ( <b>password</b> ) سرور خود را ارسال کنید :", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'send_password') {
        step('none');
        $info = explode("\n", file_get_contents('add_panel.txt'));
        $response = loginPanel($info[1], $info[2], $text);
        if (isset($response['access_token'])) {
            $code = rand(11111111, 99999999);
            $sql->query("INSERT INTO `panels` (`name`, `login_link`, `username`, `password`, `token`, `code`, `type`) VALUES ('{$info[0]}', '{$info[1]}', '{$info[2]}', '$text', '{$response['access_token']}', '$code', 'marzban')");
            sendMessage($from_id, "✅ ربات با موفقیت به پنل شما لاگین شد!\n\n▫️یوزرنیم : <code>{$info[2]}</code>\n▫️پسورد : <code>{$text}</code>\n▫️کد پیگیری : <code>$code</code>", $manage_server);
        } else {
            sendMessage($from_id, "❌ لاگین به پنل با خطا مواجه شد , بعد از گذشت چند دقیقه مجددا تلاش کنید !\n\n🎯 دلایل ممکن متصل نشدن ربات به پنل شما :↓\n\n◽باز نبودن پورت مورد نظر\n◽باز نشدن آدرس ارسالی\n◽آدرس ارسالی اشتباه\n◽یوزرنیم یا پسورد اشتباه\n◽قرار گرفتن آی‌پی در بلاک لیست\n◽️باز نبودن دسترسی CURL\n◽️مشکل کلی هاست", $manage_server);
        }
        if (file_exists('add_panel.txt')) unlink('add_panel.txt');
    }
    
    # ------------------------------------ #
    
    elseif ($text == '🎟 افزودن پلن') {
        step('none');
        sendMessage($from_id, "ℹ️ قصد اضافه کردن چه نوع پلنی را دارید ؟\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :", $add_plan_button);
    }

    elseif ($data == 'add_buy_plan') { 
        step('add_name');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻نام این دسته بندی را  ارسال کنید :↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_limit');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻حجم خود را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>50</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_limit' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_date');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻تاریخ خود را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>30</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_date' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_price');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸 مبلغ این حجم را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>60000</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_price' and $text != '⬅️ بازگشت به مدیریت') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category` (`limit`, `date`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[2]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت و به لیست اضافه شد.\n\n◽حجم ارسالی : <code>{$info[1]}</code>\n◽قیمت ارسالی : <code>$text</code>", $manage_server);
        if (file_exists('add_plan.txt')) unlink('add_plan.txt');
    }

    elseif ($data == 'add_limit_plan') { 
        step('add_name_limit');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻نام این دسته بندی را  ارسال کنید :↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name_limit' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_limit_limit');
        file_put_contents('add_plan_limit.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻حجم خود را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>50</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_limit_limit' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_price_limit');
        file_put_contents('add_plan_limit.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸 مبلغ این حجم را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>60000</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_price_limit' and $text != '⬅️ بازگشت به مدیریت') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan_limit.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category_limit` (`limit`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت و به لیست اضافه شد.\n\n◽حجم ارسالی : <code>{$info[1]}</code>\n◽قیمت ارسالی : <code>$text</code>", $manage_server);
        if (file_exists('add_plan_limit.txt')) unlink('add_plan_limit.txt');
    }

    elseif ($data == 'add_date_plan') { 
        step('add_name_date');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻نام این دسته بندی را  ارسال کنید :↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name_date' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_date_date');
        file_put_contents('add_plan_date.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻تاریخ خود را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>30</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_date_date' and $text != '⬅️ بازگشت به مدیریت') {
        step('add_price_date');
        file_put_contents('add_plan_date.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸 مبلغ این حجم را به صورت عدد صحیح و لاتین ارسال کنید :↓\n\n◽نمونه : <code>60000</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_price_date' and $text != '⬅️ بازگشت به مدیریت') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan_date.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category_date` (`date`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت و به لیست اضافه شد.\n\n◽حجم ارسالی : <code>{$info[1]}</code>\n◽قیمت ارسالی : <code>$text</code>", $manage_server);
        if (file_exists('add_plan_date.txt')) unlink('add_plan_date.txt');
    }
    
    elseif ($text == '⚙️ لیست سرور ها' or $data == 'back_panellist') {
        step('none');
        $info_servers = $sql->query("SELECT * FROM `panels`");
        if($info_servers->num_rows == 0){
            if(!isset($data)){
                sendMessage($from_id, "❌ هیچ سروری در ربات ثبت نشده است.");
            }else{
                editMessage($from_id, "❌ هیچ سروری در ربات ثبت نشده است.", $message_id);
            }
            exit();
        }
        $key[] = [['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️نام', 'callback_data' => 'null'], ['text' => '▫️کد پیگیری', 'callback_data' => 'null']];
        while($row = $info_servers->fetch_array()){
            $name = $row['name'];
            $code = $row['code'];
            if($row['status'] == 'active') $status = '✅ فعال'; else $status = '❌ غیرفعال';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ بستن پنل | close panel', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        if(!isset($data)){
            sendMessage($from_id, "🔎 لیست سرور های ثبت شده شما :\n\n⚙️ با کلیک بر روی کد پیگیری سرور میتوانید وارد بخش مدیریت سرور شوید.\n\nℹ️ برای مدیریت هر کدام بر روی آن کلیک کنید.", $key);
        }else{
            editMessage($from_id, "🔎 لیست سرور های ثبت شده شما :\n\n⚙️ با کلیک بر روی کد پیگیری سرور میتوانید وارد بخش مدیریت سرور شوید.\n\nℹ️ برای مدیریت هر کدام بر روی آن کلیک کنید.", $message_id, $key);
        }
    }
    
    elseif (strpos($data, 'change_status_panel-') !== false) {
        $code = explode('-', $data)[1];
        $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
        if ($info_panel['type'] == 'sanayi') {
            $sanayi_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$info_panel['code']}'")->fetch_assoc();
            if ($sanayi_setting['example_link'] == 'none') {
                alert('🔴 برای روشن کردن پنل سنایی ابتدا باید اینباند آیدی و نمونه سرویس را تنظیم کنید !');
                exit;
            } elseif ($sanayi_setting['inbound_id'] == 'none') {
                alert('🔴 برای روشن کردن پنل سنایی ابتدا باید اینباند آیدی و نمونه سرویس را تنظیم کنید !');
                exit;
            }
        }
        $status = $info_panel['status'];
        if($status == 'active'){
            $sql->query("UPDATE `panels` SET `status` = 'inactive' WHERE `code` = '$code'");
        }else{
            $sql->query("UPDATE `panels` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $key[] = [['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️نام', 'callback_data' => 'null'], ['text' => '▫️کد پیگیری', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `panels`");
        while($row = $result->fetch_array()){
            $name = $row['name'];
            $code = $row['code'];
            if($row['status'] == 'active') $status = '✅ فعال'; else $status = '❌ غیرفعال';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ بستن پنل | close panel', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, "🔎 لیست سرور های ثبت شما :\n\nℹ️ برای مدیریت هر کدام بر روی آن کلیک کنید.", $message_id, $key);
    }
    
    elseif (strpos($data, 'status_panel-') !== false or strpos($data, 'update_panel-') !== false) {
    	alert('🔄 - لطفا چند ثانیه صبر کنید در حال دریافت اطلاعات . . .', false);
    
        $code = explode('-', $data)[1];
        $info_server = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
        
        if ($info_server['status'] == 'active') $status = '✅ فعال'; else $status = '❌ غیرفعال';
        if (strpos($info_server['login_link'], 'https://') !== false) $status_ssl = '✅ فعال'; else $status_ssl = '❌ غیرفعال';
        
        $info = [
            'ip' => explode(':', str_replace(['http://', 'https://'], '', $info_server['login_link']))[0] ?? '⚠️',
            'port' => explode(':', str_replace(['http://', 'https://'], '', $info_server['login_link']))[1] ?? '⚠️',
            'type' => ($info_server['type'] == 'marzban') ? 'مرزبان' : 'سنایی',
        ];
        
        $txt = "اطلاعات پنل [ <b>{$info_server['name']}</b> ] با موفقیت دریافت شد.\n\n🔎 وضعیت فعلی در ربات : <b>$status</b>\nℹ️ کد سرور ( برای اطلاعات ) : <code>$code</code>\n\n◽️نوع پنل : <b>{$info['type']}</b>\n◽️لوکیشن : <b>{$info_server['name']}</b>\n◽️آیپی : <code>{$info['ip']}</code>\n◽️پورت : <code>{$info['port']}</code>\n◽️وضعیت ssl : <b>$status_ssl</b>\n\n🔑 یوزرنیم پنل : <code>{$info_server['username']}</code>\n🔑 پسورد پنل : <code>{$info_server['password']}</code>";
        
        $protocols = explode('|', $info_server['protocols']);
        unset($protocols[count($protocols)-1]);
        if (in_array('vmess', $protocols)) $vmess_status = '✅'; else $vmess_status = '❌';
        if (in_array('trojan', $protocols)) $trojan_status = '✅'; else $trojan_status = '❌';
        if (in_array('vless', $protocols)) $vless_status = '✅'; else $vless_status = '❌';
        if (in_array('shadowsocks', $protocols)) $shadowsocks_status = '✅'; else $shadowsocks_status = '❌';
        
        if ($info_server['type'] == 'marzban') {
	        $back_panellist = json_encode(['inline_keyboard' => [
	            [['text' => '🆙 آپدیت اطلاعات', 'callback_data' => 'update_panel-' . $code]],
	            [['text' => '🔎 - Status :', 'callback_data' => 'null'], ['text' => $info_server['status'] == 'active' ? '✅' : '❌', 'callback_data' => 'change_status_panel-' . $code]],
	            [['text' => '🎯 - Flow :', 'callback_data' => 'null'], ['text' => $info_server['flow'] == 'flowon' ? '✅' : '❌', 'callback_data' => 'change_status_flow-' . $code]],
	            [['text' => '🗑 حذف پنل', 'callback_data' => 'delete_panel-' . $code], ['text' => '✍️ تغییر نام', 'callback_data' => 'change_name_panel-' . $code]],
	            [['text' => 'vmess - [' . $vmess_status . ']', 'callback_data' => 'change_protocol|vmess-' . $code], ['text' => 'trojan [' . $trojan_status . ']', 'callback_data' => 'change_protocol|trojan-' . $code], ['text' => 'vless [' . $vless_status . ']', 'callback_data' => 'change_protocol|vless-' . $code]],
	            [['text' => 'shadowsocks [' . $shadowsocks_status . ']', 'callback_data' => 'change_protocol|shadowsocks-' . $code]],
                [['text' => 'ℹ️ مدیریت اینباند ها', 'callback_data' => 'manage_marzban_inbound-'.$code], ['text' => '⏺ تنظیم اینباند', 'callback_data' => 'set_inbound_marzban-'.$code]],
	            [['text' => '🔙 بازگشت به لیست پنل ها', 'callback_data' => 'back_panellist']],
	        ]]);
	    } elseif ($info_server['type'] == 'sanayi') {
		    $back_panellist = json_encode(['inline_keyboard' => [
	            [['text' => '🆙 آپدیت اطلاعات', 'callback_data' => 'update_panel-' . $code]],
	            [['text' => '🔎 - Status :', 'callback_data' => 'null'], ['text' => $info_server['status'] == 'active' ? '✅' : '❌', 'callback_data' => 'change_status_panel-' . $code]],
	            [['text' => '🗑 حذف پنل', 'callback_data' => 'delete_panel-' . $code], ['text' => '✍️ تغییر نام', 'callback_data' => 'change_name_panel-' . $code]],
	            [['text' => '🆔 تنظیم اینباند برای ساخت سرویس', 'callback_data' => 'set_inbound_sanayi-'.$code]],
				[['text' => '🌐 تنظیم نمونه لینک ( سرویس ) برای تحویل', 'callback_data' => 'set_example_link_sanayi-'.$code]],
	            [['text' => 'vmess - [' . $vmess_status . ']', 'callback_data' => 'change_protocol|vmess-' . $code], ['text' => 'trojan [' . $trojan_status . ']', 'callback_data' => 'change_protocol|trojan-' . $code], ['text' => 'vless [' . $vless_status . ']', 'callback_data' => 'change_protocol|vless-' . $code]],
	            [['text' => 'shadowsocks [' . $shadowsocks_status . ']', 'callback_data' => 'change_protocol|shadowsocks-' . $code]],
	            [['text' => '🔙 بازگشت به لیست پنل ها', 'callback_data' => 'back_panellist']],
	        ]]);
        }
        editMessage($from_id, $txt, $message_id, $back_panellist);
    }

    elseif (strpos($data, 'set_inbound_marzban') !== false) {
        $code = explode('-', $data)[1];
        step('send_inbound_marzban-'.$code);
        sendMessage($from_id, "🆕 نام اینباند مورد نظر خود را ارسال کنید :\n\n❌ توجه داشته باشید که اگر نام اینباند را اشتباه وارد کنید امکان خطا در ساخت سرویس خواهد بود و همچنین اینباند ارسالی شما باید مربوط به پروتکل که برای این پنل در ربات فعال کردید باشد.", $back_panel);
    }

    elseif (strpos($user['step'], 'send_inbound_marzban') !== false and $text != '✔ اتمام و ثبت') {
        $code = explode('-', $user['step'])[1];
        $rand_code = rand(111111, 999999);
        $panel_fetch = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
        $token = loginPanel($panel_fetch['login_link'], $panel_fetch['username'], $panel_fetch['password'])['access_token'];
        $inbounds = inbounds($token, $panel_fetch['login_link']);
        $status = checkInbound(json_encode($inbounds), $text);
        if ($status) {
            $res = $sql->query("INSERT INTO `marzban_inbounds` (`panel`, `inbound`, `code`, `status`) VALUES ('$code', '$text', '$rand_code', 'active')");
            sendMessage($from_id, "✅ اینباند ارسالی شما با موفقیت تنظیم شد.\n\n#️⃣ در صورت ارسال اینباند جدید آن را ارسال کنید و در غیر این صورت دستور /end_inbound را ارسال کنید یا روی دکمه زیر کلیک کنید.", $end_inbound);
        } else {
            sendMessage($from_id, "🔴 اینباند ارسالی شما یافت نشد !", $end_inbound);
        }
    }

    elseif (($text == '✔ اتمام و ثبت' or $text == '/end_inbound') and strpos($user['step'], 'send_inbound_marzban') !== false) {
        step('none');
        sendMessage($from_id, "✅ همه اینباند های ارسالی شما ثبت شد.", $manage_server);
    }

    elseif (strpos($data, 'manage_marzban_inbound') !== false) {
        $panel_code = explode('-', $data)[1];
        $fetch_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '$panel_code'");
        if ($fetch_inbounds->num_rows > 0) {
            while ($row = $fetch_inbounds->fetch_assoc()) {
                $key[] = [['text' => $row['inbound'], 'callback_data' => 'null'], ['text' => '🗑', 'callback_data' => 'delete_marzban_inbound-'.$row['code'].'-'.$panel_code]];
            }
            $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'status_panel-'.$panel_code]];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "🔎 لیست همه اینباند های ثبت شده برای این پنل نوسط شما به شرح زیر است !", $message_id, $key);
        } else {
            alert('❌ هیچ اینباندی برای این پنل تنظیم نشده است !', true);
        }
    }

    elseif (strpos($data, 'delete_marzban_inbound') !== false) {
        $panel_code = explode('-', $data)[2];
        $inbound_code = explode('-', $data)[1];
        $fetch = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '$panel_code'");
        if ($fetch->num_rows > 0) {
            alert('✅ اینباند انتخابی شما با موفقیت از دیتابیس ربات حذف شد.', true);
            $sql->query("DELETE FROM `marzban_inbounds` WHERE `panel` = '$panel_code' AND `code` = '$inbound_code'");
            $key = json_encode(['inline_keyboard' => [[['text' => '🔎', 'callback_data' => 'manage_marzban_inbound-'.$panel_code]]]]);
            editMessage($from_id, "⬅️ برای بازگشت به لیست اینباند ها , روی دکمه زیر کلیک کنید !", $message_id, $key);
        } else {
            alert('❌ همچین اینباندی در دیتابیس ربات یافت نشد !', true);
        }
    }

    elseif (strpos($data, 'set_inbound_sanayi') !== false) {
        $code = explode('-', $data)[1];
        step('send_inbound_id-'.$code);
        sendMessage($from_id, "👇 آیدی سرویس مادر که قرار است کلاینت ها داخل آن اد شود را ارسال کنید : ( id ) :", $back_panel);
    }
    
    elseif (strpos($user['step'], 'send_inbound_id') !== false) {
        if (is_numeric($text)) {
            $code = explode('-', $user['step'])[1];
            $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
            include_once 'api/sanayi.php';
            $xui = new Sanayi($info_panel['login_link'], $info_panel['token']);
            $id_status = json_decode($xui->checkId($text), true)['status'];
            if ($id_status == true) {
                step('none');
                if ($sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '$code'")->num_rows > 0) {
                    $sql->query("UPDATE `sanayi_panel_setting` SET `inbound_id` = '$text' WHERE `code` = '$code'");
                } else {
                    $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', '$text', 'none', 'offflow')");
                }
                sendMessage($from_id, "✅ با موفقیت تنظیم شد !", $manage_server);
            } else {
                sendMessage($from_id, "❌ اینباندی با ایدی <code>$text</code> پیدا نشد !", $back_panel);
            }
        } else {
            sendMessage($from_id, "❌ مقدار ورودی باید فقط عدد باشد !", $back_panel);
        }
    }
    
    elseif (strpos($data, 'set_example_link_sanayi') !== false) {
        $code = explode('-', $data)[1];
        step('set_example_link_sanayi-'.$code);
        sendMessage($from_id, "✏️ نمونه سرویس خود را با توجه به توضیحات زیر ارسال کنید :\n\n▫️به جای جاهای متغیر هر قسمت در لینک سرویس ارسالی مقدار s1 و %s2 و ...% رو جایگزین کنید.\n\nبرای مثال لینک دریافتی :\n\n<code>vless://a8eff4a8-226d3343bbf-9e9d-a35f362c4cb4@1.1.1.1:2053?security=reality&type=grpc&host=&headerType=&serviceName=xyz&sni=cdn.discordapp.com&fp=chrome&pbk=SbVKOEMjK0sIlbwg4akyBg5mL5KZwwB-ed4eEE7YnRc&sid=&spx=#ZanborPAnel</code>\n\nو لینک ارسالی شما به ربات باید به شرح زیر باشد ( نمونه ) :\n\n<code>vless://%s1@%s2?security=reality&type=grpc&host=&headerType=&serviceName=xyz&sni=cdn.discordapp.com&fp=chrome&pbk=SbVKOEMjK0sIlbwg4akyBg5mL5KZwwB-ed4eEE7YnRc&sid=&spx=#%s3</code>\n\n⚠️ به صورت صحیح ارسال کنید در غیر این صورت ربات موقع خرید سرویس با خطا مواجه خواهد شد", $back_panel);
    }
    
    elseif (strpos($user['step'], 'set_example_link_sanayi') !== false) {
        if (strpos($text, '%s1') !== false and strpos($text, '%s3') !== false) {
            step('none');
            $code = explode('-', $user['step'])[1];
            if ($sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '$code'")->num_rows > 0) {
                $sql->query("UPDATE `sanayi_panel_setting` SET `example_link` = '$text' WHERE `code` = '$code'");
            } else {
                $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', 'none', '$text', 'offflow')");
            }
            sendMessage($from_id, "✅ با موفقیت تنظیم شد !", $manage_server);
        } else {
            sendMessage($from_id, "❌ نمونه لینک ارسالی شما اشتباه است !", $back_panel);
        }
    }
    
    elseif (strpos($data, 'change_status_flow-') !== false) {
    	$code = explode('-', $data)[1];
        $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'");
        $status = $info_panel->fetch_assoc()['flow'];
        if($status == 'flowon'){
            $sql->query("UPDATE `panels` SET `flow` = 'flowoff' WHERE `code` = '$code'");
        }else{
            $sql->query("UPDATE `panels` SET `flow` = 'flowon' WHERE `code` = '$code'");
        }
        $back = json_encode(['inline_keyboard' => [[['text' => '🆙 آپدیت اطلاعات', 'callback_data' => 'update_panel-'.$code]]]]);
        editmessage($from_id, '✅ تغییرات با موفقیت انجام شد.', $message_id, $back);
    }
    
    elseif (strpos($data, 'change_protocol|') !== false) {
        $code = explode('-', $data)[1];
        $protocol = explode('-', explode('|', $data)[1])[0];
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code' LIMIT 1")->fetch_assoc();
        $protocols = explode('|', $panel['protocols']);
        unset($protocols[count($protocols)-1]);
        
        if($protocol == 'vless'){
            if(in_array($protocol, $protocols)){
                unset($protocols[array_search($protocol, $protocols)]);
            }else{
                array_push($protocols, $protocol);
            }
        }elseif($protocol == 'vmess'){
            if(in_array($protocol, $protocols)){
                unset($protocols[array_search($protocol, $protocols)]);
            }else{
                array_push($protocols, $protocol);
            }
        }elseif($protocol == 'trojan'){
            if(in_array($protocol, $protocols)){
                unset($protocols[array_search($protocol, $protocols)]);
            }else{
                array_push($protocols, $protocol);
            }
        }elseif($protocol == 'shadowsocks'){
            if(in_array($protocol, $protocols)){
                unset($protocols[array_search($protocol, $protocols)]);
            }else{
                array_push($protocols, $protocol);
            }
        }
        
        $protocols = join('|', $protocols) . '|';
        $sql->query("UPDATE `panels` SET `protocols` = '$protocols' WHERE `code` = '$code' LIMIT 1");
        
        $back = json_encode(['inline_keyboard' => [[['text' => '🆙 آپدیت اطلاعات', 'callback_data' => 'update_panel-'.$code]]]]);
        editmessage($from_id, '✅ تغییر وضعیت پروتکل با موفقیت انجام شد.', $message_id, $back);
        
    }
    
    elseif (strpos($data, 'change_name_panel-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name-'.$code);
        sendMessage($from_id, "🔰نام جدید پنل را ارسال کنید :", $back_panel);
    }
    
    elseif (strpos($user['step'], 'change_name-') !== false) {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `panels` SET `name` = '$text' WHERE `code` = '$code'");
        sendMessage($from_id, "✅ نام پنل با موفقیت بر روی [ <b>$text</b> ] تنظیم شد.", $back_panellist);
    }
    
    elseif (strpos($data, 'delete_panel-') !== false) {
        step('none');
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `panels` WHERE `code` = '$code'");
        $info_servers = $sql->query("SELECT * FROM `panels`");
        if($info_servers->num_rows == 0){
            if(!isset($data)){
                sendMessage($from_id, "❌ هیچ سروری در ربات ثبت نشده است.");
            }else{
                editMessage($from_id, "❌ هیچ سروری در ربات ثبت نشده است.", $message_id);
            }
            exit();
        }
        $key[] = [['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️نام', 'callback_data' => 'null'], ['text' => '▫️کد پیگیری', 'callback_data' => 'null']];
        while($row = $info_servers->fetch_array()){
            $name = $row['name'];
            $code = $row['code'];
            if($row['status'] == 'active') $status = '✅ فعال'; else $status = '❌ غیرفعال';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ بستن پنل | close panel', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        if(!isset($data)){
            sendMessage($from_id, "🔎 لیست سرور های ثبت شده شما :\n\n⚙️ با کلیک بر روی کد پیگیری سرور میتوانید وارد بخش مدیریت سرور شوید.\n\nℹ️ برای مدیریت هر کدام بر روی آن کلیک کنید.", $key);
        }else{
            editMessage($from_id, "🔎 لیست سرور های ثبت شده شما :\n\n⚙️ با کلیک بر روی کد پیگیری سرور میتوانید وارد بخش مدیریت سرور شوید.\n\nℹ️ برای مدیریت هر کدام بر روی آن کلیک کنید.", $message_id, $key);
        }
    }
    
    elseif ($text == '⚙️ مدیریت پلن ها' or $data == 'back_cat') {
        step('manage_plans');
        if ($text) {
            sendMessage($from_id, "ℹ️ قصد مدیریت کردن کدام پلن را دارید ؟\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :", $manage_plans);
        } else {
            editMessage($from_id, "ℹ️ قصد مدیریت کردن کدام پلن را دارید ؟\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید :", $message_id, $manage_plans);
        }
    }

    elseif ($data == 'manage_main_plan') {
        step('manage_main_plan');
        $count = $sql->query("SELECT * FROM `category`")->num_rows;
        if ($count == 0) {
            if(isset($data)){
                editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
                exit();
            } else {
                sendmessage($from_id, "❌ لیست پلن ها خالی است.", $manage_server);
                exit();
            }
        }
        $result = $sql->query("SELECT * FROM `category`");
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }

    elseif ($data == 'manage_limit_plan') {
        step('manage_limit_plan');
        $count = $sql->query("SELECT * FROM `category_limit`")->num_rows;
        if ($count == 0) {
            if(isset($data)){
                editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
                exit();
            } else {
                sendmessage($from_id, "❌ لیست پلن ها خالی است.", $manage_server);
                exit();
            }
        }
        $result = $sql->query("SELECT * FROM `category_limit`");
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }

    elseif ($data == 'manage_date_plan') {
        step('manage_date_plan');
        $count = $sql->query("SELECT * FROM `category_date`")->num_rows;
        if ($count == 0) {
            if(isset($data)){
                editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
                exit();
            } else {
                sendmessage($from_id, "❌ لیست پلن ها خالی است.", $manage_server);
                exit();
            }
        }
        $result = $sql->query("SELECT * FROM `category_date`");
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }
    
    elseif (strpos($data, 'change_status_cat-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category`");
       while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }

    elseif (strpos($data, 'change_status_cat_limit-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category_limit` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category_limit` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category_limit`");
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }

    elseif (strpos($data, 'change_status_cat_date-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category_date` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category_date` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category_date`");
       while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
        }else{
            sendMessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $button);
        }
    }
    
    elseif (strpos($data, 'delete_limit-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$code], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
    }

    elseif (strpos($data, 'delete_limit_limit-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category_limit` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category_limit`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category_limit`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
    }

    elseif (strpos($data, 'delete_limit_date-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category_date` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category_date`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ لیست پلن ها خالی است.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category_date`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰لیست دسته بندی های شما به شرح زیر است :\n\n🔢 تعداد کل : <code>$count</code> عدد\n🔢 تعداد کل لیست فعال : <code>$count_active</code>  عدد", $message_id, $button);
    }
    
    elseif (strpos($data, 'manage_list-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }

    elseif (strpos($data, 'manage_list_limit-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }

    elseif (strpos($data, 'manage_list_date-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }
    
    elseif (strpos($data, 'manage_cat-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'تاریخ', 'callback_data' => 'null'], ['text' => 'حجم', 'callback_data' => 'null'], ['text' => 'قیمت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null']],
            [['text' => $res['date'], 'callback_data' => 'change_date-'.$res['code']], ['text' => $res['limit'], 'callback_data' => 'change_limit-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name-'.$res['code']]],
            [['text' => '⬅️ بازگشت', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 اطلاعات پلن با موفقیت دریافت شد.\n\n▫️نام پلن : <b>{$res['name']}</b>\n▫️حجم : <code>{$res['limit']}</code>\n▫️تاریخ : <code>{$res['date']}</code>\n▫️قیمت : <code>{$res['price']}</code>\n\n📎 با کلیک بر روی هر کدام میتوانید مقدار آن را تغییر دهید !", $message_id, $key);
    }

    elseif (strpos($data, 'manage_cat_date-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'تاریخ', 'callback_data' => 'null'], ['text' => 'قیمت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null']],
            [['text' => $res['date'], 'callback_data' => 'change_date_date-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price_date-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name_date-'.$res['code']]],
            [['text' => '⬅️ بازگشت', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 اطلاعات پلن با موفقیت دریافت شد.\n\n▫️نام پلن : <b>{$res['name']}</b>\n▫️تاریخ : <code>{$res['date']}</code>\n▫️قیمت : <code>{$res['price']}</code>\n\n📎 با کلیک بر روی هر کدام میتوانید مقدار آن را تغییر دهید !", $message_id, $key);
    }

    elseif (strpos($data, 'manage_cat_limit-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'حجم', 'callback_data' => 'null'], ['text' => 'قیمت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null']],
            [['text' => $res['limit'], 'callback_data' => 'change_limit_limit-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price_limit-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name_limit-'.$res['code']]],
            [['text' => '⬅️ بازگشت', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 اطلاعات پلن با موفقیت دریافت شد.\n\n▫️نام پلن : <b>{$res['name']}</b>\n▫️حجم : <code>{$res['limit']}</code>\n▫️قیمت : <code>{$res['price']}</code>\n\n📎 با کلیک بر روی هر کدام میتوانید مقدار آن را تغییر دهید !", $message_id, $key);
    }
    
    elseif (strpos($data, 'change_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_date-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_date_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_date_date-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }
    
    elseif (strpos($data, 'change_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_limit-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_limit_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_limit_limit-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }
    
    elseif (strpos($data, 'change_price-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_price_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price_date-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_price_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price_limit-'.$code);
        sendMessage($from_id, "🔰مقدار جدید را به صورت عدد صحیح و لاتین ارسال کنید :", $back_panel);
    }
    
    elseif (strpos($data, 'change_name-') !== false) {
        $code = explode('-', $data)[1];
        step('change_namee-'.$code);
        sendMessage($from_id, "🔰نام جدید را ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_name_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name_date-'.$code);
        sendMessage($from_id, "🔰نام جدید را ارسال کنید :", $back_panel);
    }

    elseif (strpos($data, 'change_name_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name_limit-'.$code);
        sendMessage($from_id, "🔰نام جدید را ارسال کنید :", $back_panel);
    }
    
    elseif (strpos($user['step'], 'change_date-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `date` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_date_date-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `date` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }
    
    elseif (strpos($user['step'], 'change_limit-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `limit` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_limit_limit-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `limit` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }
    
    elseif (strpos($user['step'], 'change_price-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_price_date-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_price_limit-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }
    
    elseif (strpos($user['step'], 'change_namee-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_name_date-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }

    elseif (strpos($user['step'], 'change_name_limit-') !== false and $text != '⬅️ بازگشت به مدیریت') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ اطلاعات ارسالی شما با موفقیت ثبت شد.", $manage_server);
    }
    
    // ----------- manage message ----------- //
    elseif($text == '🔎 وضعیت ارسال / فوروارد همگانی'){
        $info_send = $sql->query("SELECT * FROM `sends`")->fetch_assoc();
        if($info_send['send'] == 'yes') $send_status = '✅'; else $send_status = '❌';
        if($info_send['step'] == 'send') $status_send = '✅'; else $status_send = '❌';
        if($info_send['step'] == 'forward') $status_forward = '✅'; else $status_forward = '❌';
        sendMessage($from_id, "👇🏻وضعیت ارسال های شما به شرح زیر است :\n\nℹ️ در صف ارسال/فوروارد : <b>$send_status</b>\n⬅️ ارسال همگانی : <b>$status_send</b>\n⬅️ فوروارد همگانی : <b>$status_forward</b>\n\n🟥 برای لغو ارسال/فوروارد همگانی دستور /cancel_send را ارسال کنید.", $manage_message);
    }
    
    elseif($text == '/cancel_send'){
        $sql->query("UPDATE `sends` SET `send` = 'no', `text` = 'null', `type` = 'null', `step` = 'null'");
        sendMessage($from_id, "✅ ارسال/فوروارد همگانی شما با موفقیت لغو شد.", $manage_message);   
    }
    
    elseif($text == '📬 ارسال همگانی'){
        step('send_all');
        sendMessage($from_id, "👇 متن خود را در قالب یک پیام ارسال کنید :", $back_panel);
    }
    
    elseif($user['step'] == 'send_all'){
        step('none');
        if (isset($update->message->text)){
            $type = 'text';
        }else{
            $type = $update->message->photo[count($update->message->photo)-1]->file_id;
            $text = $update->message->caption;
        }
        $sql->query("UPDATE `sends` SET `send` = 'yes', `text` = '$text', `type` = '$type', `step` = 'send'");
        sendMessage($from_id, "✅ پیام شما با موفقیت به صف ارسال همگانی اضافه شد !", $manage_message);
    }
    
    elseif($text == '📬 فوروارد همگانی'){
        step('for_all');
        sendMessage($from_id, "‌‌👈🏻⁩ متن خود را فوروارد کنید :", $back_panel);
    }
    
    elseif($user['step'] == 'for_all'){
        step('none');
        sendMessage($from_id, "✅ پیام شما با موفقیت به صف فوروارد همگانی اضافه شد !", $panel);
        $sql->query("UPDATE `sends` SET `send` = 'yes', `text` = '$message_id', `type` = '$from_id', `step` = 'forward'");
    }
    
    elseif($text == '📞 ارسال پیام به کاربر' or $text == '📤 ارسال پیام به کاربر'){
        step('sendmessage_user1');
        sendMessage($from_id, "🔢 ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif($user['step'] == 'sendmessage_user1' and $text != '⬅️ بازگشت به مدیریت'){
        if ($sql->query("SELECT `from_id` FROM `users` WHERE `from_id` = '$text'")->num_rows > 0) {
            step('sendmessage_user2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "👇 پیام خود را در قالب یک متن ارسال کنید :", $back_panel);
        } else {
            step('sendmessage_user1');
            sendMessage($from_id, "❌ آیدی عددی ارسالی شما عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($user['step'] == 'sendmessage_user2' and $text != '⬅️ بازگشت به مدیریت') {
        step('none');
        $id = file_get_contents('id.txt');
        sendMessage($from_id, "✅ پیام شما با موفقیت به کاربر <code>$id</code> ارسال شد.", $manage_message);
        if (isset($update->message->text)){
            sendmessage($id, $text);
        } else {
            $file_id = $update->message->photo[count($update->message->photo)-1]->file_id;
            $caption = $update->message->caption;
            bot('sendphoto', ['chat_id' => $id, 'photo' => $file_id, 'caption' => $caption]);
        }
        unlink('id.txt');
    }
    
    // ----------- manage users ----------- //
    elseif ($text == '🔎 اطلاعات کاربر') {
        step('info_user');
        sendMessage($from_id, "🔰ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'info_user') {
        $info = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($info->num_rows > 0) {
            step('none');
            $res_get = bot('getchatmember', ['user_id' => $text, 'chat_id' => $text]);
            $first_name = $res_get->result->user->first_name;
            $username = '@' . $res_get->result->user->username;
            $coin = number_format($info->fetch_assoc()['coin']) ?? 0;
            $count_service = $info->fetch_assoc()['count_service'] ?? 0;
            $count_payment = $info->fetch_assoc()['count_charge'] ?? 0;   
            sendMessage($from_id, "⭕️ اطلاعات کاربر [ <code>$text</code> ] با موفقیت دریافت شد.\n\n▫️یوزرنیم کاربر : $username\n▫️نام کاربر : <b>$first_name</b>\n▫️موجودی کاربر : <code>$coin</code> تومان\n▫️ تعدادی سرویس کاربر : <code>$count_service</code> عدد\n▫️تعداد پرداختی کاربر : <code>$count_payment</code> عدد", $manage_user);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($text == '➕ افزایش موجودی') {
        step('add_coin');
        sendMessage($from_id, "🔰ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'add_coin') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows > 0){
            step('add_coin2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "🔎 مقدار مبلغ خود را ارسال کنید :", $back_panel);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($user['step'] == 'add_coin2') {
        step('none');
        $id = file_get_contents('id.txt');
        $sql->query("UPDATE `users` SET `coin` = coin + $text WHERE `from_id` = '$id'");
        sendMessage($from_id, "✅ با موفقیت انجام شد.", $manage_user);
        sendMessage($id, "✅ حساب شما از طرف مدیریت به مقدار <code>$text</code> تومان شارژ شد.");
        unlink('id.txt');
    }
    
    elseif ($text == '➖ کسر موجودی') {
        step('rem_coin');
        sendMessage($from_id, "🔰ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'rem_coin' and $text != '⬅️ بازگشت به مدیریت') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows > 0){
            step('rem_coin2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "🔎 مقدار مبلغ خود را ارسال کنید :", $back_panel);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($user['step'] == 'rem_coin2' and $text != '⬅️ بازگشت به مدیریت') {  
        step('none');
        $id = file_get_contents('id.txt');
        $sql->query("UPDATE `users` SET `coin` = coin - $text WHERE `from_id` = '$id'");
        sendMessage($from_id, "✅ با موفقیت انجام شد.", $manage_user);
        sendMessage($id, "✅ از طرف مدیریت مقدار <code>$text</code> تومان از حساب شما کسر شد.");
        unlink('id.txt');
    }
    
    elseif (strpos($data, 'cancel_fish') !== false) {
        $id = explode('-', $data)[1];
        editMessage($from_id, "✅ با موفقیت انجام شد !", $message_id);
        sendMessage($id, "❌ فیش ارسالی شما به دلیل اشتباه بودن از طرف مدیریت لغو شد و حساب شما شارژ نشد !");
    }
    
    elseif (strpos($data, 'accept_fish') !== false) {
        $id = explode('-', $data)[1];
        $price = explode('-', $data)[2];
        $sql->query("UPDATE `users` SET `coin` = coin + $price WHERE `from_id` = '$id'");
        editMessage($from_id, "✅ با موفقیت انجام شد !", $message_id);
        sendMessage($id, "✅ حساب شما با موفقیت به مبلغ <code>$price</code> تومان شارژ شد !");
    }
    
    elseif ($text == '❌ مسدود کردن') {
        step('block');
        sendMessage($from_id, "🔢 ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'block' and $text != '⬅️ بازگشت به مدیریت') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($user->num_rows > 0) {
            step('none');
            $sql->query("UPDATE `users` SET `status` = 'inactive' WHERE `from_id` = '$text'");
            sendMessage($from_id, "✅ کاربر مورد نظر با موفقیت بلاک شد.", $manage_user);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($text == '✅ آزاد کردن') {
        step('unblock');
        sendmessage($from_id, "🔢 ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'unblock' and $text != '⬅️ بازگشت به مدیریت' ){
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($user->num_rows > 0) {
            step('none');
            $sql->query("UPDATE `users` SET `status` = 'active' WHERE `from_id` = '$text'");
            sendMessage($from_id, "✅ کاربر مورد نظر با موفقیت ازاد شد.", $manage_user);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);  
        }
    }
    
    // ----------- manage setting ----------- //
    elseif ($text == '◽بخش ها') {
        sendMessage($from_id, "🔰این بخش تکمیل نشده است !");
    }
    
    elseif ($text == '🚫 مدیریت ضد اسپم' or $data == 'back_spam') {
        if (isset($text)) {
            sendMessage($from_id, "🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $manage_spam);
        } else {
            editMessage($from_id, "🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $message_id, $manage_spam);
        }
    }
    
    elseif ($data == 'change_status_spam') {
        $status = $sql->query("SELECT * FROM `spam_setting`")->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `spam_setting` SET `status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `spam_setting` SET `status` = 'active'");
        }
        $manage_spam = json_encode(['inline_keyboard' => [
            [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_status_spam'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
            [['text' => ($spam_setting['status'] == 'ban') ? '🚫 مسدود' : '⚠️ اخطار', 'callback_data' => 'change_type_spam'], ['text' => '▫️مدل برخورد :', 'callback_data' => 'null']],
            [['text' => $spam_setting['time'] . ' ثانیه', 'callback_data' => 'change_time_spam'], ['text' => '▫️زمان : ', 'callback_data' => 'null']],
            [['text' => $spam_setting['count_message'] . ' عدد', 'callback_data' => 'change_count_spam'], ['text' => '▫️تعداد پیام : ', 'callback_data' => 'null']],
        ]]);
        editMessage($from_id, "🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $message_id, $manage_spam);
    }
    
    elseif ($data == 'change_type_spam') {
        $type = $sql->query("SELECT * FROM `spam_setting`")->fetch_assoc()['type'];
        if ($type == 'ban') {
            $sql->query("UPDATE `spam_setting` SET `type` = 'warn'");
        } elseif ($type == 'warn') {
            $sql->query("UPDATE `spam_setting` SET `type` = 'ban'");
        }
        $manage_spam = json_encode(['inline_keyboard' => [
            [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
            [['text' => ($type == 'ban') ? '⚠️ اخطار' : '🚫 مسدود', 'callback_data' => 'change_type_spam'], ['text' => '▫️مدل برخورد :', 'callback_data' => 'null']],
            [['text' => $spam_setting['time'] . ' ثانیه', 'callback_data' => 'change_time_spam'], ['text' => '▫️زمان : ', 'callback_data' => 'null']],
            [['text' => $spam_setting['count_message'] . ' عدد', 'callback_data' => 'change_count_spam'], ['text' => '▫️تعداد پیام : ', 'callback_data' => 'null']],
        ]]);
        editMessage($from_id, "🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $message_id, $manage_spam);
    }
    
    elseif ($data == 'change_count_spam') {
        step('change_count_spam');
        editMessage($from_id, "🆙 مقدار جدید را به صورت عدد صحیح و درست ارسال کنید :", $message_id, $back_spam);
    }
    
    elseif ($user['step'] == 'change_count_spam') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `spam_setting` SET `count_message` = '$text'");
            $manage_spam = json_encode(['inline_keyboard' => [
                [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
                [['text' => ($spam_setting['type'] == 'ban') ? '🚫 مسدود' : '⚠️ اخطار', 'callback_data' => 'change_type_spam'], ['text' => '▫️مدل برخورد :', 'callback_data' => 'null']],
                [['text' => $spam_setting['time'] . ' ثانیه', 'callback_data' => 'change_time_spam'], ['text' => '▫️زمان : ', 'callback_data' => 'null']],
                [['text' => $text . ' عدد', 'callback_data' => 'change_count_spam'], ['text' => '▫️تعداد پیام : ', 'callback_data' => 'null']],
            ]]);
            sendMEssage($from_id, "✅ تغییرات با موفقیت انجام شد !\n🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $manage_spam);
        } else {
            sendMessage($from_id, "❌ عدد ارسالی شما اشتباه است !", $back_spam);
        }
    }
    
    elseif ($data == 'change_time_spam') {
        step('change_time_spam');
        editMessage($from_id, "🆙 مقدار جدید را به صورت عدد صحیح و درست ارسال کنید :", $message_id, $back_spam);
    }
    
    elseif ($user['step'] == 'change_time_spam') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `spam_setting` SET `time` = '$text'");
            $manage_spam = json_encode(['inline_keyboard' => [
                [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
                [['text' => ($spam_setting['type'] == 'ban') ? '🚫 مسدود' : '⚠️ اخطار', 'callback_data' => 'change_type_spam'], ['text' => '▫️مدل برخورد :', 'callback_data' => 'null']],
                [['text' => $text . ' ثانیه', 'callback_data' => 'change_time_spam'], ['text' => '▫️زمان : ', 'callback_data' => 'null']],
                [['text' => $spam_setting['count_message'] . ' عدد', 'callback_data' => 'change_count_spam'], ['text' => '▫️تعداد پیام : ', 'callback_data' => 'null']],
            ]]);
            sendMEssage($from_id, "✅ تغییرات با موفقیت انجام شد !\n🚫 به بخش مدیریت ضد اسپم ربات خوش آمدید!\n\n✏️ با کلیک بر روی هر کدام از دکمه های سمت چپ, میتوانید مقدار فعلی را تغییر دهید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $manage_spam);
        } else {
            sendMessage($from_id, "❌ عدد ارسالی شما اشتباه است !", $back_spam);
        }
    }
    
    elseif ($text == '◽کانال ها') {    
        $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
        if (mysqli_num_rows($lockSQL) > 0) {
            $locksText = "☑️ به بخش (🔒 بخش قفل ها) خوش امدید\n\n🚦 راهنما :\n1 - 👁 برای مشاهده ی هر کدام روی اسم ان بزنید.\n2 - برای حذف هر کدام روی دکمه ی ( 🗑 ) بزنید\n3 - برای افزودن قفل روی دکمه ی ( ➕ افزودن قفل ) بزنید";
            $button[] = [['text' => '🗝 نام قفل', 'callback_data' => 'none'], ['text' => '🗑 حذف', 'callback_data' => 'none']];
            while ($row = $lockSQL->fetch_assoc()) {
                $name = $row['name'];
                $link = str_replace("@", "", $row['chat_id']);
                $button[] = [['text' => $name, 'url' => "https://t.me/$link"], ['text' => '🗑', 'callback_data' => "remove_lock-{$row['chat_id']}"]];
            }
        } else $locksText = '❌ شما قفلی برای حذف و مشاهده ندارید لطفا از طریق دکمه ی ( ➕ افزودن قفل ) اضافه کنید.';
        $button[] = [['text' => '➕ افزودن قفل', 'callback_data' => 'addLock']];
        if ($data) editmessage($from_id, $locksText, $message_id, json_encode(['inline_keyboard' => $button]));
        else sendMessage($from_id, $locksText, json_encode(['inline_keyboard' => $button]));
    }
    
    elseif($data == 'addLock'){
        step('add_channel');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "✔ یوزرنیم کانال خود را با @ ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'add_channel' and $data != 'back_look' and $text != '⬅️ بازگشت به مدیریت') {
        if (strpos($text, "@") !== false) { 
            if ($sql->query("SELECT * FROM `lock` WHERE `chat_id` = '$text'")->num_rows == 0) {
                $info_channel = bot('getChatMember', ['chat_id' => $text, 'user_id' => bot('getMe')->result->id]);
                if ($info_channel->result->status == 'administrator') {
                    step('none');
                    $channel_name = bot('getChat', ['chat_id' => $text])->result->title ?? 'بدون نام';
                    $sql->query("INSERT INTO `lock`(`name`, `chat_id`) VALUES ('$channel_name', '$text')");
                    $txt = "✅ کانال شما با موفقیت به لیست جوین اجباری اضافه شد.\n\n🆔 - $text";
                    sendmessage($from_id, $txt, $panel);
                } else { 
                    sendMessage($from_id, "❌  ربات داخل کانال $text ادمین نیست !", $back_panel);
                }
            } else {
                sendMessage($from_id, "❌ این کانال از قبل در ربات ثبت شده است !", $back_panel);
            }
        } else {
            sendmessage($from_id, "❌ یوزرنیم ارسالی شما باید با @ باشد !", $back_panel);
        }
    }
    
    elseif (strpos($data, "remove_lock-") !== false) {
        $link = explode("-", $data)[1];
        $sql->query("DELETE FROM `lock` WHERE `chat_id` = '$link' LIMIT 1");
        $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
        if (mysqli_num_rows($lockSQL) > 0) {
            $locksText = "☑️ به بخش (🔒 بخش قفل ها) خوش امدید\n\n🚦 راهنما :\n1 - 👁 برای مشاهده ی هر کدام روی اسم ان بزنید.\n2 - برای حذف هر کدام روی دکمه ی ( 🗑 ) بزنید\n3 - برای افزودن قفل روی دکمه ی ( ➕ افزودن قفل ) بزنید";
            $button[] = [['text' => '🗝 نام قفل', 'callback_data' => 'none'], ['text' => '🗑 حذف', 'callback_data' => 'none']];
            while ($row = $lockSQL->fetch_assoc()) {
                $name = $row['name'];
                $link = str_replace("@", "", $row['chat_id']);
                $button[] = [['text' => $name, 'url' => "https://t.me/$link"], ['text' => '🗑', 'callback_data' => "remove_lock_{$row['chat_id']}"]];
            }
        } else $locksText = '❌ شما قفلی برای حذف و مشاهده ندارید لطفا از طریق دکمه ی ( ➕ افزودن قفل ) اضافه کنید.';
        $button[] = [['text' => '➕ افزودن قفل', 'callback_data' => 'addLock']];
        if ($data) editmessage($from_id, $locksText, $message_id, json_encode(['inline_keyboard' => $button]));
        else sendMessage($from_id, $locksText, json_encode(['inline_keyboard' => $button]));
    }
    
    // ----------------- manage paymanet ----------------- //
    elseif ($text == '◽تنظیمات درگاه پرداخت') {
        sendMessage($from_id, "⚙️️ به تنظیمات درگاه پرداخت خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :", $manage_payment);
    }
    
    elseif ($text == '✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات') {
        sendMessage($from_id, "✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات به شرح زیر است :", $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_zarinpal') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['zarinpal_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `zarinpal_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `zarinpal_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_zarinpal'], ['text' => '▫️زرین پال :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['idpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_idpay'], ['text' => '▫️آیدی پی :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
            [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️کارت به کارت :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات به شرح زیر است :", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_idpay') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['idpay_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `idpay_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `idpay_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($payment_setting['zarinpal_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_zarinpal'], ['text' => '▫️زرین پال :', 'callback_data' => 'null']],
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_idpay'], ['text' => '▫️آیدی پی :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
            [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️کارت به کارت :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات به شرح زیر است :", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_nowpayment') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['nowpayment_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `nowpayment_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `nowpayment_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($payment_setting['zarinpal_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_zarinpal'], ['text' => '▫️زرین پال :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['idpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_idpay'], ['text' => '▫️آیدی پی :', 'callback_data' => 'null']],
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
            [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️کارت به کارت :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات به شرح زیر است :", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_card') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['card_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `card_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `card_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($payment_setting['zarinpal_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_zarinpal'], ['text' => '▫️زرین پال :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['idpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_idpay'], ['text' => '▫️آیدی پی :', 'callback_data' => 'null']],
            [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️کارت به کارت :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات به شرح زیر است :", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($text == '▫️تنظیم شماره کارت') {
        step('set_card_number');
        sendMessage($from_id, "🪪 لطفا شماره کارت خود را به صورت صحیح و دقیق ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_card_number') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `payment_setting` SET `card_number` = '$text'");
            sendMessage($from_id, "✅ شماره کارت ارسالی شما با موفقیت تنظیم شد !\n\n◽️شماره کارت : <code>$text</code>", $manage_payment);
        } else {
            sendMessage($from_id, "❌ شماره کارت ارسالی شما اشتباه است !", $back_panel);
        }
    }
    
    elseif ($text == '▫️تنظیم صاحب شماره کارت') {
        step('set_card_number_name');
        sendMessage($from_id, "#️⃣ نام صاحب کارت را به صورت دقیق و صحیح ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_card_number_name') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `card_number_name` = '$text'");
        sendMessage($from_id, "✅ صاحب شماره کارت ارسالی شما با موفقیت تنظیم شد !\n\n◽صاحب ️شماره کارت : <code>$text</code>", $manage_payment);
    }
    
    elseif ($text == '◽ NOWPayments') {
        step('set_nowpayment_token');
        sendMessage($from_id, "🔎 لطفا api_key خود را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_nowpayment_token') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `nowpayment_token` = '$text'");
        sendMessage($from_id, "✅ با موفقیت تنظیم شد !", $manage_payment);
    }
    
    elseif ($text == '▫️آیدی پی') {
        step('set_idpay_token');
        sendMessage($from_id, "🔎 لطفا api_key آیدی پی خود را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_idpay_token') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `idpay_token` = '$text'");
        sendMessage($from_id, "✅ با موفقیت تنظیم شد !", $manage_payment);
    }
    
    elseif ($text == '▫️زرین پال') {
        step('set_zarinpal_token');
        sendMessage($from_id, "🔎 لطفا api_key زرین پال خود را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_zarinpal_token') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `zarinpal_token` = '$text'");
        sendMessage($from_id, "✅ با موفقیت تنظیم شد !", $manage_payment);
    }
    
    // -----------------manage copens ----------------- //
    elseif ($text == '🎁 مدیریت کد تخفیف' or $data == 'back_copen') {
        step('none');
        if (isset($text)) {
            sendMessage($from_id, "🎁 به بخش مدیریت کد تخفیف ربات خوش آمدید!\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $manage_copens);
        } else {
            editMessage($from_id, "🎁 به بخش مدیریت کد تخفیف ربات خوش آمدید!\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید : \n◽️@ZanborPanel", $message_id, $manage_copens);
        }
    }
    
    elseif ($data == 'add_copen') {
        step('add_copen');
        editMessage($from_id, "🆕 کد تخفیف خود را ارسال کنید :", $message_id, $back_copen);
    }
    
    elseif ($user['step'] == 'add_copen') {
        step('send_percent');
        file_put_contents('add_copen.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔢 کد تخفیف [ <code>$text</code> ] چند درصد باشد به صورت عدد صحیح ارسال کنید :", $back_copen);
    }
    
    elseif ($user['step'] == 'send_percent') {
        if (is_numeric($text)) {
            step('send_count_use');
            file_put_contents('add_copen.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🔢 چند نفر میتوانند از این کد تخفیف استفاده کنند به صورت عدد صحیح ارسال کنید :", $back_copen);
        } else {
            sendMessage($from_id, "❌ عدد ورودی اشتباه است !", $back_copen);
        }
    }
    
    elseif ($user['step'] == 'send_count_use') {
        if (is_numeric($text)) {
            step('none');
            $copen = explode("\n", file_get_contents('add_copen.txt'));
            $sql->query("INSERT INTO `copens` (`copen`, `percent`, `count_use`, `status`) VALUES ('{$copen[0]}', '{$copen[1]}', '{$text}', 'active')");
            sendMessage($from_id, "✅ کد تخفیف ارسالی شما با موفقیت اضافه شد !", $back_copen);
            unlink('add_copen.txt');
        } else {
            sendMessage($from_id, "❌ عدد ورودی اشتباه است !", $back_copen);
        }
    }
    
    elseif ($data == 'manage_copens') {
        step('manage_copens');
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [['text' => '▫️حذف', 'callback_data' => 'null'], ['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️تعداد', 'callback_data' => 'null'], ['text' => '▫️درصد', 'callback_data' => 'null'], ['text' => '▫️کد', 'callback_data' => 'null']];
            while ($row = $copens->fetch_assoc()) {
                $key[] = [['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']], ['text' => ($row['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_copen-'.$row['copen']], ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']], ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']], ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]];
            }
            $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ لیست همه ک تخفیف ها به شرح زیر است :\n\n⬅️ با کلیک بر روی هر کدام میتوانید مقدار فعلیشان را تغییر دهید.\n◽️@ZanborPanel", $message_id, $key);
        } else {
            alert('❌ هیچ کد تخفیفی در ربات ثبت نشده است !');
        }
    }
    
    elseif (strpos($data, 'delete_copen-') !== false) {
        $copen = explode('-', $data)[1];
        alert('🗑 کد تخفیف با موفقیت حذف شد.', false);
        $sql->query("DELETE FROM `copens` WHERE `copen` = '$copen'");
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [['text' => '▫️حذف', 'callback_data' => 'null'], ['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️تعداد', 'callback_data' => 'null'], ['text' => '▫️درصد', 'callback_data' => 'null'], ['text' => '▫️کد', 'callback_data' => 'null']];
            while ($row = $copens->fetch_assoc()) {
                $key[] = [['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']], ['text' => ($row['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_copen-'.$row['copen']], ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']], ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']], ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]];
            }
            $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ لیست همه ک تخفیف ها به شرح زیر است :\n\n⬅️ با کلیک بر روی هر کدام میتوانید مقدار فعلیشان را تغییر دهید.\n◽️@ZanborPanel", $message_id, $key);
        } else {
            editMessage($from_id, "❌ هیچ کد تخفیف دیگری وجود ندارد.", $message_id, $manage_copens);
        }
    }
    
    elseif (strpos($data, 'change_status_copen-') !== false) {
        $copen = explode('-', $data)[1];
        $copen_status = $sql->query("SELECT `status` FROM `copens` WHERE `copen` = '$copen'")->fetch_assoc();
        if ($copen_status['status'] == 'active') {
            $sql->query("UPDATE `copens` SET `status` = 'inactive' WHERE `copen` = '$copen'");    
        } else{
            $sql->query("UPDATE `copens` SET `status` = 'active' WHERE `copen` = '$copen'");
        }
        
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [['text' => '▫️حذف', 'callback_data' => 'null'], ['text' => '▫️وضعیت', 'callback_data' => 'null'], ['text' => '▫️تعداد', 'callback_data' => 'null'], ['text' => '▫️درصد', 'callback_data' => 'null'], ['text' => '▫️کد', 'callback_data' => 'null']];
            while ($row = $copens->fetch_assoc()) {
                if ($row['copen'] == $copen) {
                    $status = ($copen_status['status'] == 'active') ? '🔴' : '🟢';
                } else {
                    $status = ($row['status'] == 'active') ? '🟢' : '🔴';
                }
                $key[] = [['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']], ['text' => $status, 'callback_data' => 'change_status_copen-'.$row['copen']], ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']], ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']], ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]];
            }
            $key[] = [['text' => '🔙 بازگشت', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ لیست همه ک تخفیف ها به شرح زیر است :\n\n⬅️ با کلیک بر روی هر کدام میتوانید مقدار فعلیشان را تغییر دهید.\n◽️@ZanborPanel", $message_id, $key);
        } else {
            editMessage($from_id, "❌ هیچ کد تخفیف دیگری وجود ندارد.", $message_id, $manage_copens);
        }
    }
    
    elseif (strpos($data, 'change_countuse_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_countuse_copen-'.$copen);
        editMessage($from_id, "🔢 مقدار جدید را ارسال کنید :", $message_id, $back_copen);
    }
    
    elseif (strpos($user['step'], 'change_countuse_copen-') !== false) {
        if (is_numeric($text)) {
            $copen = explode('-', $user['step'])[1];
            $sql->query("UPDATE `copens` SET `count_use` = '$text' WHERE `copen` = '$copen'");
            sendMessage($from_id, "✅ عملیات با موفقیت انجام شد.", $manage_copens);
        } else {
            sendMessage($from_id, "❌ ورودی اشتباه است !", $back_copen);
        }
    }
    
    elseif (strpos($data, 'change_percent_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_percent_copen-'.$copen);
        editMessage($from_id, "🔢 مقدار جدید را ارسال کنید :", $message_id, $back_copen);
    }
    
    elseif (strpos($user['step'], 'change_percent_copen-') !== false) {
        if (is_numeric($text)) {
            $copen = explode('-', $user['step'])[1];
            $sql->query("UPDATE `copens` SET `percent` = '$text' WHERE `copen` = '$copen'");
            sendMessage($from_id, "✅ عملیات با موفقیت انجام شد.", $manage_copens);
        } else {
            sendMessage($from_id, "❌ ورودی اشتباه است !", $back_copen);
        }
    }
    
    elseif (strpos($data, 'change_code_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_code_copen-'.$copen);
        editMessage($from_id, "🔢 مقدار جدید را ارسال کنید :", $message_id, $back_copen);
    }
    
    elseif (strpos($user['step'], 'change_code_copen-') !== false) {
        $copen = explode('-', $user['step'])[1];
        $sql->query("UPDATE `copens` SET `copen` = '$text' WHERE `copen` = '$copen'");
        sendMessage($from_id, "✅ عملیات با موفقیت انجام شد.", $manage_copens);
    }
    
    // -----------------manage texts ----------------- //
    elseif ($text == '◽تنظیم متون ربات') {
        sendMessage($from_id, "⚙️️ به تنظیمات متون ربات خوش آمدید.\n\n👇🏻یکی از گزینه های زیر را انتخاب کنید :", $manage_texts);
    }
    
    elseif ($text == '✏️ متن استارت') {
        step('set_start_text');
        sendMessage($from_id, "👇 متن استارت را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_start_text') {
        step('none');
        $texts['start'] = str_replace('
        ', '\n', $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ متن استارت با موفقیت تنظیم شد !", $manage_texts);
    }
    
    elseif ($text == '✏️ متن تعرفه خدمات') {
        step('set_tariff_text');
        sendMessage($from_id, "👇 متن تعرفه خدمات را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'set_tariff_text') {
        step('none');
        $texts['service_tariff'] = str_replace('
        ', '\n', $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ متن تعرفه خدمات با موفقیت تنظیم شد !", $manage_text);
    }

    elseif ($text == '✏️ متن راهنمای اتصال') {
        step('none');
        sendMessage($from_id, "✏️ قصد تنظیم کدوم قسمت راهنمای اتصال را دارید ؟\n\n👇 یکی از گزینه های زیر را انتخاب کنید :", $set_text_edu);
    }

    elseif (strpos($data, 'set_edu_') !== false) {
        $sys = explode('_', $data)[2];
        step('set_edu_'.$sys);
        sendMessage($from_id, "👇🏻متن مورد نظر خود را به صورت صحیح ارسال کنید :\n\n⬅️ سیستم عامل انتخابی : <b>$sys</b>", $back_panel);
    }

    elseif (strpos($user['step'], 'set_edu_') !== false) {
        step('none');
        $sys = explode('_', $user['step'])[2];
        $texts['edu_' . $sys] = str_replace('
        ', '\n', $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ متن شما با موفقیت تنظیم شد.\n\n#️⃣ سیستم عامل : <b>$sys</b>", $manage_texts);
    }
    
    // -----------------manage admins ----------------- //
    elseif ($text == '➕ افزودن ادمین') {
        step('add_admin');
        sendMessage($from_id, "🔰ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'add_admin' and $text != '⬅️ بازگشت به مدیریت') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows != 0){
            step('none');
            $sql->query("INSERT INTO `admins` (`chat_id`) VALUES ('$text')");
            sendMessage($from_id, "✅ کاربر <code>$text</code> با موفقیت به لیست ادمین ها اضافه شد.", $manage_admin);
        } else {  
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);
        }
    }
    
    elseif ($text == '➖ حذف ادمین') {
        step('rem_admin');
        sendMessage($from_id, "🔰ایدی عددی کاربر مورد نظر را ارسال کنید :", $back_panel);
    }
    
    elseif ($user['step'] == 'rem_admin' and $text != '⬅️ بازگشت به مدیریت') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows > 0){
            step('none');
            $sql->query("DELETE FROM `admins` WHERE `chat_id` = '$text'");
            sendMessage($from_id, "✅ کاربر <code>$text</code> با موفقیت از لیست ادمین ها حذف شد.", $manage_admin);
        } else {
            sendMessage($from_id, "‼ کاربر <code>$text</code> عضو ربات نیست !", $back_panel);  
        }
        
    }
    
    elseif ($text == '⚙️ لیست ادمین ها') {
        $res = $sql->query("SELECT * FROM `admins`");
        if($res->num_rows == 0){
            sendmessage($from_id, "❌ لیست ادمین های ربات خالی است.");
            exit();
        }
        while($row = $res->fetch_array()){
            $key[] = [['text' => $row['chat_id'], 'callback_data' => 'delete_admin-'.$row['chat_id']]];
        }
        $count = $res->num_rows;
        $key = json_encode(['inline_keyboard' => $key]);
        sendMessage($from_id, "🔰لیست ادمین های ربات به شرح زیر است :\n\n🔎 تعداد کل ادمین ها : <code>$count</code>", $key);
    }
}

/**
* Project name: ZanborPanel
* Channel: @ZanborPanel
* Group: @ZanborPanelGap
 * Version: 2.5
**/
