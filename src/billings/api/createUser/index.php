<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/users/UsersHandler.php';

//TODO : REQUEST

$provider_name = 'gocardless';
$user_reference_uuid = '1234';
$user_opts_array = array (
"email" => "email@domain.com",
"first_name" => "first_name_value",
"last_name" => "last_name_value");

$user_opts = new UserOpts();
$user_opts->setOpts($user_opts_array);

try {

	$usersHandler = new UsersHandler();
	$user = $usersHandler->doCreateUser($provider_name, $user_reference_uuid, $user_opts);

} catch(BillingsException $e) {
	$msg = "an exception occurred while creating an user, error_type=".$e->getExceptionType().",error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
} catch(Exception $e) {
	$msg = "an unknown exception occurred while creating an user, error_code=".$e->getCode().", error_message=".$e->getMessage();
	config::getLogger()->addError($msg);
	//
	echo $msg;
	//
}
	
//TODO : RESPONSE

?>