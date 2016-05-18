Feature: Gestion des utilisateurs

  En tant que client de l'api je souhaite pour créer , mettre a jour, requeter un utilisateur

  Background: Je suis connecté
    Given I am connected as "admin"

  Scenario: J'accède à un utilisateur par son billing uuid
    Given I am on "/billings/api/users/11111111-1111-1111-1111-111111111111"
    Then the response status code should be 200
    And the content-type should be json
    And the json response should be:
     """
      {"status":"done","statusMessage":"success","statusCode":0,"response":{"user":{"userBillingUuid":"11111111-1111-1111-1111-111111111111","userReferenceUuid":"ref-afrostream1","userProviderUuid":"22222222-2222-2222-2222-222222222222","provider":{"providerName":"recurly"},"userOpts":{"lastName":"coelho","firstName":"firstname test","email":"test@domain.tld"}}}}
     """

  Scenario: Un utilisateur inexistant renvoie une 404
    Given I am on "/billings/api/users/11111111-1111-1111-1111-111111111122"
    Then the response status code should be 404
    And the content-type should be json

  Scenario: Un utilisateur supprimé renvoie une 404
    Given I am on "/billings/api/users/33333333-3333-3333-3333-333333333333"
    Then the response status code should be 404
    And the content-type should be json

  Scenario: J'accède à un utilisateur par le provider et sa référence afrostream
    Given I am on "/billings/api/users/?providerName=celery&userReferenceUuid=ref-afrostream3"
    Then the response status code should be 200
    And the content-type should be json
    And the json response should be:
      """
      {"status":"done","statusMessage":"success","statusCode":0,"response":{"user":{"userBillingUuid":"55555555-5555-5555-5555-555555555555","userReferenceUuid":"ref-afrostream3","userProviderUuid":"66666666-6666-6666-6666-666666666666","provider":{"providerName":"celery"},"userOpts":[]}}}
      """

  Scenario: Accès a un utilisateur avec un providerName inexistant renvoie un message d'erreur
    Given I am on "/billings/api/users/?providerName=unknowprovidername&userReferenceUuid=ref-afrostream3"
    Then the response status code should be 200
    And the content-type should be json
    And the json response should be:
      """
      {"status":"error","statusMessage":"unknown provider named : unknowprovidername","statusCode":0,"statusType":"internal","errors":[{"error":{"errorMessage":"unknown provider named : unknowprovidername","errorType":"internal","errorCode":0}}]}
      """

  Scenario: Accès a un utilisateur avec une reference inexistante renvoie une 404
    Given I am on "/billings/api/users/?providerName=celery&userReferenceUuid=bad-reference"
    Then the response status code should be 404
    And the content-type should be json

  Scenario: Création d'un utilisateur afr (les autres pas possible)
    Given I have the payload:
      """
      {
    	"providerName" : "afr",
    	"userReferenceUuid" : "new-afrostreamUUID",
    	"userOpts" : {
        	"email" : "new-email@domain.tld",
        	"firstName" : "new firstname",
        	"lastName" : "new lastname"
    	}
      }
      """
    When I request "POST /billings/api/users/"
    Then the response status code should be 200
    And the content-type should be json
    And the response should contains:
      """
      ,"provider":{"providerName":"afr"},"userOpts":{"lastName":"new lastname","firstName":"new firstname","email":"new-email@domain.tld"}}}}
      """