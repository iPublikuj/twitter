<?php
/**
 * Test: IPub\Twitter\Client
 * @testCase
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Twitter!
 * @subpackage	Tests
 * @since		5.0
 *
 * @date		05.03.15
 */

namespace IPubTests\Twitter;

use Nette;

use Tester;
use Tester\Assert;

use IPub;
use IPub\Twitter;

require_once __DIR__ . '/TestCase.php';

class ClientTest extends TestCase
{
	public function testUnauthorized()
	{
		$client = $this->buildClient();

		Assert::same(0, $client->getUser());
	}

	public function testAuthorized_savedInSession()
	{
		$client = $this->buildClient();

		$session = $client->getSession();
		$session->access_token = 'abcedf';
		$session->access_token_secret = 'ghijklmn';
		$session->user_id = 123321;

		Assert::same(123321, $client->getUser());
	}

	public function testAuthorized_readUserIdFromAccessToken()
	{
		$client = $this->buildClient();

		$client->setAccessToken([
			'access_token'          => 'abcedf',
			'access_token_secret'   => 'ghijklmn',
		]);

		$this->httpClient->fakeResponse('{"id":38895958,"id_str":"38895958","screen_name":"john.doe","name":"John Doe"}', 200, ['Content-Type' => 'application/json; charset=utf-8']);

		Assert::same(38895958, $client->getUser());
		Assert::count(1, $this->httpClient->requests);

		$secondRequest = $this->httpClient->requests[0];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.twitter.com/1.1/account/verify_credentials.json', $secondRequest->getUrl()->getHostUrl() . $secondRequest->getUrl()->getPath());
		Assert::same(['Authorization' => $this->generateAuthenticationHeader($secondRequest->getParameters()), 'Accept' => 'application/json'], $secondRequest->getHeaders());
	}

	public function testAuthorized_authorizeFromVerifierAndToken()
	{
		$client = $this->buildClient(array('oauth_verifier' => 'abcedf', 'oauth_token' => 'ghijklmn'));

		$this->httpClient->fakeResponse('oauth_token=72157626318069415-087bfc7b5816092c&oauth_token_secret=a202d1f853ec69de&user_id=38895958&screen_name=john.doe', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
		$this->httpClient->fakeResponse('{"id":38895958,"id_str":"38895958","screen_name":"john.doe","name":"John Doe"}', 200, ['Content-Type' => 'application/json; charset=utf-8']);

		Assert::same(38895958, $client->getUser());
		Assert::count(2, $this->httpClient->requests);

		$firstRequest = $this->httpClient->requests[0];

		Assert::same('POST', $firstRequest->getMethod());
		Assert::match('https://api.twitter.com/oauth/access_token', $firstRequest->getUrl()->getHostUrl() . $firstRequest->getUrl()->getPath());
		Assert::same(['Authorization' => $this->generateAuthenticationHeader($firstRequest->getParameters()), 'Accept' => 'application/json'], $firstRequest->getHeaders());

		$secondRequest = $this->httpClient->requests[1];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.twitter.com/1.1/account/verify_credentials.json', $secondRequest->getUrl()->getHostUrl() . $secondRequest->getUrl()->getPath());
		Assert::same(['Authorization' => $this->generateAuthenticationHeader($secondRequest->getParameters()), 'Accept' => 'application/json'], $secondRequest->getHeaders());
	}

	/**
	 * @param array $parameters
	 *
	 * @return string
	 */
	private function generateAuthenticationHeader($parameters)
	{
		ksort($parameters, SORT_STRING);
		$authHeader = NULL;

		foreach ($parameters as $key => $value) {
			if (strpos($key, 'oauth_') !== FALSE) {
				$authHeader .= ' ' . $key . '="' . $value . '",';
			}
		}

		return 'OAuth ' . trim(rtrim($authHeader, ','));
	}
}

\run(new ClientTest());
