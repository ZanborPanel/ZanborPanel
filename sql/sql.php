<?php

include_once '../config.php';

mysqli_multi_query($sql, "
CREATE TABLE IF NOT EXISTS `users` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` bigint(13) NOT NULL,
    `step` varchar(50) COLLATE utf8mb4_bin DEFAULT 'none',
    `coin` int DEFAULT 0,
    `count_service` int DEFAULT 0,
    `count_charge` int DEFAULT 0,
    `phone` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL,
    `view_status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active',
    `status` varchar(15) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `panels` (
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
    `status` varchar(20) COLLATE utf8mb4_bin DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `orders` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `location` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `protocol` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `volume` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `link` varchar(500) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `factors` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `sends` (
    `send` varchar(50) PRIMARY KEY,
    `step` varchar(50) DEFAULT NULL,
    `user` INT(11) DEFAULT NULL,
    `type` varchar(50) DEFAULT NULL,
    `text` varchar(7000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `category` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `limit` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `date` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `service_factors` (
    `row` int(200) AUTO_INCREMENT PRIMARY KEY,
    `from_id` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `location` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `protocol` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `plan` varchar(50) COLLATE utf8mb4_bin NOT NULL,
    `price` varchar(200) COLLATE utf8mb4_bin NOT NULL,
    `code` varchar(20) COLLATE utf8mb4_bin NOT NULL,
    `status` varchar(15) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `lock` (
    `id` int(11) AUTO_INCREMENT PRIMARY KEY,
    `chat_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
    `name` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `payment_setting` (
    `zarinpal_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `idpay_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `nowpayment_token` varchar(200) COLLATE utf8mb4_bin DEFAULT 'none',
    `card_number` varchar(20) COLLATE utf8mb4_bin DEFAULT 'none',
    `card_number_name` varchar(100) COLLATE utf8mb4_bin DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `admins` (
    `chat_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
");

if ($sql->query("SELECT * FROM `sends` WHERE `send` = 'no'")->num_rows == 0) $sql->query("INSERT INTO `sends` (`send`) VALUES('no');");
if ($sql->query("SELECT * FROM `payment_setting`")->num_rows == 0) $sql->query("INSERT INTO `payment_setting` (`zarinpal_token`, `idpay_token`, `nowpayment_token`) VALUES ('none', 'none', 'none')");

if ($sql->connect_error) {
	echo json_encode(['ok' => false, 'msg' => 'Database operation failed --> : ' . $sql->connect_error, 'error_code' => 401], 448);
} else {
    echo json_encode(['ok' => true, 'msg' => 'The database operation was completed successfully.', 'error_code' => 200], 448);
}

