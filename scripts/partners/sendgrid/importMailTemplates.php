<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/db/dbGlobal.php';

/*
 * Tool
 */

print_r("starting tool to import sendgrid mail templates...\n");

foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else
        $_GET[$e[0]]=0;
}

print_r("processing...\n");



$sendgrid = new \SendGrid(getEnv('SENDGRID_API_KEY'));

$response = $sendgrid->client->templates()->get();

$body_as_array = json_decode($response->body(), true); 

$templates = $body_as_array['templates'];

print_r("found ".count($templates)." templates to process...\n");

foreach ($templates as $template) {
	print_r("template templatePartnerUuid=".$template['id'].", templateName=".$template['name']."...\n");
	$billingMailTemplate = BillingMailTemplateDAO::getBillingMailTemplateByTemplatePartnerUuid($template['id']);
	if($billingMailTemplate == NULL) {
		print_r("template templatePartnerUuid=".$template['id'].", templateName=".$template['name']." creating...\n");
		$billingMailTemplate = new BillingMailTemplate();
		$billingMailTemplate->setTemplateBillingUuid(guid());
		$billingMailTemplate->setTemplatePartnerUuid($template['id']);
		$billingMailTemplate->setTemplateName($template['name']);
		$billingMailTemplate = BillingMailTemplateDAO::addBillingMailTemplate($billingMailTemplate);
		print_r("template templatePartnerUuid=".$template['id'].", templateName=".$template['name']." created, ID=".$billingMailTemplate->getId()."...\n");
	} else {
		print_r("template templatePartnerUuid=".$template['id'].", templateName=".$template['name']." already exists\n");
	}
}

print_r("processing done\n");

?>