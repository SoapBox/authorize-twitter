# Authorize-Twitter
[Authorize](http://github.com/soapbox/authorize) strategy for Twitter authentication.

## Getting Started
- Install [Authorize](http://github.com/soapbox/authorize) into your application
to use this Strategy.

## Installation
Add the following to your `composer.json`
```
"require": {
	...
	"soapbox/authorize-twitter": "dev-master",
	...
}
```

### app/config/app.php
Add the following to your `app.php`, note this will be removed in future
versions since it couples us with Laravel, and it isn't required for the library
to function
```
'providers' => array(
	...
	"SoapBox\AuthorizeTwitter\AuthorizeTwitterServiceProvider",
	...
)
```

## Usage

### Login
```php

use SoapBox\Authroize\Authenticator;
use SoapBox\Authorize\Exceptions\InvalidStrategyException;
...
$settings = [
	...
];

$strategy = new Authenticator('twitter', $settings);

$user = $strategy->authenticate($parameters);

```

### Endpoint
```php

use SoapBox\Authroize\Authenticator;
use SoapBox\Authorize\Exceptions\InvalidStrategyException;
...
$settings = [
	...
];

$strategy = new Authenticator('twitter', $settings);
$user = $strategy->endpoint();

```
