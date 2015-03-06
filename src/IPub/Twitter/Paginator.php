<?php
/**
 * Paginator.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Twitter!
 * @subpackage	common
 * @since		5.0
 *
 * @date		20.02.15
 */

namespace IPub\Twitter;

use Nette;

use IPub;

/**
 * Response paginator
 *
 * @package		iPublikuj:Twitter!
 * @subpackage	common
 *
 * @author Adam Kadlec <adam.kadlec@fastybird.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class Paginator extends Nette\Object implements \Iterator
{
	const PER_PAGE_MAX = 100;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var IPub\OAuth\HttpClient
	 */
	private $httpClient;

	/**
	 * @var int
	 */
	private $firstPage = 1;

	/**
	 * @var int
	 */
	private $perPage;

	/**
	 * @var int|NULL
	 */
	private $maxResults;

	/**
	 * @var array
	 */
	private $resources = [];

	/**
	 * @var IPub\OAuth\Api\Response[]
	 */
	private $responses = [];

	/**
	 * @var int
	 */
	private $itemCursor;

	/**
	 * @var int
	 */
	private $pageCursor;

	/**
	 * @param ApiCall $client
	 * @param IPub\OAuth\Api\Response $response
	 */
	public function __construct(ApiCall $client, IPub\OAuth\Api\Response $response)
	{
		$this->client = $client;

		$this->httpClient = $client->getHttpClient();
		$resource = $response->toArray();

		$params = $response->request->getParameters();
		$this->perPage = isset($params['count']) ? (int) $params['count'] : count($resource);

		$this->responses[$this->firstPage] = $response;
		$this->resources[$this->firstPage] = $resource;
	}

	/**
	 * If you setup maximum number of results, the pagination will stop after fetching the desired number.
	 * If you have count=50 and wan't to fetch 200 results, it will make 4 requests in total.
	 *
	 * @param int $maxResults
	 *
	 * @return $this
	 */
	public function limitResults($maxResults)
	{
		$this->maxResults = (int)$maxResults;

		return $this;
	}

	public function rewind()
	{
		$this->itemCursor = 0;
		$this->pageCursor = $this->firstPage;
	}

	public function valid()
	{
		return isset($this->resources[$this->pageCursor][$this->itemCursor])
			&& ! $this->loadedMaxResults();
	}

	/**
	 * @return bool
	 */
	public function loadedMaxResults()
	{
		if ($this->maxResults === NULL) {
			return FALSE;
		}

		return $this->maxResults <= ($this->itemCursor + ($this->pageCursor - $this->firstPage) * $this->perPage);
	}

	public function current()
	{
		if (!$this->valid()) {
			return NULL;
		}

		return Nette\Utils\ArrayHash::from($this->resources[$this->pageCursor][$this->itemCursor]);
	}

	public function next()
	{
		$this->itemCursor++;

		// if cursor points at result of next page, try to load it
		if ($this->itemCursor < $this->perPage || $this->itemCursor % $this->perPage !== 0) {
			return;
		}

		if (isset($this->resources[$this->pageCursor + 1])) { // already loaded
			$this->itemCursor = 0;
			$this->pageCursor++;

			return;
		}

		if ($this->loadedMaxResults()) {
			return;
		}

		try {
			$prevRequest = $this->responses[$this->pageCursor]->getRequest();

			// Get all request parameters
			$params = $this->responses[$this->pageCursor]->request->getParameters();
			// Get last record
			$current = Nette\Utils\ArrayHash::from($this->resources[$this->pageCursor][$this->itemCursor-1]);
			// And set maximum ID
			$params['max_id'] = $current->id;
			// Get requested path
			$path = $prevRequest->getUrl()->getPath();
			$path = ltrim($path, '/1.1/');

			$response = $this->httpClient->makeRequest(
				$prevRequest->copyWithUrl($this->client->getConfig()->createUrl('api', $path, $params)),
				'HMAC-SHA1'
			);

			$this->itemCursor = 0;
			$this->pageCursor++;
			$this->responses[$this->pageCursor] = $response;
			$this->resources[$this->pageCursor] = $response->toArray();

		} catch (\Exception $e) {
			$this->itemCursor--; // revert back so the user can continue if needed
		}
	}

	public function key()
	{
		return $this->itemCursor + ($this->pageCursor - 1) * $this->perPage;
	}
}