<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../libs/users/UsersHandler.php';

//TODO : REQUEST

$provider_name = 'recurly';
$user_reference_uuid = '1234';
$user_opts = array (
"email" => "emaildomain.com",
"first_name" => "first_name_value",
"last_name" => "last_name_value");

$usersHandler = new UsersHandler();
$user = $usersHandler->doCreateUser($provider_name, $user_reference_uuid, $user_opts);

//TODO : RESPONSE

?>