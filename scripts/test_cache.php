<?php
define("BASE_PATH", dirname(__DIR__));
require BASE_PATH . "/vendor/autoload.php";
$dbFile = BASE_PATH . '/storage/cache/phpspreadsheet.sqlite';
$cacheAdapter = new \Symfony\Component\Cache\Adapter\PdoAdapter('sqlite:' . $dbFile, 'phpspreadsheet');
$cacheAdapter->createTable();
$cache = new \Symfony\Component\Cache\Psr16Cache($cacheAdapter);
$cache->set('test_key', 'test_value');
echo $cache->get('test_key');

