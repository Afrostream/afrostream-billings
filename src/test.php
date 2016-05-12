<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../libs/providers/orange/client/OrangeTVClient.php';

$orangeAPIToken = 'B64PsuqE7qCWU6wfKT4XuIe/CZl1BtEBSsWnLlD9WhrpA1XwwGCt/9VO4Pg07DVeFXHZ1I7rObpj+MrlHJvFeemw/g+84heqa6+mjZy26oumgk=|MCO=OFR|sau=3|ted=1466864529|tcd=1461594129|aGcO22B1xm0DJsFjz26uK7BOXxo=';

$subscriptionID = 'SVOAFRA19A33F788FCE4';

$orangeTVClient = new OrangeTVClient($orangeAPIToken);

$orangeTVClient->getSubscriptions($subscriptionID);

?>