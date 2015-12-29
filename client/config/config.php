<?php

require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if(getEnv('BILLINGS_API_URL') === false) {
	putEnv('BILLINGS_API_URL=http://afrostream-billings-staging.herokuapp.com');
}
if((getEnv('BILLINGS_API_HTTP_AUTH_USER') === false)) {
	putEnv('BILLINGS_API_HTTP_AUTH_USER=admin');
}

if((getEnv('BILLINGS_API_HTTP_AUTH_PWD') === false)) {
	putEnv('BILLINGS_API_HTTP_AUTH_PWD=pwd');
}

#logger, ...

class BillingsApiClientConfig {

	private static $logger;

	public static function getLogger() {
		if(self::$logger == NULL) {
			self::$logger = new Logger('afrostream-billings-client');
			self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
		}
		return(self::$logger);
	}
	
}

?>