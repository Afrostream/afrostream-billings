<?php

namespace Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Exception\ExpectationException;

class BaseContext implements SnippetAcceptingContext
{
    /**
     * Mink context
     *
     * @var MinkContext
     */
    protected $minkContext;

    protected $payload;

    /**
     * Initialisation du schema de la base et chargement des fixtures
     *
     * @BeforeFeature
     */
    public static function initBdd()
    {
        putEnv('ENVIRONMENT=test');
        $createDatabaseFile = realpath(__DIR__.'/../Bdd/create-database.sql');
        $createTablesFile = realpath(__DIR__.'/../Bdd/create_tables.sql');
        $fixtures = glob(__DIR__.'/../Bdd/Fixtures/*.sql');

        exec('psql -U postgres < '.$createDatabaseFile);
        exec('psql -U postgres afr-billings-local-test < '.$createTablesFile);

        foreach ($fixtures as $fixture) {
            exec('psql -U postgres afr-billings-local-test < '.realpath($fixture));
        }
    }

    /**
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();

        $this->minkContext = $environment->getContext('Behat\MinkExtension\Context\MinkContext');
    }

    /**
     * I am connected as context
     *
     * @param string $username username
     *
     * @Given I am connected as :username
     */
    public function iAmConnectedAs($username)
    {
        if ($this->minkContext->getSession()->getDriver() instanceof \Behat\Mink\Driver\BrowserKitDriver) {
            $this->minkContext->getSession()->setBasicAuth($username, 'pwd');
        } else {
            $url = $this->minkContext->getMinkParameter('base_url');
            $this->minkContext->setMinkParameter('base_url', str_replace('http://', 'http://'.$username .':pwd@', $url));
        }
    }

    /**
     * @param PyStringNode $string
     *
     * @Then /^the response should be:$/
     */
    public function theResponseShouldbe(PyStringNode $string)
    {
        $content =  $this->minkContext->getSession()->getPage()->getContent();

        $message = sprintf('The response is not equal to <<%s>>', $string);

        $this->assert(($string->getRaw() === $content), $message);
    }

    /**
     * @param PyStringNode $string
     *
     * @Then /^the json response should be:$/
     */
    public function theJsonResponseShouldbe(PyStringNode $string)
    {
        $content =  $this->minkContext->getSession()->getPage()->getContent();

        $jsonExpected = json_decode($string->getRaw(), true);
        $jsonResponse = json_decode($content, true);

        $message = sprintf('The response is not equal to <<%s>>', $string);

        $this->assert(($jsonExpected === $jsonResponse), $message);
    }

    /**
     * Check a specific header
     *
     * @param string $name
     * @param string $expected
     *
     * @Then /^the response header "([^"]*)" should be "([^"]*)"$/
     */
    public function theResponseHeaderShouldBe($name, $expected)
    {
        $header = $this->minkContext->getSession()->getResponseHeader($name);

        return $this->assert(($header === $expected), "$header not match $expected");
    }

    /**
     * Check than content type is json
     *
     * @Then /^the content-type should be json$/
     */
    public function theContentTypeShouldBeJson()
    {
        $this->theResponseHeaderShouldBe('Content-Type', "application/json");
    }

    /**
     * @param PyStringNode $string
     *
     * @Given /^I have the payload:$/
     */
    public function ihavePayload(PyStringNode $string)
    {
        $this->payload = $string->getRaw();
    }

    /**
     * Execute request
     *
     * @When /^I request "(GET|PUT|POST|PATCH|DELETE) ([^"]*)"$/
     */
    public function iRequest($method, $resource)
    {
        if (substr($resource, 0, 4) != 'http') {
            $url = $this->minkContext->getMinkParameter('base_url');

            $resource = $url.$resource;
        }

        $this->minkContext->getSession()->getDriver()->getClient()->request(
            $method,
            $resource,
            [],
            [],
            [],
            $this->payload
        );
    }

    /**
     * @param PyStringNode $string
     *
     * @Then /^the response should contains:$/
     */
    public function theResponseShouldContains(PyStringNode $string)
    {
        $content =  $this->minkContext->getSession()->getPage()->getContent();

        $message = sprintf('String <<%s>> not found in response', $string);

        $this->assert((false !== strpos($content, $string->getRaw())), $message);
    }

    /**
     * Check assertion
     *
     * @param boolean $bool
     * @param string $message
     *
     * @throws ExpectationException
     */
    protected function assert($bool, $message)
    {
        if ($bool) {
            return;
        }

        throw new ExpectationException($message, $this->minkContext->getSession());
    }
}