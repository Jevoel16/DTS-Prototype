<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';
date_default_timezone_set('Asia/Manila');

$job = new \App\Jobs\IntegrityCheckJob('test-id-123');
$job->handle();

$results = file_get_contents(BASE_PATH . '/cache/integrity-check-result.json');
echo $results;
