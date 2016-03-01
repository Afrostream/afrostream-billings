<?php
	
require_once __DIR__ . '/../config/config.php';

ScriptsConfig::getLogger()->addInfo("empty file upload...");

$current_par_can_file_path = "filled.csv";
$current_par_can_file_res = NULL;
if(($current_par_can_file_res = fopen($current_par_can_file_path, "w+")) === false) {
	throw new BillingsException("empty file cannot be open");
}
$url = getEnv('BOUYGUES_BILLING_SYSTEM_URL')."/"."empty.csv";

$curl_options = array(
	CURLOPT_URL => $url,
	CURLOPT_PUT => true,
	CURLOPT_INFILE => $current_par_can_file_res,
	CURLOPT_INFILESIZE => filesize($current_par_can_file_path),
	CURLOPT_HTTPHEADER => array(
		'Content-Type: text/csv'
		),
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER  => false
);
if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
	&&
	null !== (getEnv('BOUYGUES_PROXY_PORT'))
) {
	$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
	$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
}
if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
	&&
	null !== (getEnv('BOUYGUES_PROXY_PWD'))
) {
	$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
}
if(	null !== (getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_USER'))
	&&
	null !== (getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_PWD'))
) {
	$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
	$curl_options[CURLOPT_USERPWD] = getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_USER').":".getEnv('BOUYGUES_BILLING_SYSTEM_HTTP_AUTH_PWD');
}
$curl_options[CURLOPT_VERBOSE] = 1;
$CURL = curl_init();
curl_setopt_array($CURL, $curl_options);
$content = curl_exec($CURL);
$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
curl_close($CURL);
fclose($current_par_can_file_res);
$current_par_can_file_res = NULL;
unlink($current_par_can_file_path);
$current_par_can_file_path = NULL;
if($httpCode == 200 || $httpCode == 201 || $httpCode == 204) {
	ScriptsConfig::getLogger()->addInfo("empty file uploaded successfully, the httpCode is : ".$httpCode);
} else {
	$msg = "empty file failed to be uploaded, the httpCode is : ".$httpCode;
	ScriptsConfig::getLogger()->addError($msg);
	throw new Exception($msg);
}

?>