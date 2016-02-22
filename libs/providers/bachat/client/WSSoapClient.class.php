<?php

require_once __DIR__ . '/../../../../config/config.php';

class WSSoapClient extends \SoapClient
{

	function __doRequest($request, $location, $saction, $version, $one_way = 0) {
		
		$request = preg_replace_callback('/\<ns1:(random|merchantId)\>([0-9]{1,3})\<\/ns1:(random|merchantId)\>/', function($m){
			return '<ns1:'.$m[1].'>'.str_pad($m[2],4,"0",STR_PAD_LEFT).'</ns1:'.$m[1].'>';
		} , $request);
				
		$doc = new DOMDocument('1.0');
		$doc->loadXML($request);
		
		$objWSSE = new WSSESoap($doc);

		$objWSSE->addTimestamp(300);
		$token = $objWSSE->addBinaryToken(file_get_contents(dirname(__FILE__) . "/bt-billing.pem"), true);
		
		$objWSSE->nodesToSign = "wsu:Timestamp";
		
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
		$objKey->loadKey(dirname(__FILE__) . "/bt-billing.key", true, false);
		
		$unencrypted = $objWSSE->saveXML();
		
		$objWSSE->signSoapDoc($objKey);
		$objWSSE->attachTokentoSig($token);
		
		$request = $objWSSE->saveXML();
		
		/* 
		$request = preg_replace('/<ns1:(\w+)/', '<edb:$1', $request, 1);
		$request = preg_replace('/<ns1:(\w+)/', '<edb:$1', $request);
		$request = preg_replace('/<\/ns1:(\w+)/', '</edb:$1', $request);
		*/
		
		$this->__last_request = $request;
		
		config::getLogger()->addInfo("BACHAT request=".$request);

		return parent::__doRequest($request, $location, $saction, $version, $one_way);
	}
	
}

?>
