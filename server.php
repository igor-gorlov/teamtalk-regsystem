<?php


/*
This file helps to communicate with the TeamTalk 5 server.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "configurator.php";
require_once "error.php";
require_once "validator.php";


// Encapsulates TeamTalk 5 server information.
class ServerInfo {
	// Throws InvalidArgumentException if one or more of the passed values are invalid.
	public function __construct(
		Validator $validator,
		public readonly string $host,
		public readonly int $port,
		public readonly string $name = "",
		public readonly string $title = ""
	) {
		$error = false;
		$errorMessage = "The following server properties are invalid:\n";
		if(!$validator->isValidHost($host)) {
			$error = true;
			$errorMessage .= "\tHost\n";
		}
		if(!$validator->isValidPort($port)) {
			$error = true;
			$errorMessage .= "\tPort\n";
		}
		if($error) {
			throw new InvalidArgumentException($errorMessage);
		}
	}
}

// TeamTalk 5 user type.
enum UserType: int {
	case NONE = 0;
	case DEFAULT = 1;
	case ADMIN = 2;
}

// Encapsulates TeamTalk 5 user information.
class UserInfo {

	// TeamTalk 5 user rights.
	const RIGHT_NONE = 0x00000000;
	const RIGHT_MULTILOGIN = 0x00000001;
	const RIGHT_VIEW_ALL_USERS = 0x00000002;
	const RIGHT_CREATE_TEMPORARY_CHANNEL = 0x00000004;
	const RIGHT_MODIFY_CHANNELS = 0x00000008;
	const RIGHT_TEXT_MESSAGE_BROADCAST = 0x00000010;
	const RIGHT_KICK_USERS = 0x00000020;
	const RIGHT_BAN_USERS = 0x00000040;
	const RIGHT_MOVE_USERS = 0x00000080;
	const RIGHT_OPERATOR_ENABLE = 0x00000100;
	const RIGHT_UPLOAD_FILES = 0x00000200;
	const RIGHT_DOWNLOAD_FILES = 0x00000400;
	const RIGHT_UPDATE_SERVER_PROPERTIES = 0x00000800;
	const RIGHT_TRANSMIT_VOICE = 0x00001000;
	const RIGHT_TRANSMIT_VIDEO_CAPTURE = 0x00002000;
	const RIGHT_TRANSMIT_DESKTOP = 0x00004000;
	const RIGHT_TRANSMIT_DESKTOP_INPUT = 0x00008000;
	const RIGHT_TRANSMIT_MEDIA_FILE_AUDIO = 0x00010000;
	const RIGHT_TRANSMIT_MEDIA_FILE_VIDEO = 0x00020000;
	const RIGHT_TRANSMIT_MEDIA_FILE = self::RIGHT_TRANSMIT_MEDIA_FILE_AUDIO | self::RIGHT_TRANSMIT_MEDIA_FILE_VIDEO;
	const RIGHT_LOCKED_NICKNAME = 0x00040000;
	const RIGHT_LOCKED_STATUS = 0x00080000;
	const RIGHT_RECORD_VOICE = 0x00100000;
	const RIGHT_VIEW_HIDDEN_CHANNELS = 0x00200000;
	const RIGHT_DEFAULT = self::RIGHT_MULTILOGIN | self::RIGHT_VIEW_ALL_USERS | self::RIGHT_CREATE_TEMPORARY_CHANNEL |
		self::RIGHT_UPLOAD_FILES | self::RIGHT_DOWNLOAD_FILES | self::RIGHT_TRANSMIT_VOICE |
		self::RIGHT_TRANSMIT_VIDEO_CAPTURE | self::RIGHT_TRANSMIT_DESKTOP | self::RIGHT_TRANSMIT_DESKTOP_INPUT |
		self::RIGHT_TRANSMIT_MEDIA_FILE;
	const RIGHT_ADMIN = 0x001fffff; // All flags in one.

	// Throws InvalidArgumentException if one or more of the passed values do not comply to the requirements.
	public function __construct(
		Validator $validator,
		public readonly ServerInfo $server,
		public readonly string $username,
		public readonly string $password,
		public readonly string $nickname = "",
		public readonly UserType $type = UserType::DEFAULT,
		public readonly int $rights = self::RIGHT_DEFAULT
	) {
		$error = false;
		$errorMessage = "The following user properties are invalid:\n";
		if(!$validator->isValidUsername($username)) {
			$error = true;
			$errorMessage .= "\tUsername\n";
		}
		if(!$validator->isValidPassword($password)) {
			$error = true;
			$errorMessage .= "\tPassword\n";
		}
		if(!$validator->isValidNickname($nickname)) {
			$error = true;
			$errorMessage .= "\tNickname\n";
		}
		if($error) {
			throw new InvalidArgumentException($errorMessage);
		}
	}

}

// Represents a single command.
class Command {
	public function __construct(
		public string $name,
		public array $params = array()
	) {}
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
	Establishes connection to a TeamTalk 5 server and performs authorization under the given account.
	For most of the operations to succeed, the account must have admin rights.

	Throws ServerUnavailableException if cannot connect to the server for some reason;
	throws InvalidCommandException in case of other problems.
	*/
	public function __construct(public readonly Validator $validator, public readonly UserInfo $account) {
		$this->mLastId = 0;
		// Connect to the server.
		$this->mSocket = @fsockopen($account->server->host, $account->server->port);
		if($this->mSocket === false) {
			throw new ServerUnavailableException($account->server);
		}
		// Login under the admin account.
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
		$matches = array(); // a reusable array to store preg_match results in.
		// Extract the name.
		preg_match("/^([a-z]+\b)(\s*)/i", $command, $matches);
		$result = new Command($matches[1]);
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
				if($value === array("")) {
					$value = array();
				}
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

	/*
	Returns an array of UserInfo objects representing all accounts that exist on the server.
	Throws CommandFailedException or InvalidArgumentException on error.
	*/
	public function getAllAccounts(): array {
		$result = array();
		$reply = $this->executeCommand("listaccounts");
		for($i = 0; $reply[$i]->name == "useraccount"; $i++) {
			$result[] = new UserInfo(
				validator: $this->validator,
				server: $this->account->server,
				username: $reply[$i]->params["username"],
				password: $reply[$i]->params["password"],
				type: UserType::from($reply[$i]->params["usertype"]),
				rights: $reply[$i]->params["userrights"]
			);
		}
		return $result;
	}

	/*
	Searches accounts using the given callback function, returns a sequentially indexed array of UserInfo instancies.

	The callback takes a UserInfo object, returns true if this account must be included in the search results,
	returns false if it must be ignored.
	*/
	public function findAccounts(callable $callback): array {
		$allAccounts = $this->getAllAccounts();
		$resultWithGaps = array_filter($allAccounts, $callback);
		return array_values($resultWithGaps);
	}

	/*
	Returns the first account which satisfies the condition defined by the given callback;
	if nothing was found, returns null.

	The callback takes a UserInfo object, returns true if this account is what we are looking for,
	returns false if it must be ignored.
	*/
	public function findAccount(callable $callback): UserInfo|null {
		$allAccounts = $this->getAllAccounts();
		foreach($allAccounts as $account) {
			if($callback($account)) {
				return $account;
			}
		}
		return null;
	}

	// Returns true if an account with the given name exists; otherwise returns false.
	public function accountExists(string $username): bool {
		$result = $this->findAccount(fn(UserInfo $account): bool => $account->username == $username);
		return $result === null ? false : true;
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
			"newaccount username=\"$acc->username\" password=\"$acc->password\"" .
			" usertype={$acc->type->value} userrights=$acc->rights"
		);
		return $acc->username;
	}

	/*
	Performs authorization with the configured parameters.
	Throws CommandFailedException on error.
	*/
	public function mLogin(): void {
		$this->executeCommand(
			"login username=\"{$this->account->username}\" password=\"{$this->account->password}\" nickname=\"{$this->account->nickname}\" protocol=\"5.0\""
		);
	}

}
