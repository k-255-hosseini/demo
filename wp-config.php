<?php
// ** تنظیمات پایگاه داده - این اطلاعات را از میزبان خود بگیرید ** //
/** نام پایگاه داده برای وردپرس */
define('DB_NAME', 'wordpress');

/** نام کاربری پایگاه داده */
define('DB_USER', 'root');

/** رمز عبور پایگاه داده */
define('DB_PASSWORD', '');

/** میزبان پایگاه داده */
define('DB_HOST', 'localhost');

/** پیشوند جدول */
$table_prefix  = 'wp_';

/** حالت وردپرس */
define('WP_DEBUG', false);

/* این خط را تغییر ندهید */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** راه‌اندازی وردپرس */
require_once(ABSPATH . 'wp-settings.php');
