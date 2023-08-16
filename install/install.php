<?php

if (isset($_POST['token']) and isset($_POST['admin-id']) and isset($_POST['db-name']) and isset($_POST['db-user']) and isset($_POST['db-pass'])) {
	if (!file_exists('zanbor.install')) {
		if (file_exists('../config.php') and file_exists('../index.php') and file_exists('../texts.json') and file_exists('../sql/sql.php')) {
			$getTokenStatus = json_decode(file_get_contents('https://api.telegram.org/bot' . $_POST['token'] . '/getMe'), true)['ok'];
			if ($getTokenStatus == true) {
				$replace = str_replace(['[*TOKEN*]', '[*DEV*]', '[*DB-NAME*]', '[*DB-USER*]', '[*DB-PASS*]'], [$_POST['token'], $_POST['admin-id'], $_POST['db-name'], $_POST['db-user'], $_POST['db-pass']], file_get_contents('../config.php'));
				file_put_contents('../config.php', $replace);
				$webhook = json_decode(file_get_contents('https://api.telegram.org/bot' . $_POST['token'] . '/setWebhook?url=' . str_replace('/install/install.html', '', $_SERVER['HTTP_REFERER']) . '/index.php'), true);
				if ($webhook['ok'] == false) die('setwebhook is failed.');
				$connect = json_decode(file_get_contents(str_replace('/install/install.html', '', $_SERVER['HTTP_REFERER']) . '/sql/sql.php'), true);
				if ($connect['ok'] == false) die($connect['msg']);
				touch('zanbor.install');
				$send_message = json_decode(file_get_contents('https://api.telegram.org/bot' . $_POST['token'] . '/sendMessage?chat_id=' . $_POST['admin-id'] . '&text=' . urlencode(base64_decode('CuKchSDYsdio2KfYqiDYqNinINmF2YjZgdmC24zYqiDZhti12Kgg2LTYry4KCvCfmoAg2LHYqNin2Kog2LHYpyAvc3RhcnQg2qnZhtuM2K8uCgrwn5CdIC0gQFphbmJvclBhbmVsIC0gQFphbmJvclBhbmVsR2FwCg=='))), true);
			    print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">ربات شما با موفقیت نصب شد ✅</h2>';
			} else {
				print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">توکن ارسالی اشتباه است ❌</h2>';
			}
		} else {
			print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فایل های اصلی ربات یافت نشد ❌</h2>';
		}
	} else {
		print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">ربات قبلا یک بار نصب شده است ❌</h2>';
	}
} else {
	print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">مقادیر اجباری به درستی ارسال نشده است ❌</h2>';
}

?>