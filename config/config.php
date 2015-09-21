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
	
	private static $db_conn;
	
	public static function getDbConn() {
		if(self::$db_conn == null) {
			$connection_string = 'host='.DBHOST.' port='.DBPORT.' dbname='.DBNAME.' user='.DBUSER.' password='.DBPASSWORD;
			self::$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>