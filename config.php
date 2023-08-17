<?php

date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL ^ E_NOTICE);

$config = [
    'version' => '1.1.1',
    'token' => '[*TOKEN*]',
    'dev' => '[*DEV*]',
    'database' => ['db_name' => '[*DB-NAME*]', 'db_username' => '[*DB-USER*]', 'db_password' => '[*DB-PASS*]',]
];

$sql = new mysqli('localhost', $config['database']['db_username'], $config['database']['db_password'], $config['database']['db_name']);
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
    global $config, $sql;
    
    $zainpal_mer = $sql->query("SELECT `zarinpal_token` FROM `payment_setting`")->fetch_assoc()['zarinpal_token'] ?? 0;
    $data = array(
        'merchant_id' => $zainpal_mer,
        'amount' => $price,
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
	if ($err) {
	    return false;
	} else {
	    if ($result['data']['code'] == 100) {
	        return true;
	    } else {
	        return false;
	    }
	}
}

function idpayGenerator($from_id, $price, $code) {
    global $config, $sql;
    
    $idpay_mer = $sql->query("SELECT `idpay_token` FROM `payment_setting`")->fetch_assoc()['idpay_token'] ?? 0;
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
            'X-API-KEY: ' . $idpay_mer,
            'X-SANDBOX: 1'
      ),
    ));
    $response = json_decode(curl_exec($curl), true);
    curl_close($curl);
    return $response['link'] ?? 'https://idpay.ir';
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

function createService($username, $limit, $expire_data, $proxies, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' .  $token, 'Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('proxies' => $proxies, 'expire' => $expire_data, 'data_limit' => $limit, 'username' => $username, 'data_limit_reset_strategy' => 'no_reset')));
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

# ----------------- [ <- others -> ] ----------------- #

if (!isset($sql->connect_error)) {
    if ($sql->query("SHOW TABLES LIKE 'users'")->num_rows > 0 and $sql->query("SHOW TABLES LIKE 'admins'")->num_rows > 0) {
        if (isset($update)) {
            $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$from_id' LIMIT 1");
            if ($user->num_rows == 0) {
                $sql->query("INSERT INTO `users`(`from_id`) VALUES ('$from_id')");
            }
            $user = $user->fetch_assoc();
        }
    }
}

# ----------------- [ <- keyboard -> ] ----------------- #

if ($from_id == $config['dev']) {
    $start_key = json_encode(['keyboard' => [
        [['text' => '🔧 مدیریت']],
        [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
        [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
        [['text' => '📮 پشتیبانی آنلاین']]
    ], 'resize_keyboard' => true]);
} else {
    $start_key = json_encode(['keyboard' => [
        [['text' => '🛍 سرویس های من'], ['text' => '🛒 خرید سرویس']],
        [['text' => '👤 پروفایل'], ['text' => '🛒 تعرفه خدمات'], ['text' => '💸 شارژ حساب']],
        [['text' => '📮 پشتیبانی آنلاین']]
    ], 'resize_keyboard' => true]);
}

$back = json_encode(['keyboard' => [
    [['text' => '🔙 بازگشت']]
], 'resize_keyboard' => true]);

$select_diposet_payment = json_encode(['inline_keyboard' => [
    [['text' => '▫️کارت به کارت', 'callback_data' => 'kart']],
    [['text' => '▫️زرینپال', 'callback_data' => 'zarinpal'], ['text' => '▫️آیدی پی', 'callback_data' => 'idpay']],
    [['text' => '❌ لغو عملیات', 'callback_data' => 'cancel_payment_proccess']]
]]);

$panel = json_encode(['keyboard' => [
    [['text' => '📞 اطلاعیه آپدیت ربات']],
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
    [['text' => '⚙️ مدیریت پلن ها'], ['text' => '🎟 افزودن پلن']],
    [['text' => '⚙️ لیست سرور ها'], ['text' => '➕ افزودن سرور']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

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
    [['text' => '◽کانال ها'], ['text' => '◽بخش ها']],
    [['text' => '◽تنظیم متون ربات'], ['text' => '◽تنظیمات درگاه پرداخت']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_payment = json_encode(['keyboard' => [
    [['text' => '✏️ وضعیت خاموش/روشن درگاه پرداخت های ربات']],
    [['text' => '▫️تنظیم صاحب شماره کارت'], ['text' => '▫️تنظیم شماره کارت']],
    [['text' => '▫️زرین پال'], ['text' => '▫️آیدی پی']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

$manage_texts = json_encode(['keyboard' => [
    [['text' => 'متن استارت']],
    [['text' => 'متن تعرفه خدمات']],
    [['text' => '⬅️ بازگشت به مدیریت']]
], 'resize_keyboard' => true]);

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
