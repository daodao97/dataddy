<?php

$IF_SAFE_USER = TRUE;
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

define("APPLICATION_PATH",  dirname(dirname(__FILE__)));

date_default_timezone_set('Asia/Shanghai');

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/../bootstrap.php';

$config = new Yaf\Config\Ini(APPLICATION_PATH . '/conf/application.ini');

$app  = new Yaf\Application($config->toArray()['yaf']);
$app->bootstrap() //call bootstrap methods defined in Bootstrap.php
    ->run();

/* End of file index.php */
