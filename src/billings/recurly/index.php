<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "Hello World" . "\n";

$log = new Logger('afrostream-billings');
$log->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));


$log->addInfo('POST='.'php://input');

$dom = new DomDocument();

$dom->loadXML('php://input');

$root_node = $dom->documentElement;
$log->addInfo('root_node='.$root_node->nodeName);

?>