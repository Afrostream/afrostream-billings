<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/BillingsController.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

use \Slim\Http\Request;
use \Slim\Http\Response;

class ProviderStripeController extends BillingsController {
  public function createEphemeralKey(Request $request, Response $response, array $args) {
		try {
      // FIXME: h4rdc0d3d :)
      $providerName = 'stripe';
      $platformId = 1;

      // loading platform & provider
      $platform = BillingPlatformDAO::getPlatformById($platformId);
      $provider = ProviderDAO::getProviderByName($providerName, $platformId);
      // parsing parameters
      $data = json_decode($request->getBody(), true);
      if (!isset($data['userReferenceUuid'])) {
        $msg = "field 'userReferenceUuid' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
      }
      $userReferenceUuid = $data['userReferenceUuid'];
      $data = $request->getQueryParams();
      if (!isset($data['apiVersion'])) {
        $msg = "query string 'apiVersion' is missing";
				config::getLogger()->addError($msg);
				throw new BillingsException(new ExceptionType(ExceptionType::internal), $msg);
      }
      $apiVersion = $data['apiVersion'];
      // searching user
      $getUserRequest = new GetUserRequest();
      $getUserRequest->setOrigin('api');
      $getUserRequest->setPlatform($platform);
      $getUserRequest->setProviderName($providerName);
      $getUserRequest->setUserReferenceUuid($userReferenceUuid);
      $usersHandler = new UsersHandler();
      $user = $usersHandler->doGetUser($getUserRequest);
      if($user == NULL) {
          return($this->returnNotFoundAsJson($response));
      }
      // requesting ephemeral key
      $providerUsersHandler = ProviderHandlersBuilder::getProviderUsersHandlerInstance($provider);
      $key = $providerUsersHandler->createEphemeralKey($user->getUserProviderUuid(), $apiVersion);
      return($this->returnObjectAsJson($response, 'key', $key));
		} catch(BillingsException $e) {
			$msg = "an exception occurred while getting a stripe ephemeral key, error_type=".$e->getExceptionType().", error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnBillingsExceptionAsJson($response, $e));
			//
		} catch(Exception $e) {
			$msg = "an unknown exception occurred while getting a stripe ephemeral key, error_code=".$e->getCode().", error_message=".$e->getMessage();
			config::getLogger()->addError($msg);
			//
			return($this->returnExceptionAsJson($response, $e));
			//
		}
	}
}

?>
