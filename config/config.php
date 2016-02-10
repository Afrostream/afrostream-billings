<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#Database

#DATABASE_URL is filled in by heroku

if(getEnv('DB_HOST') === false) {
	putEnv('DB_HOST=localhost');
}

if(getEnv('DB_PORT') === false) {
	putEnv('DB_PORT=5432');
}

if(getEnv('DB_NAME') === false) {
	putEnv('DB_NAME=afr-billings-local');
}

if(getEnv('DB_USER') === false) {
	putEnv('DB_USER=postgres');
}

if(getEnv('DB_PASSWORD') === false) {
	putEnv('DB_PASSWORD=password');
}

#Billings API
if(getEnv('API_HTTP_AUTH_USER') === false) {
	putEnv('API_HTTP_AUTH_USER=admin');
}

if(getEnv('API_HTTP_AUTH_PWD') === false) {
	putEnv('API_HTTP_AUTH_PWD=pwd');
}

if(getEnv('RECURLY_WH_REPOST_URLS') === false) {
	putEnv('RECURLY_WH_REPOST_URLS=');
}

if(getEnv('API_HTTP_SECURE') === false) {
	putEnv('API_HTTP_SECURE=false');// /!\ true do not seem to work on heroku (https already 'on')
}

#Recurly API
if(getEnv('RECURLY_API_SUBDOMAIN') === false) {
	putEnv('RECURLY_API_SUBDOMAIN=johnarch');
}

if(getEnv('RECURLY_API_KEY') === false) {
	putEnv('RECURLY_API_KEY=67dbb29f0dbe4e219bc247a3b5387652');
}

#Recurly WebHooks
if(getEnv('RECURLY_WH_HTTP_AUTH_USER') === false) {
	putEnv('RECURLY_WH_HTTP_AUTH_USER=admin');
}
if(getEnv('RECURLY_WH_HTTP_AUTH_PWD') === false) {
	putEnv('RECURLY_WH_HTTP_AUTH_PWD=pwd');
}

#Gocardless API
if(getEnv('GOCARDLESS_API_ENV') === false) {
	putEnv('GOCARDLESS_API_ENV=sandbox');
}

if(getEnv('GOCARDLESS_API_KEY') === false) {
	putEnv('GOCARDLESS_API_KEY=YXwxcLeTGwGv3sPd1-CpmNh3nAsqtDfzGuV8_Vji');
}

if(getEnv('GOCARDLESS_WH_SECRET') === false) {
	putEnv('GOCARDLESS_WH_SECRET=nelsounet');
}

#SendGrid API

if(getEnv('SENDGRID_API_KEY') === false) {
	putEnv('SENDGRID_API_KEY=SG.lliM3Gp5QyuqgmQ36iLwLw.u3mP5Ne2PhP5Kohs8MO8rHhlA0Q3GLyZil45b9qgl5E');
}

if(getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_NEW_ID') === false) {
	putEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_NEW_ID=dde84299-e6fe-47a0-909b-1ee11417efe1');
}

if(getEnv('SENDGRID_FROM') === false) {
	putEnv('SENDGRID_FROM=abonnement@afrostream.tv');
}

if(getEnv('SENDGRID_FROM_NAME') === false) {
	putEnv('SENDGRID_FROM_NAME=Tonjé, Fondateur d\'Afrostream');
}

if(getEnv('SENGRID_BCC') === false) {
	putEnv('SENGRID_BCC=');
}

#Event (MAIL)
if(getEnv('EVENT_EMAIL_ACTIVATED') === false) {
	putEnv('EVENT_EMAIL_ACTIVATED=true');
}

if(getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION') === false) {
	putEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION=recurly');
}

#logger, #db_conn, ...

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
			$connection_string = NULL;
			if(getEnv('DATABASE_URL') === false) {
				$connection_string = 'host='.getEnv('DB_HOST').' port='.getEnv('DB_PORT').' dbname='.getEnv('DB_NAME').' user='.getEnv('DB_USER').' password='.getEnv('DB_PASSWORD');
			} else {
				$connection_string = getEnv('DATABASE_URL');
			}
			self::$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());
		}
		return(self::$db_conn);
	}
}

?>
