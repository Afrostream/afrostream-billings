<?php

function guid( $opt = false ) {       //  Set to true/false as your default way to do this.
	if(function_exists('com_create_guid')) {
    	if( $opt ) { return com_create_guid(); }
        	else { return trim( com_create_guid(), '{}' ); }
    } else {
      mt_srand( (double)microtime() * 10000 );    // optional for php 4.2.0 and up.
      $charid = strtoupper( md5(uniqid(rand(), true)) );
      $hyphen = chr( 45 );    // "-"
      $left_curly = $opt ? chr(123) : "";     //  "{"
      $right_curly = $opt ? chr(125) : "";    //  "}"
      $uuid = $left_curly
      . substr( $charid, 0, 8 ) . $hyphen
      . substr( $charid, 8, 4 ) . $hyphen
      . substr( $charid, 12, 4 ) . $hyphen
      . substr( $charid, 16, 4 ) . $hyphen
      . substr( $charid, 20, 12 )
      . $right_curly;
      return $uuid;
	}
}

function checkUserOptsArray(array $user_opts_as_array) {
	checkUserOptsKeys($user_opts_as_array);
	checkUserOptsValues($user_opts_as_array);
}

function checkUserOptsKeys(array $user_opts_as_array) {
	if(!array_key_exists('email', $user_opts_as_array)) {
		//exception
		$msg = "userOpts field 'email' is missing";
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	if(!array_key_exists('firstName', $user_opts_as_array)) {
		//exception
		$msg = "userOpts field 'firstName' is missing";
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
	if(!array_key_exists('lastName', $user_opts_as_array)) {
		//exception
		$msg = "userOpts field 'lastName' is missing";
		config::getLogger()->addError($msg);
		throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
	}
}

function checkUserOptsValues(array $user_opts_as_array) {
	if(array_key_exists('email', $user_opts_as_array)) {
		$email = $user_opts_as_array['email'];
		if(strlen(trim($email)) == 0) {
			//exception
			$msg = "'email' value is empty";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	if(array_key_exists('firstName', $user_opts_as_array)) {
		$firstName = $user_opts_as_array['firstName'];
		if(strlen(trim($firstName)) == 0) {
			//exception
			$msg = "'firstName' value is empty";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
	if(array_key_exists('lastName', $user_opts_as_array)) {
		$lastName = $user_opts_as_array['lastName'];
		if(strlen(trim($lastName)) == 0) {
			//exception
			$msg = "'lastName' value is empty";
			config::getLogger()->addError($msg);
			throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
		}
	}
}

//$case = all, create, get
function checkSubOptsArray(array $sub_opts_as_array, $providerName, $case = 'all') {
	checkSubOptsKeys($sub_opts_as_array, $providerName, $case);
	checkSubOptsValues($sub_opts_as_array, $providerName, $case);
}

//$case = all, create, get
function checkSubOptsKeys(array $sub_opts_as_array, $providerName, $case = 'all') {
	switch($providerName) {
		case 'bachat' :
			if(!array_key_exists('otpCode', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'otpCode' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('idSession', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'idSession' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('requestId', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'requestId' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoEnabled', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoEnabled' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoItemBasePrice', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoItemBasePrice' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoItemTaxAmount', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoItemTaxAmount' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoItemTotal', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoItemTotal' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoCurrency', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoCurrency' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoPeriod', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoPeriod' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			if(!array_key_exists('promoDuration', $sub_opts_as_array)) {
				//exception
				$msg = "subOpts field 'promoDuration' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
			}
			break;
		case 'gocardless':
			if($case == 'create') {
				if(!array_key_exists('customerBankAccountToken', $sub_opts_as_array)) {
					//exception
					$msg = "subOpts field 'customerBankAccountToken' is missing";
					config::getLogger()->addError($msg);
					throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
				}
			}
			break;
		default :
			//nothing
			break;
	}
}

function checkSubOptsValues(array $sub_opts_as_array, $providerName, $case = 'all') {
	switch($providerName) {
		case 'bachat' :
			break;
		default :
			//nothing
			break;
	}
}

?>