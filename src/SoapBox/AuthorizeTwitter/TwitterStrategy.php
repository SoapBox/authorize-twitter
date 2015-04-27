<?php namespace SoapBox\AuthorizeTwitter;

use TwitterOAuth\Api;
use SoapBox\Authorize\Helpers;
use SoapBox\Authorize\User;
use SoapBox\Authorize\Contact;
use SoapBox\Authorize\Exceptions\MissingArgumentsException;
use SoapBox\Authorize\Exceptions\AuthenticationException;
use SoapBox\Authorize\Strategies\SingleSignOnStrategy;

class TwitterStrategy extends SingleSignOnStrategy {

	private $twitter;

	public static $store = null;
	public static $load = null;

	public function __construct($parameters = array(), $store = null, $load = null) {
		if( !isset($parameters['consumer_key']) ||
			!isset($parameters['consumer_secret']) ) {
			throw new MissingArgumentsException(
				'Required parameters consumer_key, or consumer_secret are missing'
			);
		}

		$this->twitter = new Api(
			$parameters['consumer_key'],
			$parameters['consumer_secret']
		);

		if ($store != null && $load != null) {
			TwitterStrategy::$store = $store;
			TwitterStrategy::$load = $load;
		} else {
			session_start();
			TwitterStrategy::$store = function($key, $value) {
				$_SESSION[$key] = $value;
			};
			TwitterStrategy::$load = function($key) {
				return $_SESSION[$key];
			};
		}

		$this->twitter->host = 'https://api.twitter.com/1.1/';
	}

	public function login($parameters = array()) {
		$store = TwitterStrategy::$store;

		if ( !isset($parameters['redirect_url']) ) {
			throw new MissingArgumentsException(
				'redirect_url is required'
			);
		}

		$requestToken = $this->twitter->getRequestToken($parameters['redirect_url']);

		$store('twitter.oauth_token', $requestToken['oauth_token']);
		$store('twitter.oauth_token_secret', $requestToken['oauth_token_secret'], true);

		$token = $requestToken['oauth_token'];

		switch ($this->twitter->http_code) {
			case 200:
				Helpers::redirect($this->twitter->getAuthorizeURL($token, true));
				break;
			default:
				throw new AuthorizationException();
		}

	}

	public function getUser($parameters = array()) {
		if ( !isset($parameters['accessToken']) ) {
			throw new AuthorizationException();
		}

		$accessToken = json_decode($parameters['accessToken']);

		$this->twitter->setTokens($accessToken->oauth_token, $accessToken->oauth_token_secret);

		$response = $this->twitter->get('account/verify_credentials');

		$user = new User;
		$user->id = $response->id;
		$user->displayName = $response->screen_name;
		$user->accessToken = json_encode($accessToken);
		$name = explode(' ', $response->name, 2);
		$user->firstname = $name[0];
		$user->lastname = $name[1];

		return $user;
	}

	public function getFriends($parameters = array()) {
		if ( !isset($parameters['accessToken']) ) {
			throw new AuthorizationException();
		}

		$accessToken = json_decode($parameters['accessToken']);

		$this->twitter->setTokens($accessToken->oauth_token, $accessToken->oauth_token_secret);

		$response = $this->twitter->get('friends/ids');

		$contactIds = array_chunk($response->ids, 75);

		$friends = [];

		foreach ($contactIds as $chunk) {
			$parameters = ['user_id' => implode(',', $chunk)];
			$temp = $this->twitter->get('users/lookup', $parameters);

			if ($this->twitter->http_code != 200) {
				throw new \Exception('Failed to get friends');
			}

			if ($temp && count($temp)) {
				foreach ($temp as $item) {
					$contact = new Contact;
					$contact->displayName = $item->screen_name;
					$friends[] = $contact;
				}
			}
		}

		return $friends;
	}

	public function endpoint($parameters = array()) {
		$load = TwitterStrategy::$load;

		$token = $load('twitter.oauth_token');
		$secret = $load('twitter.oauth_token_secret');

		if (empty($token) || empty($secret)) {
			throw new AuthorizationException();
		}

		$this->twitter->setTokens($token, $secret);
		$accessToken = $this->twitter->getAccessToken($_REQUEST['oauth_verifier']);

		return $this->getUser(['accessToken' => json_encode($accessToken)]);
	}

}
