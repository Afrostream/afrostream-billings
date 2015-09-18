<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#Database

define('DBHOST', 'localhost');
define('DBPORT', '5432');
define('DBNAME', 'afrostream');
define('DBUSER', 'neo');
define('DBPASSWORD', 'toto');

#WebHooks
#

#Logs
#

class config {
	
	private static $logger;
	
	public static function getLogger() {
		if(self::$logger == NULL) {
			self::$logger = new Logger('afrostream-billings');
			self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
		}
		return(self::$logger);
	}
}

?>