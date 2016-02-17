<?php

require_once __DIR__ .'/../libs/providers/bachat/client/soap-wsse.php';
require_once __DIR__ .'/../libs/providers/bachat/client/WSSoapClient.class.php';
require_once __DIR__ .'/../libs/providers/bachat/client/ByTelBachat.class.php';

date_default_timezone_set("Europe/Paris");

$bachat = new ByTelBAchat();

$res = $bachat->requestEDBBilling('1','1', 1);

//if ($res->resultMessage == "SUCCESS") {
	//OK
//	var_export("OK=".$res);
//} else {
	//KO
	var_export($res);
//}

//require_once __DIR__ .'/../libs/providers/idipper/client/IdipperClient.php';

//$c = new IdipperClient();

//$r = new UtilisateurRequest();

//$r->setExternalUserID('TEST_LAURENT1');

//$utilisateurResponse = $c->getUtilisateur($r);

//echo("getIDUtilisateur=".$utilisateurResponse->getIDUtilisateur());

//use SebastianBergmann\Money\Money;
//use SebastianBergmann\Money\Currency;
//require_once __DIR__ . '/../libs/subscriptions/SubscriptionsHandler.php';

//require_once __DIR__ . '/PHP/ByTelBAchat.class.php';
//require_once __DIR__ . '/../libs/utils/utils.php';

//echo("Hello Worlddd\n");

//$today = new DateTime(NULL, new DateTimeZone("Europe/Paris"));
//$today->setTime(0, 0, 0);

//echo ($today->format(DateTime::ISO8601));;

function testMe() {
	print_r("Hello World\n");
	//$byTelBAchat = new ByTelBAchat();
	//$byTelBAchat->requestEDBBilling(guid(), "0", 10);
	//$amountInMoney = new Money((integer) 1000, 'EUR');
	//setlocale(LC_MONETARY, 'fr_FR');
	//setlocale(LC_NUMERIC, 'fr_FR');
	//print_r("money=".money_format('%!.2n', 6.99));//$amountInMoney->getConvertedAmount());
	//$s = new SubscriptionsHandler();
	//$db_subscription = BillingsSubscriptionDAO::getBillingsSubscriptionById(149);
	//$s->doSendSubscriptionEvent(NULL, $db_subscription);
	/*$emailTo = 'nelson.coelho@afrostream.tv'; 
	$userName = explode('@', $emailTo)[0];
	$sendgrid = new SendGrid('SG.lliM3Gp5QyuqgmQ36iLwLw.u3mP5Ne2PhP5Kohs8MO8rHhlA0Q3GLyZil45b9qgl5E');
	$email = new SendGrid\Email();
	$email
	->addTo($emailTo)
	->setFrom('nelson.coelho@afrostream.tv')
	->setSubject(' ')
	->setText(' ')
	->setHtml(' ')
	->setTemplateId('dde84299-e6fe-47a0-909b-1ee11417efe1')
	->addSubstitution("%userName%", array($userName))
	->addSubstitution("%planCode%", array("Formule Cadeau"));
	;
	// Or catch the error
	
	try {
		$sendgrid->send($email);
	} catch(\SendGrid\Exception $e) {
		echo $e->getCode();
		foreach($e->getErrors() as $er) {
			echo $er;
		}
	}*/
}

?>