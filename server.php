<?php


/*
This file helps to communicate with the TeamTalk 5 server.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


// Encapsulates TeamTalk 5 user information.
class UserInfo {

	private string $mUsername;
	private string $mPassword;

	// Throws InvalidArgumentException if one or more of the passed values do not comply to the requirements.
	public function __construct(string $username, string $password, public string $nickname="") {
		$error = false;
		$errorMessage = "The following user properties are invalid:\n";
		if(!static::isValidUsername($username)) {
			$error = true;
			$errorMessage .= "\tUsername\n";
		}
		if(!static::isValidPassword($password)) {
			$error = true;
			$errorMessage .= "\tPassword\n";
		}
		if($error) {
			throw new InvalidArgumentException($errorMessage);
		}
		$this->mUsername = $username;
		$this->mPassword = $password;
	}

	// Sets a username if it is valid, otherwise throws InvalidArgumentException.
	function setUsername(string $username): void {
		if(!static::isValidUsername($username)) {
			throw new InvalidArgumentException("Invalid username");
		}
		$this->mUsername = $username;
	}

	// Returns the current username.
	function getUsername(): string {
		return $this->mUsername;
	}

	// Sets a password if it is valid, otherwise throws InvalidArgumentException.
	function setPassword(string $password): void {
		if(!static::isValidPassword($password)) {
			throw new InvalidArgumentException("Invalid password");
		}
		$this->mPassword = $password;
	}

	// Returns the current password.
	function getPassword(): string {
		return $this->mPassword;
	}

	// Validates a username.
	public static function isValidUsername(string $str): bool {
		return strlen($str) > 0;
	}

	// Validates a password.
	public static function isValidPassword(string $str): bool {
		return strlen($str) > 0;
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
		$message = "The following command failed:\n\t$command";
		if($reply != null) {
			$errorCode = $reply[array_key_last($reply)]->params["number"];
			$serverMessage = $reply[array_key_last($reply)]->params["message"];
			$message .= "The server returned error code $errorCode and said:\n\t$serverMessage\n";
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

// Represents a single connection with a TeamTalk 5 server.
class Tt5Session {

	public const REPLY_AS_TEXT = 1;
	public const REPLY_AS_ARRAY = 2;

	private $mSocket; // the actual underlying connection.
	private int $mLastId; // a counter used to compute command identifiers.

	/*
	The constructor does not establish connection:
	this operation may take a lot of time and thus should be delayed while possible.
	*/
	public function __construct(public readonly string $address, public readonly int $port) {
		$this->mLastId = 0;
		$this->mSocket = null;
	}

	/*
	Establishes connection if it has not been already established.
	Throws ServerUnavailableException if cannot connect.
	*/
	private function mEnsureConnection(): void {
		if($this->mSocket === null) {
			$this->mSocket = @fsockopen($this->address, $this->port);
			if($this->mSocket === false) {
				$this->mSocket = null;
				throw new ServerUnavailableException($this->address, $this->port);
			}
		}
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
			elseif(preg_match("/^(\d+\b)(\s*)/i", substr($command, $offset), $matches)) { // integer
				$value = intval($matches[1]);
			}
			elseif(preg_match("/^\[(((\d+,)*\d+)?)\]\s*/i", substr($command, $offset), $matches)) { // array of integers
				$value = explode(",", $matches[1]);
				foreach($value as &$elem) {
					$elem = intval($elem);
				}
			}
			elseif(preg_match('/^\"(.*?)(\\\\)*\"\s*/i', substr($command, $offset), $matches)) { // string
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
		$this->mEnsureConnection();
		$id = ++$this->mLastId;
		$command .= " id=$id\r\n";
		fwrite($this->mSocket, $command);
		return $id;
	}

	/*
	Sends the given command to the TeamTalk 5 server and waits for the server's reply.

	The return value type depends on the optional argument $outputMode. You can choose between two modes:
		* Tt5Session::REPLY_AS_TEXT: a plain text string is returned;
		* Tt5Session::REPLY_AS_ARRAY [default]: an array of objects of type Command is returned.

	If the server returns an error, and the output mode is Tt5Session::REPLY_AS_ARRAY,
	this function throws CommandFailedException; no exceptions is thrown in text mode even if an error occurs.

	Note that you must NOT explicitly use "id" parameter in your command or finish it with "\r\n" sequence:
	the function will handle those things implicitly.
	*/
	public function executeCommand(string $command, int $outputMode = self::REPLY_AS_ARRAY): string|array {
		$id = $this->sendCommand($command);
		// Wait for the reply.
		$respondingText = $this->getRespondingText($id);
		$respondingCommands = static::parseRespondingText($respondingText);
		// Check for errors.
		if($respondingCommands[array_key_last($respondingCommands)]->name == "error" and $outputMode == self::REPLY_AS_ARRAY) {
			throw new CommandFailedException($command, $respondingCommands);
		}
		// Return the required result.
		switch($outputMode) {
			case self::REPLY_AS_TEXT:
				return $respondingText;
			case self::REPLY_AS_ARRAY:
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
		$username = $acc->getUsername();
		$password = $acc->getPassword();
		if($this->accountExists($username)) {
			throw new AccountAlreadyExistsException($username);
		}
		$this->executeCommand(
			"newaccount username=\"$username\" password=\"$password\" usertype=1"
		);
		return $username;
	}

	/*
	Performs authorization with the given parameters.
	Throws CommandFailedException on error.
	*/
	public function login(UserInfo $acc): void {
		$username = $acc->getUsername();
		$password = $acc->getPassword();
		$this->executeCommand(
			"login username=\"$username\" password=\"$password\" nickname=\"$acc->nickname\" protocol=\"5.0\""
		);
	}

}


?>
