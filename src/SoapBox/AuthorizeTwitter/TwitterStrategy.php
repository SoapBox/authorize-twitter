<?php namespace SoapBox\AuthorizeTwitter;

use TwitterOAuth\OAuth\Api;
use SoapBox\Authorize\Exceptions\MissingArgumentsException;
use SoapBox\Authorize\Exceptions\AuthorizationException;

class TwitterStrategy extends SingleSignOnStrategy {

	private $twitter;

	public function __construct($settings = array()) {
		session_start();
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
	}

	public function login($parameters = array()) {
		if ( !isset($parameters['redirect_url']) ) {
			throw new MissingArgumentsException(
				'redirect_url is required'
			);
		}

		$requestToken = $this->twitter->getRequestToken($parameters['redirect_url']);

		$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
		$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

		switch ($this->twitter->http_code) {
			case 200:
				Helpers::redirect($this->twitter->getAuthorizeURL($token));
				break;
			default:
				throw new AuthorizationException();
		}

	}

	public function getUser($parameters = array()) {
		if ( !isset($parameters['accessToken']) ) {
			throw new AuthorizationException();
		}

		$accessToken = $parameters['accessToken'];

		$this->twitter->setTokens($accessToken['oauth_token'], $accessToken['oauth_token_secret']);

		$response = $this->twitter->get('account/verify_credentials');

		$user = new User;
		$user->id = $response->id;
		$user->displayName = $response->screen_name;
		$user->accessToken = $accessToken;
		$user->firstname = $response->name;

		return $user;
	}

	public function getFriends($parameters = array()) {
		if ( !isset($parameters['accessToken']) ) {
			throw new AuthorizationException();
		}

		$accessToken = $parameters['accessToken'];

		$this->twitter->setTokens($accessToken['oauth_token'], $accessToken['oauth_token_secret']);

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
		$this->twitter->setTokens($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
		$accessToken = $this->twitter->getAccessToken($_REQUEST['oauth_verifier']);

		unset($_SESSION['oauth_token']);
		unset($_SESSION['oauth_token_secret']);

		return $this->getUser(['accessToken' => $accessToken]);
	}

}
