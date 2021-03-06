<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Europe/Paris");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

#General

//sample : {"planChangeProposalsOnCancel":{"enabled":true,"internalPlans":[{"fromInternalPlanUuid":"toto1","toInternalPlanUuid":"tutu1"},{"fromInternalPlanUuid":"toto2","toInternalPlanUuid":"tutu2"}]}}
if(getEnv('CONFIG') === false) {
	putEnv('CONFIG={"planChangeProposalsOnCancel":{"enabled":true,"internalPlans":[]}}');
}

if(getEnv('LOCALE_COUNTRY_DEFAULT') === false) {
	putEnv('LOCALE_COUNTRY_DEFAULT=FR');
}

if(getEnv('LOCALE_LANGUAGE_DEFAULT') === false) {
	putEnv('LOCALE_LANGUAGE_DEFAULT=fr');
}

if(getEnv('PLATFORM_DEFAULT_ID') === false) {
	putEnv('PLATFORM_DEFAULT_ID=1');/* AFROSTREAM */
}

if(getEnv('DYNO') === false) {
	putEnv('DYNO=web-0');
}

if(getEnv('BILLINGS_ENV') === false) {
	putEnv('BILLINGS_ENV=staging');
}

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

if(getEnv('RECURLY_IMPORT_TRANSACTIONS_SLEEPING_TIME_IN_MILLIS') === false) {
	putEnv('RECURLY_IMPORT_TRANSACTIONS_SLEEPING_TIME_IN_MILLIS=250');
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

if(getEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_FP_ID') === false) {
	putEnv('SENDGRID_TEMPLATE_SUBSCRIPTION_ENDED_FP_ID=835e891b-c196-486e-8f0a-64394e62f737');
}

if(getEnv('SENDGRID_TEMPLATE_COUPON_OWN_STANDARD_NEW') === false) {
	putEnv('SENDGRID_TEMPLATE_COUPON_OWN_STANDARD_NEW=40c8532f-4117-434f-882d-81b4e5e50193');
}

if(getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_STANDARD_NEW') === false) {
	putEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_STANDARD_NEW=06e63db8-0cf9-4396-b527-1d9e70bee72b');
}

if(getEnv('SENDGRID_TEMPLATE_COUPON_OWN_SPONSORSHIP_NEW') === false) {
	putEnv('SENDGRID_TEMPLATE_COUPON_OWN_SPONSORSHIP_NEW=9a9a2f5b-d784-46f2-852d-0958185f7dd7');
}

if(getEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_SPONSORSHIP_NEW') === false) {
	putEnv('SENDGRID_TEMPLATE_COUPON_OFFERED_SPONSORSHIP_NEW=22a2e61c-565f-4270-a9bd-6ec7f592b3ed');
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

if(getEnv('SENDGRID_TO_IFNULL') === false) {
	putEnv('SENDGRID_TO_IFNULL=null@afrostream.tv');
}

if(getEnv('SENDGRID_VAR_couponAppliedSentence') === false) {
	putEnv('SENDGRID_VAR_couponAppliedSentence=La réduction de %couponAmountForDisplay% liée au code promo %couponCode% sera appliquée lors du prélèvement.');
}

if(getEnv('SENDGRID_TEMPLATE_SUFFIX') === false) {
	putEnv('SENDGRID_TEMPLATE_SUFFIX=');
}

#Event (MAIL)

if(getEnv('EVENT_EMAIL_ACTIVATED') === false) {
	putEnv('EVENT_EMAIL_ACTIVATED=1');
}

if(getEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION') === false) {
	putEnv('EVENT_EMAIL_PROVIDERS_EXCEPTION=recurly');
}

#Event (CLOUDAMQP)

if(getEnv('EVENT_CLOUDAMQP_ACTIVATED') === false) {
	putEnv('EVENT_CLOUDAMQP_ACTIVATED=0');
}

#Slack
//test channel : test-channel
if(getEnv('SLACK_ACTIVATED') === false) {
	putEnv('SLACK_ACTIVATED=0');
}

if(getEnv('SLACK_GROWTH_CHANNEL') === false) {
	putEnv('SLACK_GROWTH_CHANNEL=growth');
}

if(getEnv('SLACK_STATS_CHANNEL') === false) {
	putEnv('SLACK_STATS_CHANNEL=test-channel');
}

if(getEnv('SLACK_STATS_COUPONS_CHANNEL') === false) {
	putEnv('SLACK_STATS_COUPONS_CHANNEL=activation');
}

if(getEnv('SLACK_STATS_TRANSACTIONS_CHANNEL') === false) {
	putEnv('SLACK_STATS_TRANSACTIONS_CHANNEL=test-channel');
}

#Bouygues

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

if(getEnv('CASHWAY_COUPON_URL') === false) {
	putEnv('CASHWAY_COUPON_URL=https://staging-payments-afrostream.cashway.fr/1/b/');
}

if(getEnv('CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN_ACTIVATED') === false) {
	putEnv('CASHWAY_COUPON_ONE_BY_USER_FOR_EACH_CAMPAIGN_ACTIVATED=0');
}

#OrangeTV

if(getEnv('ORANGE_TV_API_URL') === false) {
	putEnv('ORANGE_TV_API_URL=https://iosw3sn-ba-rest.orange.com:8443/OTP/API_OTVP_Partners-1/user/v1');
}

if(getEnv('ORANGE_TV_HTTP_AUTH_USER') === false) {
	putEnv('ORANGE_TV_HTTP_AUTH_USER=OTP-OTP_AFR');
}

if(getEnv('ORANGE_TV_HTTP_AUTH_PWD') === false) {
	putEnv('ORANGE_TV_HTTP_AUTH_PWD=a]ar[9vU');
}

if(getEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH') === false) {
	putEnv('ORANGE_SUBSCRIPTION_PERIOD_LENGTH=1');
}

#BouyguesTV

if(getEnv('BOUYGUES_TV_API_URL') === false) {
	putEnv('BOUYGUES_TV_API_URL=https://idp.bouygtel.fr:20443/federation/eligibility');
}

if(getEnv('BOUYGUES_SUBSCRIPTION_PERIOD_LENGTH') === false) {
	putEnv('BOUYGUES_SUBSCRIPTION_PERIOD_LENGTH=1');
}

#Netsize

if(getEnv('NETSIZE_API_URL') === false) {
	putEnv('NETSIZE_API_URL=http://qa.pay.netsize.com/API/1.2/');
}

if(getEnv('NETSIZE_API_AUTH_KEY') === false) {
	putEnv('NETSIZE_API_AUTH_KEY=368b8163dca54e64a17ec098d63d2464');
}

if(getEnv('NETSIZE_API_SERVICE_ID') === false) {
	putEnv('NETSIZE_API_SERVICE_ID=1');
}

if(getEnv('NETSIZE_API_PRODUCT_TYPE') === false) {
	putEnv('NETSIZE_API_PRODUCT_TYPE=121');
}

if(getEnv('NETSIZE_SUBSCRIPTION_PERIOD_LENGTH') === false) {
	putEnv('NETSIZE_SUBSCRIPTION_PERIOD_LENGTH=1');
}

#If 1, always consider that customer is a subscriber
if(getEnv('BOUYGUES_TV_HACK_ACTIVATED') === false) {
	putEnv('BOUYGUES_TV_HACK_ACTIVATED=0');
}

#Braintree

if(getEnv('BRAINTREE_ENVIRONMENT') === false) {
	putEnv('BRAINTREE_ENVIRONMENT=sandbox');
}

if(getEnv('BRAINTREE_MERCHANT_ID') === false) {
	putEnv('BRAINTREE_MERCHANT_ID=vpchhx9ppk3xwrcy');
}

if(getEnv('BRAINTREE_PUBLIC_KEY') === false) {
	putEnv('BRAINTREE_PUBLIC_KEY=hpwk56f69q22bnqh');
}

if(getEnv('BRAINTREE_PRIVATE_KEY') === false) {
	putEnv('BRAINTREE_PRIVATE_KEY=d2cc0c2d62852a9555e7fa9119f89665');
}

// url au format sprintf 
if(getEnv('BRAINTREE_TRANSACTION_URL_DETAIL_FORMAT') === false) {
	putEnv('BRAINTREE_TRANSACTION_URL_DETAIL_FORMAT=https://sandbox.braintreegateway.com/merchants/%s/transactions/%s');
}

#proxy

if(getEnv('PROXY_HOST') === false) {
	putEnv('PROXY_HOST=proxy.adm.afrostream.net');
}

if(getEnv('PROXY_PORT') === false) {
	putEnv('PROXY_PORT=3128');
}

if(getEnv('PROXY_USER') === false) {
	putEnv('PROXY_USER=afrostream');
}

if(getEnv('PROXY_PWD') === false) {
	putEnv('PROXY_PWD=afrostream77');
}

# stripe api key
if(getEnv('STRIPE_API_KEY') === false) {
	putEnv('STRIPE_API_KEY=sk_test_VaFvskbZOobGZ1L3x1iGwzOk');
}

#Stripe WebHooks

if(getEnv('STRIPE_WH_HTTP_AUTH_USER') === false) {
	putEnv('STRIPE_WH_HTTP_AUTH_USER=admin');
}

if(getEnv('STRIPE_WH_HTTP_AUTH_PWD') === false) {
	putEnv('STRIPE_WH_HTTP_AUTH_PWD=pwd');
}

if(getEnv('STRIPE_WH_EVENT_INVOICE.PAYMENT_FAILED_ENABLED') === false) {
	putEnv('STRIPE_WH_EVENT_INVOICE.PAYMENT_FAILED_ENABLED=0');
}

#statsd

if(getEnv('STATSD_ACTIVATED') === false) {
	putEnv('STATSD_ACTIVATED=1');
}

if(getEnv('STATSD_HOST') === false) {
	putEnv('STATSD_HOST=graphite.afrostream.net');
}

if(getEnv('STATSD_PORT') === false) {
	putEnv('STATSD_PORT=8125');
}

if(getEnv('STATSD_NAMESPACE') === false) {
	putEnv('STATSD_NAMESPACE=afrostream-billings');
}

if(getEnv('STATSD_DYNO_MODULO') === false) {
	putEnv('STATSD_DYNO_MODULO=16');
}

if(getEnv('STATSD_KEY_PREFIX') === false) {
	$dyno = str_replace('.', '-', getEnv('DYNO'));
	$dynoNumber = substr($dyno, strrpos($dyno, '-') + 1) % getEnv('STATSD_DYNO_MODULO');
	$dyno = substr($dyno, 0, strrpos($dyno, '-')).'-'.$dynoNumber;
	putEnv('STATSD_KEY_PREFIX='.getEnv('BILLINGS_ENV').'.container.'.$dyno.'.worker.0.');
}

#

if(getEnv('CONTEXTS_SWITCH_EXPIRED_DATE_BOUNDARY_TO_COMMON_CONTEXT') === false) {
	putEnv('CONTEXTS_SWITCH_EXPIRED_DATE_BOUNDARY_TO_COMMON_CONTEXT=2016-11-21 23:59:59');
}

#Wecashup

if(getEnv('WECASHUP_MERCHANT_UID') === false) {
	putEnv('WECASHUP_MERCHANT_UID=bzmSSCP8WqUMDDH4sPb2w8hB14F2');
}

if(getEnv('WECASHUP_MERCHANT_PUBLIC_KEY') === false) {
	putEnv('WECASHUP_MERCHANT_PUBLIC_KEY=NoZ7voE0KDRSBnxaB7oqcGdWQnrLVxAZm9NLiEIMyYvq');
}

if(getEnv('WECASHUP_MERCHANT_SECRET') === false) {
	putEnv('WECASHUP_MERCHANT_SECRET=PwWqkwwq8L7nlb61');
}

if(getEnv('WECASHUP_API_URL') === false) {
	putEnv('WECASHUP_API_URL=https://www.wecashup.com/api/v1.0/merchants');
}

#MONEY

if(getEnv('CURRENCY_CONVERSION_ENABLED') === false) {
	putEnv('CURRENCY_CONVERSION_ENABLED=1');
}

if(getEnv('CURRENCY_CONVERSION_CACHE_TTL') === false) {
	putEnv('CURRENCY_CONVERSION_CACHE_TTL=21600');//21600s = 6 hours by default
}

if(getEnv('CURRENCY_CONVERSION_INTERNALPLAN_CURRENCY_TARGETS') === false) {
	putEnv('CURRENCY_CONVERSION_INTERNALPLAN_CURRENCY_TARGETS=EUR;USD');
}

#AMAZON

#AWS_ACCESS_KEY_ID

if(getEnv('AWS_ACCESS_KEY_ID') === false) {
	putEnv('AWS_ACCESS_KEY_ID=');
}

#AWS_SECRET_ACCESS_KEY

if(getEnv('AWS_SECRET_ACCESS_KEY') === false) {
	putEnv('AWS_SECRET_ACCESS_KEY=');
}

#AWS_ENV ( 'staging' / 'production' )

if(getEnv('AWS_ENV') === false) {
	putEnv('AWS_ENV=staging');
}

#AWS_BUCKET_BILLINGS

if(getEnv('AWS_BUCKET_BILLINGS_EXPORTS') === false) {
	putEnv('AWS_BUCKET_BILLINGS_EXPORTS=afrostream-exports-billings');
}

#AWS_REGION

if(getEnv('AWS_REGION') === false) {
	putEnv('AWS_REGION=eu-central-1');
}

#AWS_VERSION

if(getEnv('AWS_VERSION') === false) {
	putEnv('AWS_VERSION=latest');
}

#PARTNER_ORDERS
if(getEnv('PARTNER_ORDERS_LOGISTA_FILE_SIZE_LIMIT') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FILE_SIZE_LIMIT=0');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_ID=055');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_OPERATOR_PREFIX=AFST');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_USER') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_USER=logista-staging');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_PWD=6rQOM9PLts');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_HOST=ftp.afrostream.net');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_PORT') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_PORT=21');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_OUT') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_OUT=TOLOG');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_IN') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_FTP_FOLDER_IN=FLOG');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_PUBLIC_KEY_FILE') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_PUBLIC_KEY_FILE='.dirname(__FILE__).'/../libs/partners/logista/pgp-public-key/logista-pgp-public-key.txt');
}

if(getEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME') === false) {
	putEnv('PARTNER_ORDERS_LOGISTA_REPORT_FILE_BASENAME=SAF_STRT');
}

#Known user-agents

if(getEnv('AFROSTREAM_ANDROID_APP_CLIENT_IDS') === false) {
	putEnv('AFROSTREAM_ANDROID_APP_CLIENT_IDS=85f700d9-4a80-4913-8223-e0d49fef3a05;');
}

if(getEnv('AFROSTREAM_IOS_APP_CLIENT_IDS') === false) {
	putEnv('AFROSTREAM_IOS_APP_CLIENT_IDS=989796ec-5d63-4ef2-89b0-7d3923d6484f');
}

#Google

#Given by google itself

if(getEnv('GOOGLE_APPLICATION_CREDENTIALS') === false) {
	putenv('GOOGLE_APPLICATION_CREDENTIALS='.__DIR__.'/../libs/providers/google/credentials/afrostream-billing-project-e6f27e14d70a.json');
}

#

if(getEnv('GOOGLE_PACKAGENAME') === false) {
	putenv('GOOGLE_PACKAGENAME=tv.afrostream.app');
}

#Bonus

if(getEnv('BONUS_ENABLED') === false) {
	putEnv('BONUS_ENABLED=0');
}

if(getEnv('BONUS_CLIENT_IDS') === false) {
	putEnv('BONUS_CLIENT_IDS=85f700d9-4a80-4913-8223-e0d49fef3a05;');
}

if(getEnv('BONUS_INTERNAL_COUPON_CAMPAIGN_BILLING_UUID') === false) {
	putEnv('BONUS_INTERNAL_COUPON_CAMPAIGN_BILLING_UUID=e40028fa-ab18-4ec8-a573-b396c3165758');
}

if(getEnv('BONUS_INTERNAL_PLAN_BILLING_UUID') === false) {
	putEnv('BONUS_INTERNAL_PLAN_BILLING_UUID=bonus');
}

#logger, #db_conn, ...

class config {

	public static $timezone = "Europe/Paris";
	
	private static $logger;
	
	public static function init() {
	}
	
	public static function getLogger() {
		if(self::$logger == NULL) {
			self::$logger = new Logger('afrostream-billings');
			self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
		}
		return(self::$logger);
	}
	
	private static $db_conns = array();
	
	public static function getDbConn($connection_string_options = NULL, $read_only = false) {
		$connection_string = NULL;
		if(getEnv('DATABASE_URL') === false) {
			$connection_string = 'host='.getEnv('DB_HOST').' port='.getEnv('DB_PORT').' dbname='.getEnv('DB_NAME').' user='.getEnv('DB_USER').' password='.getEnv('DB_PASSWORD');
		} else {
			$connection_string = getEnv('DATABASE_URL');
		}
		if(isset($connection_string_options)) {
			$connection_string.= ' '.$connection_string_options;
		}
		$db_conn = NULL;
		$key = $connection_string.'-'.$read_only;
		if(key_exists($key, self::$db_conns)) {
			$db_conn = self::$db_conns[$key];
		} else {
			/* NC - keep in mind - 
			 * an old connection can be kept by pg_connect.
			 * By forcing PGSQL_CONNECT_FORCE_NEW will create one connection with read-only mode that will not be returned for connections with read-write mode 
			 */
			if($read_only == true) {
				$db_conn = pg_connect($connection_string, PGSQL_CONNECT_FORCE_NEW)
				or die('connection to database impossible : '.pg_last_error());
				pg_query($db_conn, "SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY");
			} else {
				$db_conn = pg_connect($connection_string)
				or die('connection to database impossible : '.pg_last_error());			
			}
			self::$db_conns[$key] = $db_conn;
		}
		return($db_conn);
	}
	
	public static function getReadOnlyDbConn() {
		$db_conn = self::getDbConn(NULL, true);
		return($db_conn);
	}
	
}

config::init();

class BillingStatsd {
	
	private static $statsd;
	
	private static function getStatsd() {
		if(self::$statsd == NULL) {
			$conn = new \Domnikl\Statsd\Connection\UdpSocket(getEnv('STATSD_HOST'), getEnv('STATSD_PORT'));
			self::$statsd = new \Domnikl\Statsd\Client($conn, getEnv('STATSD_NAMESPACE'));
		}
		return(self::$statsd);
	}
	
	public static function inc($key, $sampleRate = 1) {
		if(getEnv('STATSD_ACTIVATED') == 1) {
			self::getStatsd()->increment(getEnv('STATSD_KEY_PREFIX').$key, $sampleRate);
		}
	}
	
	public static function timing($key, $value, $sampleRate = 1) {
		if(getEnv('STATSD_ACTIVATED') == 1) {
			self::getStatsd()->timing(getEnv('STATSD_KEY_PREFIX').$key, $value, $sampleRate);
		}
	}
	
	public static function gauge($key, $value) {
		if(getEnv('STATSD_ACTIVATED') == 1) {
			self::getStatsd()->gauge(getEnv('STATSD_KEY_PREFIX').$key, $value);
		}
	}
	
	public static function startTiming($key) {
		if(getEnv('STATSD_ACTIVATED') == 1) {
			self::getStatsd()->startTiming(getEnv('STATSD_KEY_PREFIX').$key);
		}
	}
	
	public static function endTiming($key, $sampleRate = 1) {
		if(getEnv('STATSD_ACTIVATED') == 1) {
			self::getStatsd()->endTiming(getEnv('STATSD_KEY_PREFIX').$key, $sampleRate);
		}		
	}
	
}

?>