<?php


/*
A collection of error handling utilities.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "vendor/autoload.php";


// Is thrown when a command yields an error.
class CommandFailedException extends RuntimeException {
	public function __construct(string $command, ?array $reply = null) {
		$message = "The following command failed:\n$command";
		if($reply != null) {
			$errorCode = $reply[array_key_last($reply)]->params["number"];
			$serverMessage = $reply[array_key_last($reply)]->params["message"];
			$message .= "\nThe server returned error code $errorCode and said:\n$serverMessage\n";
		}
		parent::__construct($message);
	}
}

// Is thrown when attempting to register an account that already exists.
class AccountAlreadyExistsException extends RuntimeException {
	public function __construct(string $username) {
		parent::__construct("Unable to create account named $username because this username is already taken");
	}
}

// Is thrown when an attempt to establish connection with the TeamTalk 5 server fails.
class ServerUnavailableException extends RuntimeException {
	public function __construct(ServerInfo $server) {
		parent::__construct("Unable to connect to $server->host:$server->port");
	}
}

// Is thrown when one or more URL parameters needed for some task are missing.
class BadQueryStringException extends RuntimeException {

	public const INVALID_UNKNOWN = 0x00000000;
	public const INVALID_SERVER = 0x00000001;
	public const INVALID_NAME = 0x00000002;
	public const INVALID_PASSWORD = 0x00000004;

	public function __construct(public readonly int $invalidUrlParams = self::INVALID_UNKNOWN) {
		$message = "";
		if($invalidUrlParams == 0) {
			$message = "One or more URL parameters are invalid";
		}
		else {
			$message = "The following URL parameters are invalid:";
			if(($invalidUrlParams & self::INVALID_SERVER) == self::INVALID_SERVER) {
				$message .= "\nserver";
			}
			if(($invalidUrlParams & self::INVALID_NAME) == self::INVALID_NAME) {
				$message .= "\nname";
			}
			if(($invalidUrlParams & self::INVALID_PASSWORD) == self::INVALID_PASSWORD) {
				$message .= "\npassword";
			}
		}
		parent::__construct($message);
	}

}

// Is thrown on a problem with JSON.
class InvalidJsonException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

// Is thrown on a problem with configuration.
class InvalidConfigException extends RuntimeException {
	public function __construct(string $filename) {
		parent::__construct("Invalid configuration file \"$filename\"");
	}
}

// Is thrown when a language file for a specific locale cannot be found.
class UnknownLocaleException extends RuntimeException {
	public function __construct(string $locale) {
		parent::__construct("There is no language file for locale \"$locale\"");
	}
}
