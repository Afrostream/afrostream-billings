<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "Hello World" . "\n";

$log = new Logger('afrostream-billings');
$log->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

$log->addWarning('Foo');
?>