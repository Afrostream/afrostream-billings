<?php
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");

	require_once("soap-wsse.php");
	require_once("WSSoapClient.class.php");
	require_once("ByTelBAchat.class.php");
	require_once("db.php");

	$postdata = file_get_contents("php://input");
    $request = json_decode($postdata);

	$bachat = new ByTelBAchat();

	$res = $bachat->requestEDBCancel($request->requestId, $request->merchantId, $request->serviceId, $request->subscriptionId);

	if ($res->resultMessage == "SUCCESS") {
		$r = "INSERT INTO transactions (type, chargeTransactionId, subscriptionId) VALUES ('CANCEL', '$res->chargeTransactionId', '$res->subscriptionId');	";
		$result = db_query($r);
	}

	$outp = '{';
	$outp .= '"result": "' . $res->result . '",';
	$outp .= '"resultMessage": "' . $res->resultMessage . '"';
	$outp .= '}';

	echo($outp);

?>
