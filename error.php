<?php


/*
A collection of error handling utilities.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


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
	public function __construct(string $message) {
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
	public function __construct(string $message) {
		parent::__construct($message);
	}
}


// The default handler for uncaught exceptions and errors. Displays a pretty formatted message in a browser.
function printErrorMessage(Throwable $e): void {
	ob_start();
	echo("<table><tr><td style=\"color: red\"><strong>Error!</strong></td><td><pre><code>");
	echo($e->getMessage());
	echo("<br>(" . $e->getFile() . " : " . $e->getLine() . ")");
	echo("</code></pre></td></tr></table>");
	ob_end_flush();
}
