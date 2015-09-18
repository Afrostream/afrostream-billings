<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/config.php';

config::getLogger()->addInfo('Processing recurly webhook...');

$post_xml = file_get_contents('php://input');

$notification = new Recurly_PushNotification($post_xml);

config::getLogger()->addInfo('Processing notification type : '. $notification->type);

//each webhook is defined by a type
switch ($notification->type) {
	case "successful_payment_notification":
		/* process notification here */
		break;
	case "failed_payment_notification":
		/* process notification here */
		break;
		/* add more notifications to process */
	default :
		config::getLogger()->addInfo('notification type : '. $notification->type. ' is not yet ');
		break;
}

/*
$connection_string = 'host='.DBHOST.' port='.DBPORT.' dbname='.DBNAME.' user='.DBUSER.' password='.DBPASSWORD;

$db_conn = pg_connect($connection_string)
	or die('connexion to db impossible : '.pg_last_error());

$query = 'SELECT * FROM "Users"';
$result = pg_query($query) or die('Échec de la requête : ' . pg_last_error());

// Affichage des résultats en HTML
echo "<table>\n";
while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
	echo "\t<tr>\n";
	foreach ($line as $col_value) {
		echo "\t\t<td>$col_value</td>\n";
	}
	echo "\t</tr>\n";
}
echo "</table>\n";

// free result
pg_free_result($result);

// close db connexion
pg_close($db_conn);
*/

config::getLogger()->addInfo('Processing recurly webhook done successfully');

function logToFile($msg)
{
	// open file
	$fd = fopen("afrostream-billings.log", "a");
	// write string
	fwrite($fd, $msg . "\n");
	// close file
	fclose($fd);
}

?>