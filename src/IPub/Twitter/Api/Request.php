<?php
/**
 * Request.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Twitter!
 * @subpackage	Api
 * @since		5.0
 *
 * @date		05.03.15
 */

namespace IPub\Twitter\Api;

use IPub;
use IPub\OAuth;

/**
 * @package		iPublikuj:Twitter!
 * @subpackage	Api
 *
 * @author Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Request extends OAuth\Api\Request
{
	/**
	 * {@inheritdoc}
	 */
	public function getSignableParameters()
	{
		// Grab all parameters
		$params = $this->getParameters();

		// Remove oauth_signature if present
		// Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
		if (isset($params['oauth_signature'])) {
			unset($params['oauth_signature']);
		}

		return OAuth\Utils\Url::buildHttpQuery($params);
	}
}