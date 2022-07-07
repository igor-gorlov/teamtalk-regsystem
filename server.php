<?php


/*
This file helps to communicate with the TeamTalk 5 server.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "conf.php";


// Encapsulates TeamTalk 5 user information.
class UserInfo {
	// Throws InvalidArgumentException if one or more of the passed values do not comply to the requirements.
	public function __construct(
		private readonly Configurator $mConfig,
		public readonly string $username,
		public readonly string $password,
		public readonly string $nickname = ""
	) {
		$error = false;
		$errorMessage = "The following user properties are invalid:\n";
		if(!static::isValidUsername($username, $this->mConfig)) {
			$error = true;
			$errorMessage .= "\tUsername\n";
		}
		if(!static::isValidPassword($password, $this->mConfig)) {
			$error = true;
			$errorMessage .= "\tPassword\n";
		}
		if($error) {
			throw new InvalidArgumentException($errorMessage);
		}
	}

	/*
	Validates a username against the configured regular expression;
	if no regexp is configured, the following is used: "/.+/i".

	If an error occurres during validation process, the method throws RuntimeException.
	*/
	public static function isValidUsername(string $str, Configurator $config): bool {
		$regexp = "";
		if(!$config->exists("validation.username")) {
			$regexp = "/.+/i";
		}
		else {
			$regexp = $config->get("validation.username");
		}
		$result = @preg_match($regexp, $str);
		if($result === false) {
			throw new RuntimeException("Unable to validate a username");
		}
		return boolval($result);
	}

	/*
	Validates a password against the configured regular expression;
	if no regexp is configured, the following is used: "/.+/i".

	If an error occurres during validation process, the method throws RuntimeException.
	*/
	public static function isValidPassword(string $str, Configurator $config): bool {
		$regexp = "";
		if(!$config->exists("validation.password")) {
			$regexp = "/.+/i";
		}
		else {
			$regexp = $config->get("validation.password");
		}
		$result = @preg_match($regexp, $str);
		if($result === false) {
			throw new RuntimeException("Unable to validate a password");
		}
		return boolval($result);
	}

}

// Represents a single command.
class Command {
	public string $name;
	public array $params;
}

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
	public function __construct(string $address, int $port) {
		parent::__construct("Unable to connect to $address:$port");
	}
}

// Possible representation types for command responses.
enum ReplyMode {
	case AS_TEXT;
	case AS_ARRAY;
}

// Represents a single connection with a TeamTalk 5 server.
class Tt5Session {

	private $mSocket; // the actual underlying connection.
	private int $mLastId; // a counter used to compute command identifiers.

	/*
	Establishes connection to the TeamTalk 5 server pointed-to by the given name
	and performs authorization under the configured system account.

	Throws InvalidArgumentException if the name is unknown;
	throws ServerUnavailableException if cannot connect to a well-known server for some reason;
	throws InvalidCommandException in case of other problems.
	*/
	public function __construct(public readonly string $serverName, private readonly Configurator $mConfig) {
		$this->mLastId = 0;
		// Retrieve server information.
		if(!$mConfig->exists("servers.$serverName")) {
			throw new InvalidArgumentException("Unknown server \"$serverName\"");
		}
		$serverData = $mConfig->get("servers.$serverName");
		// Connect to the server.
		$this->mSocket = @fsockopen($serverData["host"], $serverData["port"]);
		if($this->mSocket === false) {
			throw new ServerUnavailableException($serverData["host"], $serverData["port"]);
		}
		// Login under the system account.
		$this->mLogin();
	}

	/*
	Waits for the server to process the command with the given id;
	returns the server's reply (with "begin" and "end" parts excluded).
	*/
	public function getRespondingText(int $id): string {
		$text = "";
		while(true) { // scan the communication history again and again until the reply is found.
			while($line = fgets($this->mSocket)) {
				if($line == "begin id=$id\r\n") { // the beginning of the reply is found.
					for($respondingLine = fgets($this->mSocket); $respondingLine != "end id=$id\r\n"; $respondingLine = fgets($this->mSocket)) {
						$text .= $respondingLine;
					}
					return $text;
				}
			}
		}
	}

	/*
	Accepts a command in the form of a string; returns an object containing the parsed data.
	This function expects the input to be a syntactically correct TeamTalk 5 command; no validation is performed.
	*/
	public static function parseCommand(string $command): Command {
		$result = new Command;
		$matches = array(); // a reusable array to store preg_match results in.
		// Extract the name.
		preg_match("/^([a-z]+\b)(\s*)/i", $command, $matches);
		$result->name = $matches[1];
		$offset = strlen($matches[0]);
		// Parse the parameters.
		while($offset != strlen($command)) {
			// Extract the parameter name.
			preg_match("/^([a-z]+\b)\=/i", substr($command, $offset), $matches);
			$paramName = $matches[1];
			$offset += strlen($matches[0]);
			// Extract the parameter value.
			$value = null;
			if(preg_match("/^(true\b|false\b)(\s*)/i", substr($command, $offset), $matches)) { // boolean
				if($matches[1] == "true") {
					$value = true;
				}
				else {
					$value = false;
				}
			}
			elseif(preg_match("/^(-?\d+\b)(\s*)/i", substr($command, $offset), $matches)) { // integer
				$value = intval($matches[1]);
			}
			elseif(preg_match("/^\[(((-?\d+,)*-?\d+)?)\]\s*/i", substr($command, $offset), $matches)) { // array of integers
				$value = explode(",", $matches[1]);
				foreach($value as &$elem) {
					$elem = intval($elem);
				}
			}
			elseif(preg_match('/^\"(|(\\\\\\\\)+|.*?[^\\\\](\\\\\\\\)*)\"\s*/i', substr($command, $offset), $matches)) { // string
				$value = $matches[1];
			}
			$result->params[$paramName] = $value;
			$offset += strlen($matches[0]);
		}
		return $result;
	}

	/*
	Parses a server reply into an array of objects of type Command.
	The reply must be syntactically correct; this function performs no validation.
	*/
	public static function parseRespondingText(string $text): array {
		// Prepare a container for future results.
		$commands = array();
		// Split the text into lines, which in fact are equivalent to commands.
		$text = rtrim($text);
		$lines = explode("\r\n", $text);
		// Build the resulting array.
		foreach($lines as &$line) {
			$command = static::parseCommand($line);
			$commands[] = $command;
		}
		return $commands;
	}

	/*
	Sends the given command to the TeamTalk 5 server and transfers control back immediately;
	returns the ID assigned to this command.
	The result of command execution can be obtained later with getRespondingText() method.

	Note that you must NOT explicitly use "id" parameter in your command or finish it with "\r\n" sequence:
	the function will handle those things implicitly.
	*/
	public function sendCommand(string $command): int {
		$id = ++$this->mLastId;
		$command .= " id=$id\r\n";
		fwrite($this->mSocket, $command);
		return $id;
	}

	/*
	Sends the given command to the TeamTalk 5 server and waits for the server's reply.

	The return value type depends on the optional argument $outputMode. You can choose between two modes:
		* ReplyMode::AS_TEXT: a plain text string is returned;
		* ReplyMode::AS_ARRAY [default]: an array of objects of type Command is returned.

	If the server returns an error, and $mode = ReplyMode::AS_ARRAY, this function throws CommandFailedException;
	no exceptions is thrown in text mode even if an error occurs.

	Note that you must NOT explicitly use "id" parameter in your command or finish it with "\r\n" sequence:
	the function will handle those things implicitly.
	*/
	public function executeCommand(string $command, ReplyMode $mode = ReplyMode::AS_ARRAY): string|array {
		$id = $this->sendCommand($command);
		// Wait for the reply.
		$respondingText = $this->getRespondingText($id);
		$respondingCommands = static::parseRespondingText($respondingText);
		// Check for errors.
		if($respondingCommands[array_key_last($respondingCommands)]->name == "error" and $mode == ReplyMode::AS_ARRAY) {
			throw new CommandFailedException($command, $respondingCommands);
		}
		// Return the required result.
		switch($mode) {
			case ReplyMode::AS_TEXT:
				return $respondingText;
			case ReplyMode::AS_ARRAY:
				return $respondingCommands;
		}
	}

	// Returns true if an account with the given name exists; otherwise returns false.
	public function accountExists(string $name): bool {
		$reply = $this->executeCommand("listaccounts");
		for($i = 0; $reply[$i]->name == "useraccount"; $i++) {
			$username = $reply[$i]->params["username"];
			if($username == $name) {
				return true;
			}
		}
		return false;
	}

	/*
	Creates a new account of "default" type with the given name and password, returns its username.
	Throws AccountAlreadyExistsException if the name had previously been allocated on the server;
	may throw CommandFailedException in case of other problems.
	*/
	public function createAccount(UserInfo $acc): string {
		if($this->accountExists($acc->username)) {
			throw new AccountAlreadyExistsException($acc->username);
		}
		$this->executeCommand(
			"newaccount username=\"$acc->username\" password=\"$acc->password\" usertype=1"
		);
		return $acc->username;
	}

	/*
	Performs authorization with the configured parameters.
	Throws CommandFailedException on error.
	*/
	public function mLogin(): void {
		$username = $this->mConfig->get("servers.$this->serverName.systemAccount.username");
		$password = $this->mConfig->get("servers.$this->serverName.systemAccount.password");
		$nickname = $this->mConfig->get("servers.$this->serverName.systemAccount.nickname");
		$this->executeCommand(
			"login username=\"$username\" password=\"$password\" nickname=\"$nickname\" protocol=\"5.0\""
		);
	}

}
