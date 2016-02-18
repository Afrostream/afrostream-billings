<?php

  class ByTelBAchat {
    
  	static private $wsdl_billing = "EDBBilling.wsdl";
    static private $wsdl_cancel = "EDBCancel.wsdl";
    static private $wsdl_refund = "EDBRefund.wsdl";

    public static function requestEDBBilling($requestId, $gw_idsession, $random) {
      $client = new WSSoapClient(dirname(__FILE__)."/" . self::$wsdl_billing, 
      		array(	'trace' => 1, 
      				'soap_version' => SOAP_1_1,
      				'connection_timeout' => 3600
      				/* PHP BUG ? */,
      				'exceptions' => 1,
      				'stream_context' => stream_context_create(
      						array(
      								'ssl' => array(
      										'verify_peer'       => false,
      										'verify_peer_name'  => false,
      										)
      							)
      				)
      		));

      $params = array("requestId" => $requestId, "gw_idsession" => $gw_idsession, "random" => (int)$random);

      try {
        $res = $client->__soapCall("EDBBilling", array("parameters" => $params));
		error_log("********************   SUCCES du requestEDBBilling  ****************************");
        return $res;
      }
      catch (SoapFault $fault)  {
		error_log("********************   ECHEC du requestEDBBilling  ****************************");
		error_log($fault->faultstring);
        return array("last_request" => $client->__getLastRequest(), "last_response" => $client->__getLastResponse(), "last_request_headers" => $client->__getLastRequestHeaders());
      }
    }

    public static function requestEDBCancel($requestId, $merchantId, $serviceId, $subscriptionId) {
      $client = new WSSoapClient(dirname(__FILE__)."/" . self::$wsdl_cancel, array('trace' => 1, 'soap_version' => SOAP_1_1, 'connection_timeout' => 3600));

      $params = array("requestId" => $requestId, "merchantId" => $merchantId, "serviceId" => $serviceId, "subscriptionId" => $subscriptionId);

      try {
        $res = $client->__soapCall("EDBCancel", array("parameters" => $params));
        return $res;
      }
      catch (SoapFault $fault)  {
        return array("last_request" => $client->__getLastRequest(), "last_response" => $client->__getLastResponse(), "last_request_headers" => $client->__getLastRequestHeaders());
      }      
    }

     public static function requestEDBRefund($requestId, $gw_idsession, $random) {
      $client = new WSSoapClient(dirname(__FILE__)."/" . self::$wsdl_refund, array('trace' => 1, 'soap_version' => SOAP_1_1, 'connection_timeout' => 3600));

      $params = array("requestId" => $requestId, "gw_idsession" => $gw_idsession, "random" => (int)$random);

      try {
        $res = $client->__soapCall("EDBRefund", array("parameters" => $params));
        return $res;
      }
      catch (SoapFault $fault)  {
        return array("last_request" => $client->__getLastRequest(), "last_response" => $client->__getLastResponse(), "last_request_headers" => $client->__getLastRequestHeaders());
      }      
    }   

  }

?>
