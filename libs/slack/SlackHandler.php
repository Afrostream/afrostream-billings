<?php

class SlackHandler {
	
	public function __construct() {
	}
	
	public function sendMessage($channel, $message) {
		if(getEnv('SLACK_ACTIVATED') == 1) {
			$url = "https://afrostream-slackbot.herokuapp.com/api/channels/".$channel."/messages";
			$json_as_array = array();
			$json_as_array['text'] = $message;
			$data_string = json_encode($json_as_array);
			$curl_options = array(
					CURLOPT_URL => $url,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => $data_string,
					CURLOPT_HTTPHEADER => array(
							'Content-Type: application/json',
							'Content-Length: ' . strlen($data_string)
					),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HEADER  => false
			);
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			$content = curl_exec($CURL);
			$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
			curl_close($CURL);
			if($httpCode != 200) {
				throw new Exception("SLACKBOT CALL, code=".$httpCode." is unexpected...");
			}
		}
	}
}

?>