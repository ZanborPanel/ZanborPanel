<?php

date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL ^ E_NOTICE);

$config = ['version' => '2.5', 'domain' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . explode('/', explode('html/', $_SERVER['SCRIPT_FILENAME'])[1])[0], 'token' => '[*TOKEN*]', 'dev' => '[*DEV*]', 'database' => ['db_name' => '[*DB-NAME*]', 'db_username' => '[*DB-USER*]', 'db_password' => '[*DB-PASS*]']];

$sql = new mysqli('localhost', $config['database']['db_username'], $config['database']['db_password'], $config['database']['db_name']);
$sql->set_charset("utf8mb4");

if ($sql->connect_error) {
	die(json_encode(['status' => false, 'msg' => $sql->connect_error, 'error' => 'database'], 423));
}

define('API_KEY', $config['token']);

if (file_exists('texts.json')) $texts = json_decode(file_get_contents('texts.json'), true);
# ----------------- [ <- variables -> ] ----------------- #

$update = json_decode(file_get_contents('php://input'));

if (isset($update->message)) {
    $message_id = $update->message->message_id;
    $first_name = isset($update->message->from->first_name) ? $update->message->from->first_name : '❌';
    $username = isset($update->message->from->username) ? '@' . $update->message->from->username : '❌';
    $from_id = $update->message->from->id;
    $chat_id = $update->message->chat->id;
    $text = $update->message->text;
} elseif (isset($update->callback_query)) {
    $from_id = $update->callback_query->from->id;
    $data = $update->callback_query->data;
    $query_id = $update->callback_query->id;
    $message_id = $update->callback_query->message->message_id;
    $username = isset($update->callback_query->from->username) ? '@' . $update->callback_query->from->username : "ندارد";
}

# ----------------- [ <- others -> ] ----------------- #

if (!isset($sql->connect_error)) {
    if ($sql->query("SHOW TABLES LIKE 'users'")->num_rows > 0 and $sql->query("SHOW TABLES LIKE 'admins'")->num_rows > 0 and $sql->query("SHOW TABLES LIKE 'test_account_setting'")->num_rows > 0) {
        if (isset($update)) {
            $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$from_id' LIMIT 1");
            if ($user->num_rows == 0) {
                $sql->query("INSERT INTO `users`(`from_id`) VALUES ('$from_id')");
            }
            
            $test_account = $sql->query("SELECT * FROM `test_account_setting`");
            $payment_setting = $sql->query("SELECT * FROM `payment_setting`");
            $spam_setting = $sql->query("SELECT * FROM `spam_setting`");
            $auth_setting = $sql->query("SELECT * FROM `auth_setting`");
            $settings = $sql->query("SELECT * FROM `settings`");
            # ------------------------------------------------- #
            $test_account_setting = $test_account->fetch_assoc();
            $payment_setting = $payment_setting->fetch_assoc();
            $spam_setting = $spam_setting->fetch_assoc();
            $auth_setting = $auth_setting->fetch_assoc();
            $settings = $settings->fetch_assoc();
            $user = $user->fetch_assoc();
        }
    }
}

# ----------------- [ <- functions -> ] ----------------- #

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $datas
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log('cURL Error: ' . curl_error($ch));
    } else {
        return json_decode($res);
    }
    curl_close($ch);
}

function sendMessage($chat_id, $text, $keyboard = null, $mrk = 'html') {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $mrk,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard
    ];
    return bot('sendMessage', $params);
}

function forwardMessage($from, $to, $message_id, $mrk = 'html') {
    $params = [
        'chat_id' => $to,
        'from_chat_id' => $from,
        'message_id' => $message_id,
        'parse_mode' => $mrk
    ];
    return bot('forwardMessage', $params);
}

function editMessage($chat_id, $text, $message_id, $keyboard = null, $mrk = 'html') {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => '⏳',
    ];
    bot('editMessageText', $params);
    
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => $mrk,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard
    ];
    return bot('editMessageText', $params);
}

function deleteMessage($chat_id, $message_id) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    return bot('deleteMessage', $params);
}

function alert($text, $show = true) {
    global $query_id;
    $params = [
        'callback_query_id' => $query_id,
        'text' => $text,
        'show_alert' => $show
    ];
    return bot('answerCallbackQuery', $params);
}

function step($step) {
    global $sql, $from_id;
    $sql->query("UPDATE `users` SET `step` = '$step' WHERE `from_id` = '$from_id'");
}

function checkURL($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 10]);
    $output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpcode;
}

function Conversion($byte, $one = 'GB') {
    if (isset($one)) {
        if ($one == 'GB') {
            $limit = floor($byte / 1048576);
        } elseif ($one == 'MB') {
            $limit = floor($byte / 1024);
        } elseif ($one == 'KB') {
            $limit = floor($byte);
        }
    }
    return $limit;
}

function convertToBytes($from) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $number = substr($from, 0, -2);
    $suffix = strtoupper(substr($from,-2));

    if(is_numeric(substr($suffix, 0, 1))) {
        return preg_replace('/[^\d]/', '', $from);
    }

    $exponent = array_flip($units)[$suffix] ?? null;
    if($exponent === null) {
        return null;
    }

    return $number * (1024 ** $exponent);
}

function isJoin($from_id) {
    global $sql;
    $lockSQL = $sql->query("SELECT `chat_id` FROM `lock`");
    if ($lockSQL->num_rows > 0) {
        $result = [];
        while ($id = $lockSQL->fetch_assoc()) {
            $status = bot('getChatMember', ['chat_id' => $id['chat_id'], 'user_id' => $from_id])->result->status;
            $result[] = $status;
        }
        return !in_array('left', $result);
    }
    return true;
}

function joinSend($from_id){
    global $sql, $texts;
    $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
    $buttons = [];
    while ($row = $lockSQL->fetch_assoc()) {
        $link = $row['chat_id'];
        if ($link) {
            $chat_member = bot('getChatMember', ['chat_id' => $link, 'user_id' => $from_id]);
            if ($chat_member->ok && $chat_member->result->status == 'left') {
                $link = str_replace("@", "", $link);
                $buttons[] = [['text' => $row['name'], 'url' => "https://t.me/$link"]];
            }
        }
    }
    if (count($buttons) > 0) {
        $buttons[] = [['text' => "عضو شدم ✅", 'callback_data' => 'join']];
        sendmessage($from_id, $texts['send_join'], json_encode(['inline_keyboard' => $buttons]));
    }
}

function zarinpalGenerator($from_id, $price, $code) {
    global $config, $payment_setting;
    
    $data = array(
        'merchant_id' => $payment_setting['zarinpal_token'],
        'amount' => $price * 10,
        'callback_url' => $config['domin'] . '/api/callback_zarinpal.php?from_id=' . $from_id . '&price=' . $price . '&code=' . $code,
        'description' => "$code",
    );
    
    $jsonData = json_encode($data);
    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)));
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if ($result['data']['code'] == 100) {
        return 'https://www.zarinpal.com/pg/StartPay/' . $result['data']['authority'];
    } else {
        return 'https://www.zarinpal.com/pg/StartPay/error:'.$result['data']['code'];
    }
}

function checkZarinpalFactor($merchend_id, $authority, $amount) {
	$data = array('merchant_id' => $merchend_id, 'authority' => $authority, 'amount' => $amount);
	$jsonData = json_encode($data);
	$ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
	curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)));
	$result = curl_exec($ch);
	curl_close($ch);
	$result = json_decode($result, true);
	if ($result['data']['code'] == 100) {
        return true;
    } else {
        return false;
    }
}

function idpayGenerator($from_id, $price, $code) {
    global $config, $payment_setting;
    
    $data = array(
        'order_id' => $code,
        'amount' => $price,
        'callback' => $config['domin'] . '/api/callback_idpay.php?from_id=' . $from_id . '&price=' . $price . '&code=' . $code,
    );
    
    $data = json_encode($data);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.idpay.ir/v1.1/payment',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-API-KEY: ' . $payment_setting['idpay_token'],
            'X-SANDBOX: 1'
      ),
    ));
    $response = json_decode(curl_exec($curl), true);
    curl_close($curl);
    return $response['link'] ?? 'https://idpay.ir';
}

function nowPaymentGenerator($price_amount, $price_currency, $pay_currency, $order_id) {
	global $payment_setting;

    $fields = array(
        "price_amount" => $price_amount,
        "price_currency" => $price_currency,
        "pay_currency" => $pay_currency,
        "order_id" => $order_id,
    );
    $fields = json_encode($fields);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $payment_setting['nowpayment_token'],
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function checkNowPayment($payment_id) {
	global $payment_setting;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $payment_setting['nowpayment_token']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function generateUUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand( 0, 0xffff ),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function loginPanelSanayi($address, $username, $password) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $address . '/login',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $username, 'password' => $password]),
        CURLOPT_COOKIEJAR => 'cookie.txt',
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function loginPanel($address, $username, $password) {
	$fields = array('username' => $username, 'password' => $password);
    $curl = curl_init($address . '/api/admin/token');
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded', 'accept: application/json')
    ));
    $response = curl_exec($curl);
    if ($response === false) {
        error_log('cURL Error: ' . curl_error($curl));
    } else {
        return json_decode($response, true);
    }
    curl_close($curl);
}

function createService($username, $limit, $expire_data, $proxies, $inbounds, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' .  $token, 'Content-Type: application/json'));
    if ($inbounds != 'null') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('proxies' => $proxies, 'inbounds' => $inbounds, 'expire' => $expire_data, 'data_limit' => $limit, 'username' => $username, 'data_limit_reset_strategy' => 'no_reset')));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('proxies' => $proxies, 'expire' => $expire_data, 'data_limit' => $limit, 'username' => $username, 'data_limit_reset_strategy' => 'no_reset')));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function getUserInfo($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function resetUserDataUsage($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username . '/reset');
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function getSystemStatus($token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/system');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function removeuser($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function Modifyuser($username, $data, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function inbounds($token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/inbounds');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function checkInbound($inbounds, $inbound) {
    $inbounds = json_decode($inbounds, true);
    $found_inbound = false;
    foreach ($inbounds as $protocol) {
        foreach ($protocol as $item) {
            if (strtoupper($item['tag']) == strtoupper($inbound)) {
                $found_inbound = true;
                break;
            }
        }
    }
    return $found_inbound ? true : false;
}

# ----------------- [ <- keyboard -> ] ----------------- #

if ($from_id == $config['dev']) {
    if ($test_account_setting['status'] == 'active' and $user['test_account'] == 'no') {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🔧 مدیریت']],
            [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
            [['text' => '🎁 سرویس تستی (رایگان)']],
            [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
            [['text' => '🔗 راهنمای اتصال'], ['text' => '📮 پشتیبانی آنلاین']]
        ], 'resize_keyboard' => true]);
    } else {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🔧 مدیریت']],
            [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
            [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
            [['text' => '🔗 راهنمای اتصال'], ['text' => '📮 پشتیبانی آنلاین']]
        ], 'resize_keyboard' => true]);
    }
} else {
    if ($test_account_setting['status'] == 'active' and $user['test_account'] == 'no') {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
            [['text' => '🎁 سرویس تستی (رایگان)']],
            [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
            [['text' => '🔗 راهنمای اتصال'], ['text' => '📮 پشتیبانی آنلاین']]
        ], 'resize_keyboard' => true]);
    } else {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
            [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
            [['text' => '🔗 راهنمای اتصال'], ['text' => '📮 پشتیبانی آنلاین']]
        ], 'resize_keyboard' => true]);
    }
}

$education = json_encode(['inline_keyboard' => [
    [['text' => '🍏 ios', 'callback_data' => 'edu_ios'], ['text' => '📱 android', 'callback_data' => 'edu_android']],
    [['text' => '🖥️ mac', 'callback_data' => 'edu_mac'], ['text' => '💻 windows', 'callback_data' => 'edu_windows']],
    [['text' => '🐧 linux', 'callback_data' => 'edu_linux']]
]]);

$back = json_encode(['keyboard' => [
    [['text' => '🔙 بازگشت']]
], 'resize_keyboard' => true]);

$cancel_copen = json_encode(['inline_keyboard' => [
    [['text' => '❌ لغو', 'callback_data' => 'cancel_copen']]
]]);

$confirm_service = json_encode(['keyboard' => [
    [['text' => '☑️ ایجاد سرویس']], [['text' => '❌  انصراف']]
], 'resize_keyboard' => true]);

$select_diposet_payment = json_encode(['inline_keyboard' => [
    [['text' => '▫️کارت به کارت', 'callback_data' => 'kart']],
    [['text' => '▫️زرین پال', 'callback_data' => 'zarinpal'], ['text' => '▫️آیدی پی', 'callback_data' => 'idpay']],
    [['text' => '▫️پرداخت ارزی', 'callback_data' => 'nowpayment']],
    [['text' => '❌ لغو عملیات', 'callback_data' => 'cancel_payment_proccess']]
]]);

$send_phone = json_encode(['keyboard' => [
    [['text' => '🔒 تایید و ارسال شماره', 'request_contact' => true]],
    [['text' => '🔙 بازگشت']]
], 'resize_keyboard' => true]);

$panel = json_encode(['keyboard' => [
    [['text' => '📞 اطلاعیه آپدیت ربات']],
    [['text' => '🔑 سیستم احراز هویت']],
    [['text' => '👥 مدیریت آمار ربات'], ['text' => '🌐 مدیریت سرور']],
    [['text' => '📤 مدیریت پیام'], ['text' => '👤 مدیریت کاربران']],
    [['text' => '⚙️ تنظیمات'], ['text' => '👮‍♂️مدیریت ادمین']],
    [['text' => '🔙 بازگشت']],
], 'resize_keyboard' => true]);

$manage_statistics = json_encode(['keyboard' => [
    [['text' => '👤 آمار ربات']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_server = json_encode(['keyboard' => [
    [['text' => '⏱ مدیریت اکانت تست']],
    [['text' => '⚙️ مدیریت پلن ها'], ['text' => '🎟 افزودن پلن']],
    [['text' => '⚙️ لیست سرور ها'], ['text' => '➕ افزودن سرور']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$select_panel = json_encode(['inline_keyboard' => [
    [['text' => '▫سنایی', 'callback_data' => 'sanayi']],
    [['text' => '▫️هیدیفای', 'callback_data' => 'hedifay'], ['text' => '▫️مرزبان', 'callback_data' => 'marzban']]
]]);

$add_plan_button = json_encode(['inline_keyboard' => [
    [['text' => '➕ پلن خرید سرویس', 'callback_data' => 'add_buy_plan']],
    [['text' => '➕ پلن زمانی', 'callback_data' => 'add_date_plan'], ['text' => '➕ پلن حجمی', 'callback_data' => 'add_limit_plan']],
]]);

$manage_plans = json_encode(['inline_keyboard' => [
    [['text' => '🔧 پلن خرید سرویس', 'callback_data' => 'manage_main_plan']],
    [['text' => '🔧 پلن زمانی', 'callback_data' => 'manage_date_plan'], ['text' => '🔧 پلن حجمی', 'callback_data' => 'manage_limit_plan']],
]]);

$end_inbound = json_encode(['keyboard' => [
    [['text' => '✔ اتمام و ثبت']],
], 'resize_keyboard' => true]);

$manage_test_account = json_encode(['inline_keyboard' => [
    [['text' => ($test_account_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_test_account_status'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
    [['text' => ($test_account_setting['panel'] == 'none') ? '🔴 وصل نیست' : '🟢 وصل است', 'callback_data' => 'change_test_account_panel'], ['text' => '▫️متصل به پنل :', 'callback_data' => 'null']],
    [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️تعداد اکانت تست :', 'callback_data' => 'null']],
    [['text' => $test_account_setting['volume'] . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️حجم :', 'callback_data' => 'null']],
    [['text' => $test_account_setting['time'] . ' ساعت', 'callback_data' => 'change_test_account_time'], ['text' => '▫️زمان :', 'callback_data' => 'null']],
]]);

$manage_auth = json_encode(['inline_keyboard' => [
    [['text' => ($auth_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth'], ['text' => 'ℹ️ سیستم احرازهویت :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['iran_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_iran'], ['text' => '🇮🇷 شماره ایران :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['virtual_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_virtual'], ['text' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿 شماره مجازی :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['both_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_all_country'], ['text' => '🌎 همه شماره ها :', 'callback_data' => 'null']],
]]);

$manage_service = json_encode(['keyboard' => [
    [['text' => '#⃣ لیست همه سرویس ها']],
    [['text' => '➖ حذف سرویس'], ['text' => '➕ افزودن سرویس']],
    [['text' => 'ℹ️ اطلاعات یک سرویس']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_message = json_encode(['keyboard' => [
    [['text' => '🔎 وضعیت ارسال / فوروارد همگانی']],
    [['text' => '📬 فوروارد همگانی'], ['text' => '📬 ارسال همگانی']],
    [['text' => '📞 ارسال پیام به کاربر']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_user = json_encode(['keyboard' => [
    [['text' => '🔎 اطلاعات کاربر']],
    [['text' => '➖ کسر موجودی'], ['text' => '➕ افزایش موجودی']],
    [['text' => '❌ مسدود کردن'], ['text' => '✅ آزاد کردن']],
    [['text' => '📤 ارسال پیام به کاربر']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_admin = json_encode(['keyboard' => [
    [['text' => '➖ حذف ادمین'], ['text' => '➕ افزودن ادمین']],
    [['text' => '⚙️ لیست ادمین ها']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_setting = json_encode(['keyboard' => [
    [['text' => '🚫 مدیریت ضد اسپم']],
    [['text' => '◽کانال ها'], ['text' => '◽بخش ها']],
    [['text' => '◽تنظیم متون ربات'], ['text' => '◽تنظیمات درگاه پرداخت']],
    [['text' => '🎁 مدیریت کد تخفیف']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_copens = json_encode(['inline_keyboard' => [
    [['text' => '➕افزودن تخفیف', 'callback_data' => 'add_copen'], ['text' => '✏️ مدیریت', 'callback_data' => 'manage_copens']]
]]);

$manage_spam = json_encode(['inline_keyboard' => [
    [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️وضعیت :', 'callback_data' => 'null']],
    [['text' => ($spam_setting['type'] == 'ban') ? '🚫 مسدود' : '⚠️ اخطار', 'callback_data' => 'change_type_spam'], ['text' => '▫️مدل برخورد :', 'callback_data' => 'null']],
    [['text' => $spam_setting['time'] . ' ثانیه', 'callback_data' => 'change_time_spam'], ['text' => '▫️زمان : ', 'callback_data' => 'null']],
    [['text' => $spam_setting['count_message'] . ' عدد', 'callback_data' => 'change_count_spam'], ['text' => '▫️تعداد پیام : ', 'callback_data' => 'null']],
]]);

$manage_payment = json_encode(['keyboard' => [
    [['text' => '✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات']],
    [['text' => '▫️تنظیم صاحب شماره کارت'], ['text' => '▫️تنظیم شماره کارت']],
    [['text' => '▫️زرین پال'], ['text' => '▫️آیدی پی']],
    [['text' => '◽ NOWPayments']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_off_on_paymanet = json_encode(['inline_keyboard' => [
    [['text' => ($payment_setting['zarinpal_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_zarinpal'], ['text' => '▫️زرین پال :', 'callback_data' => 'null']],
    [['text' => ($payment_setting['idpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_idpay'], ['text' => '▫️آیدی پی :', 'callback_data' => 'null']],
    [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
    [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️کارت به کارت :', 'callback_data' => 'null']]
]]);

$manage_texts = json_encode(['keyboard' => [
    [['text' => '✏️ متن تعرفه خدمات'], ['text' => '✏️ متن استارت']],
    [['text' => '✏️ متن راهنمای اتصال']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$set_text_edu = json_encode(['inline_keyboard' => [
    [['text' => '🍏 ios', 'callback_data' => 'set_edu_ios'], ['text' => '📱 android', 'callback_data' => 'set_edu_android']],
    [['text' => '🖥️ mac', 'callback_data' => 'set_edu_mac'], ['text' => '💻 windows', 'callback_data' => 'set_edu_windows']],
    [['text' => '🐧 linux', 'callback_data' => 'set_edu_linux']]
]]);

$cancel = json_encode(['keyboard' => [
    [['text' => '❌ انصراف']]
], 'resize_keyboard' => true]);

$cancel_add_server = json_encode(['keyboard' => [
    [['text' => '❌ انصراف و بازگشت']]
], 'resize_keyboard' => true]);

$back_panel = json_encode(['keyboard' => [
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$back_panellist = json_encode(['inline_keyboard' => [
    [['text' => '🔙 بازگشت به لیست پنل ها', 'callback_data' => 'back_panellist']],
]]);

$back_services = json_encode(['inline_keyboard' => [
    [['text' => '🔙 بازگشت', 'callback_data' => 'back_services']]
]]);

$back_account_test = json_encode(['inline_keyboard' => [
    [['text' => '🔙 بازگشت', 'callback_data' => 'back_account_test']]
]]);

$back_spam = json_encode(['inline_keyboard' => [
    [['text' => '🔙 بازگشت', 'callback_data' => 'back_spam']]
]]);

$back_copen = json_encode(['inline_keyboard' => [
    [['text' => '🔙 بازگشت', 'callback_data' => 'back_copen']]
]]);
