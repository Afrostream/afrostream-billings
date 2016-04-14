<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#General

if(getEnv('LOG_REQUESTS_ACTIVATED') === false) {
	putEnv('LOG_REQUESTS_ACTIVATED=1');
}

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
	putEnv('API_HTTP_SECURE=0');// /!\ true do not seem to work on heroku (https already 'on')
}

#Recurly API

if(getEnv('RECURLY_API_SUBDOMAIN') === false) {
	putEnv('RECURLY_API_SUBDOMAIN=johnarch');
}

if(getEnv('RECURLY_API_KEY') === false) {
	putEnv('RECURLY_API_KEY=67dbb29f0dbe4e219bc247a3b5387652');
}

if(getEnv('RECURLY_POSTPONE_ACTIVATED') === false) {
	putEnv('RECURLY_POSTPONE_ACTIVATED=1');
}

if(getEnv('RECURLY_POSTPONE_LIMIT_IN') === false) {
	putEnv('RECURLY_POSTPONE_LIMIT_IN=7');
}

if(getEnv('RECURLY_POSTPONE_LIMIT_OUT') === false) {
	putEnv('RECURLY_POSTPONE_LIMIT_OUT=28');
}

if(getEnv('RECURLY_POSTPONE_TO') === false) {
	putEnv('RECURLY_POSTPONE_TO=10');
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

if(getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID') === false) {
	putEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_CANCEL_ID=32685665-87ba-4c67-a726-395b58c2e36b');
}

if(getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_ID') === false) {
	putEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_ID=51b5b68f-3fc2-4fb3-b274-ec90d9ccfc20');
}

if(getEnv('SENDGRID_FROM') === false) {
	putEnv('SENDGRID_FROM=abonnement@afrostream.tv');
}

if(getEnv('SENDGRID_FROM_NAME') === false) {
	putEnv('SENDGRID_FROM_NAME=Tonjé, Fondateur d\'Afrostream');
}

if(getEnv('SENDGRID_BCC') === false) {
	putEnv('SENDGRID_BCC=');
}

#Event (MAIL)

if(getEnv('EVENT_EMAIL_ACTIVATED') === false) {
	putEnv('EVENT_EMAIL_ACTIVATED=1');
}

if(getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION') === false) {
	putEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION=recurly');
}

#Slack / Stats

if(getEnv('SLACK_STATS_ACTIVATED') === false) {
	putEnv('SLACK_STATS_ACTIVATED=0');
}

if(getEnv('SLACK_STATS_CHANNEL') === false) {
	putEnv('SLACK_STATS_CHANNEL=growth');
}

if(getEnv('BOUYGUES_BHA_ACTIVATED') === false) {
	putEnv('BOUYGUES_BHA_ACTIVATED=0');
}

#Cashway

if(getEnv('CASHWAY_API_URL') === false) {
	putEnv('CASHWAY_API_URL=https://api-staging.cashway.fr/');
}

if(getEnv('CASHWAY_API_HTTP_AUTH_USER') === false) {
	putEnv('CASHWAY_API_HTTP_AUTH_USER=73123a828c94b4b1f2b4e4669a5cae6e187090ff7ba510d6de449d707e980951');
}

if(getEnv('CASHWAY_API_HTTP_AUTH_PWD') === false) {
	putEnv('CASHWAY_API_HTTP_AUTH_PWD=81c2c892f30d8304c6af90a387001765132a2887b76a7bdbe39af9131f1be9b9');
}

if(getEnv('CASHWAY_USER_AGENT') === false) {
	putEnv('CASHWAY_USER_AGENT=afrbillingsapi/1.0');
}

if(getEnv('CASHWAY_USE_STAGING') === false) {
	putEnv('CASHWAY_USE_STAGING=1');
}

if(getEnv('CASHWAY_WH_SECRET') === false) {
	putEnv('CASHWAY_WH_SECRET=DakUdoycsOctoaphObyo');
}

#logger, #db_conn, ...

class config {

	public static $timezone = "Europe/Paris";
	
	private static $logger;
	
	public static function init() {
		self::getLogger();
		self::getDbConn();
	}
	
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

config::init();

?>
