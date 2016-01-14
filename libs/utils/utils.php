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

function checkUserOptsArray($user_opts_as_array) {
	checkUserOptsKeys($user_opts_as_array);
	checkUserOptsValues($user_opts_as_array);
}

function checkUserOptsKeys($user_opts_as_array) {
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

function checkUserOptsValues($user_opts_as_array) {
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

?>