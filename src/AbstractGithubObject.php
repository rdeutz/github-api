<?php
/**
 * Part of the Joomla Framework Github Package
 *
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Github;

use Joomla\Http\Response;
use Joomla\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * GitHub API object class for the Joomla Framework.
 *
 * @since  1.0
 */
abstract class AbstractGithubObject
{
	/**
	 * @var    Registry  Options for the GitHub object.
	 * @since  1.0
	 */
	protected $options;

	/**
	 * @var    Http  The HTTP client object to use in sending HTTP requests.
	 * @since  1.0
	 */
	protected $client;

	/**
	 * @var    string  The package the object resides in
	 * @since  1.0
	 */
	protected $package = '';

	/**
	 * @var    array  Array of the returned headers
	 * @since  1.4
	 */
	protected $headers = array();

	/**
	 * @var    Registry  Pagination
	 * @since  1.4
	 */
	protected $pagination;

	/**
	 * Constructor.
	 *
	 * @param   Registry  $options  GitHub options object.
	 * @param   Http      $client   The HTTP client object.
	 *
	 * @since   1.0
	 */
	public function __construct(Registry $options = null, Http $client = null)
	{
		$this->options = isset($options) ? $options : new Registry;
		$this->client = isset($client) ? $client : new Http($this->options);

		$this->package = get_class($this);
		$this->package = substr($this->package, strrpos($this->package, '\\') + 1);

		$this->pagination = new Registry;

		$this->pagination->set('page.count', 1);
	}

	/**
	 * Method to build and return a full request URL for the request.  This method will
	 * add appropriate pagination details if necessary and also prepend the API url
	 * to have a complete URL for the request.
	 *
	 * @param   string   $path   URL to inflect
	 * @param   integer  $page   Page to request
	 * @param   integer  $limit  Number of results to return per page
	 *
	 * @return  string   The request URL.
	 *
	 * @since   1.0
	 */
	protected function fetchUrl($path, $page = 0, $limit = 0)
	{
		// Get a new Uri object fousing the api url and given path.
		$uri = new Uri($this->options->get('api.url') . $path);

		if ($this->options->get('gh.token', false))
		{
			// Use oAuth authentication - @todo set in request header ?
			$uri->setVar('access_token', $this->options->get('gh.token'));
		}
		else
		{
			// Use basic authentication
			if ($this->options->get('api.username', false))
			{
				$uri->setUser($this->options->get('api.username'));
			}

			if ($this->options->get('api.password', false))
			{
				$uri->setPass($this->options->get('api.password'));
			}
		}

		// If we have a defined page number add it to the JUri object.
		if ($page > 0)
		{
			$uri->setVar('page', (int) $page);
		}

		// If we have a defined items per page add it to the JUri object.
		if ($limit > 0)
		{
			$uri->setVar('per_page', (int) $limit);
		}

		return (string) $uri;
	}

	/**
	 * Method to get a response object, it will set headers if available
	 *
	 * @param   string   $path   URL to inflect
	 *
	 * @return  string   The request URL.
	 *
	 * @since   1.4
	 */
	public function getResponse($path, $page = 0, $limit = 0)
	{
		$response = $this->client->get($this->fetchUrl($path, $page, $limit));

		$this->setHeaders($response);

		return $response;
	}

	/**
	 * Process the response and decode it.
	 *
	 * @param   Response  $response      The response.
	 * @param   integer   $expectedCode  The expected "good" code.
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 * @throws  \DomainException
	 */
	protected function processResponse(Response $response, $expectedCode = 200)
	{
		// Validate the response code.
		if ($response->code != $expectedCode)
		{
			// Decode the error response and throw an exception.
			$error = json_decode($response->body);
			$message = isset($error->message) ? $error->message : 'Invalid response received from GitHub.';
			throw new \DomainException($message, $response->code);
		}

		return json_decode($response->body);
	}

	/**
	 * @TODO this should be moved into the separated class to follow the SRP
	 *
	 * @param $response
	 *
	 */
	private function setHeaders($response)
	{
		if (isset($response->headers))
		{
			$this->headers = $response->headers;

			if (isset($this->headers['Link']))
			{
				$this->setPagination($this->headers['Link']);
			}
		}
	}

	/**
	 * Set a pagination object
	 *
	 * @TODO this should be moved into the separated class to follow the SRP
	 *
	 * @param $link
	 *
	 * @return bool
	 */
	private function setPagination($link)
	{
		$elements = explode(',', $link);

		foreach($elements as $element)
		{
			$startPos = strpos($element, '<') + 1;
			$url = substr($element, $startPos, strpos($element, '>') - $startPos);
			$startPos = strpos($element, 'rel="') + 5;
			$type = substr($element, $startPos, strpos($element, '"', $startPos) - $startPos);

			parse_str($url, $params);
			$page = $params['page'];

			switch ($type)
			{
				case 'next':
					$this->pagination->set('page.next', $page);
					$this->pagination->set('link.next', $url);
					break;
				case 'last':
					$this->pagination->set('page.count', $page);
					$this->pagination->set('link.last', $url);
					break;
				case 'first':
					$this->pagination->set('page.first', $page);
					$this->pagination->set('link.first', $url);
					break;
				case 'prev':
					$this->pagination->set('page.prev', $page);
					$this->pagination->set('link.prev', $url);
					break;
			}
		}

		return true;
	}

	/**
	 * get the pagination object
	 *
	 * @TODO this should be moved into the separated class to follow the SRP
	 *
	 * @return Registry
	 */
	public function getPagination()
	{
		return $this->pagination;
	}
}
