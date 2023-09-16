<?php

if (!isset($_GET['db_username'], $_GET['db_password'], $_GET['db_name'])) die(json_encode(['status' => false, 'msg' => 'Database operation failed -> ' . $sql->connect_error, 'error_code' => 401], 448));

$sql = new mysqli('localhost', $_GET['db_username'], $_GET['db_password'], $_GET['db_name']);
$sql->set_charset("utf8mb4");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `users` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` bigint(13) NOT NULL,
    `step` varchar(50) COLLATE utf8mb4_bin DEFAULT 'none',
    `coin` int DEFAULT 0,
    `count_service` int DEFAULT 0,
    `count_charge` int DEFAULT 0,
    `phone` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL,
    `test_account` varchar(10) COLLATE utf8mb4_bin DEFAULT 'no',
    `count_warn` varchar(10) COLLATE utf8mb4_bin DEFAULT '0',
    `view_status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active',
    `timestamp` varchar(50) COLLATE utf8mb4_bin DEFAULT '0',
    `status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `panels` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `login_link` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `username` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `password` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `count_create` varchar(50) COLLATE utf8mb4_bin DEFAULT '0',
    `qr_code` varchar(30) COLLATE utf8mb4_bin DEFAULT 'active',
    `protocols` varchar(50) COLLATE utf8mb4_bin DEFAULT 'vless|',
    `flow` varchar(15) COLLATE utf8mb4_bin DEFAULT 'flowon',
    `code` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `token` varchar(500) COLLATE utf8mb4_bin NOT NULL,
    `type` varchar(30) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `hiddify_panels` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `login_link` TEXT COLLATE utf8mb4_bin NOT NULL,
    `token` TEXT COLLATE utf8mb4_bin NOT NULL,
    `count_create` varchar(50) COLLATE utf8mb4_bin DEFAULT '0',
    `qr_code` varchar(30) COLLATE utf8mb4_bin DEFAULT 'active',
    `code` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `type` varchar(30) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `sanayi_panel_setting` (
    `code` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `inbound_id` varchar(10) COLLATE utf8mb4_bin DEFAULT NULL,
    `example_link` TEXT COLLATE utf8mb4_bin DEFAULT NULL,
    `flow` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `marzban_inbounds` (
    `panel` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `inbound` TEXT COLLATE utf8mb4_bin DEFAULT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL,
    `status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `orders` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `location` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `protocol` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `volume` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `link` TEXT COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL,
    `type` varchar(20) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `factors` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `sends` (
    `send` varchar(50) PRIMARY KEY,
    `step` varchar(50) DEFAULT NULL,
    `user` INT(11) DEFAULT NULL,
    `type` varchar(50) DEFAULT NULL,
    `text` varchar(7000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `category` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `limit` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `category_limit` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `limit` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `category_date` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `service_factors` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `location` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `protocol` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `plan` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(200) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `lock` (
    `id` int(11) AUTO_INCREMENT PRIMARY KEY,
    `chat_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `name` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `payment_setting` (
    `zarinpal_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `idpay_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `nowpayment_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `card_number` varchar(20) COLLATE utf8mb4_bin DEFAULT 'none',
    `card_number_name` varchar(100) COLLATE utf8mb4_bin DEFAULT 'none',
    `zarinpal_status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'inactive',
    `idpay_status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'inactive',
    `nowpayment_status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'inactive',
    `card_status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `test_account_setting` (
    `panel` varchar(20) COLLATE utf8mb4_bin DEFAULT 'none',
    `volume` varchar(20) COLLATE utf8mb4_bin DEFAULT '0',
    `time` varchar(20) COLLATE utf8mb4_bin DEFAULT '0',
    `status` varchar(50) COLLATE utf8mb4_bin DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `test_account` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `location` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `volume` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `link` TEXT COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `admins` (
    `chat_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `settings` (
    `log_channel` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `count_warn_ban` varchar(50) COLLATE utf8mb4_bin DEFAULT '3'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `spam_setting` (
    `type` varchar(20) COLLATE utf8mb4_bin DEFAULT 'ban',
    `time` varchar(20) COLLATE utf8mb4_bin DEFAULT '3',
    `count_message` varchar(20) COLLATE utf8mb4_bin DEFAULT '10',
    `status` varchar(50) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `copens` (
    `copen` varchar(100) COLLATE utf8mb4_bin NOT NULL,
    `percent` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `count_use` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(50) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `notes` (
    `note` TEXT COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(30) COLLATE utf8mb4_bin NOT NULL,
    `type` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

mysqli_multi_query($sql, "CREATE TABLE IF NOT EXISTS `auth_setting` (
    `iran_number` varchar(15) COLLATE utf8mb4_bin DEFAULT NULL,
    `virtual_number` varchar(15) COLLATE utf8mb4_bin DEFAULT NULL,
    `both_number` varchar(15) COLLATE utf8mb4_bin DEFAULT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

if ($sql->query("SELECT * FROM `auth_setting`")->num_rows == 0) $sql->query("INSERT INTO `auth_setting` (`iran_number`, `virtual_number`, `both_number`) VALUES ('inactive', 'inactive', 'inactive')");
if ($sql->query("SELECT * FROM `settings`")->num_rows == 0) $sql->query("INSERT INTO `settings` (`count_warn_ban`) VALUES ('3')");
if ($sql->query("SELECT * FROM `spam_setting`")->num_rows == 0) $sql->query("INSERT INTO `spam_setting` (`type`) VALUES ('ban')");
if ($sql->query("SELECT * FROM `test_account_setting`")->num_rows == 0) $sql->query("INSERT INTO `test_account_setting` (`panel`) VALUES ('none')");
if ($sql->query("SELECT * FROM `sends` WHERE `send` = 'no'")->num_rows == 0) $sql->query("INSERT INTO `sends` (`send`) VALUES('no');");
if ($sql->query("SELECT * FROM `payment_setting`")->num_rows == 0) $sql->query("INSERT INTO `payment_setting` (`zarinpal_token`, `idpay_token`, `nowpayment_token`) VALUES ('none', 'none', 'none')");

if ($sql->connect_error) {
	echo json_encode(['status' => false, 'msg' => '❌ The connection with the database encountered an error : ' . $sql->connect_error, 'status_code' => 401], 448);
} else {
    echo json_encode(['status' => true, 'msg' => '✅ The database operation was completed successfully.', 'status_code' => 200], 448);
}
