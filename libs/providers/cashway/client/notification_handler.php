<?php
/**
 * CashWay API client notification handler.
 * This is provided as a sample of how to receive notifications from CashWay.
 * Please adapt this to your platform.
 *
 * See https://help.cashway.fr/shops/#recevoir-des-notifications
 *
 * PHP version 5.3
 *
 * @author    CashWay <contact@cashway.fr>
 * @copyright 2015 CashWay (http://www.cashway.fr/)
 * @license   Apache License 2.0
 * @link      https://github.com/cshw/api-helpers
*/

require 'cashway_lib.php';
require 'compat.php';

define('SHARED_SECRET', 'secret_shared_between_you_and_cashway');
define('SEND_CONVERSION_EMAIL', true);

function response($status, $message)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array(
        'status'  => ($status < 400) ? 'ok' : 'error',
        'message' => $message
        )
    );
    die;
}

// This takes care of authenticating the message against SHARED_SECRET
$res = \CashWay\API::receiveNotification(
    file_get_contents('php://input'),
    getallheaders(),
    SHARED_SECRET
);

if ($res[0] === false) {
    response(400, $res[1]);
} else {
    $event = $res[1];
    $data  = $res[2];

    switch ($event) {
        case 'conversion_expired':
            // Your platform notified us a payment failed a few minutes ago
            // We're notifying you to check if it still went through.
            // If not (it's yours to know/decide), you may send a conversion
            // email proposing CashWay as an alternative payment solution.
            if (SEND_CONVERSION_EMAIL) {
                // TODO: find order matching $data->order_id
                // (if not, response(404, 'Could not find such an order.');
                // TODO: find customer (if not, response(404, 'Do not have customer info.');
                // TODO: send conversion email
                response(201, 'Ok, conversion email sent.');
            } else {
                response(202, 'Ok but not sending email per shop config.');
            }
            break;

        case 'transaction_paid':
            // This order has been paid through a CashWay distributor.
            // TODO: find order matching $data->order_id, set it to paid
            response(201, 'Ok, set to paid.');
            break;

        case 'transaction_expired':
            // This order payment expired.
            // TODO: find order matching $data->order_id, set it to cancelled
            response(201, 'Ok, set to cancelled.');
            break;

        case 'status_check':
            // Do a full check of orders against CashWay orders list.
            // See https://help.cashway.fr/shops/#rcuprer-l39tat-de-toutes-les-commandes
            $api = new \CashWay\API($configuration);
            $res = $api->checkTransactionsForOrders();
            // TODO: loop through $res to find orders whose status have changed
            response(201, $log_message);
            break;

        default:
            response(400, 'Do not know how to handle this event.');
            break;
    }
}
