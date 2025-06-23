<?php

// Suppress deprecated warnings very early
@ini_set('display_errors', '1');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Buffer output to suppress early echo
ob_start();

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

//if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
//}
