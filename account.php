<?php


/*
Various operations on TeamTalk 5 accounts.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "error.php";
require_once "server.php";


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
		public readonly Validator $validator,
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

	// Extracts a server name from the URL query string. If there seems to be no server name within URL, returns "default".
	private static function mServerNameFromUrl(): string {
		return isset($_GET["server"]) ? $_GET["server"] : "default";
	}

	/*
	Tries to construct an instance of UserInfo class from the parameters passed via the URL query string;
	The given Validator object is used to ensure correctness of those parameters.

	In addition to the validator, this function also accepts an array of ServerInfo objects
	to search the server name extracted from URL in it.

	Throws BadQueryStringException if the actual set of required fields within the URL is incomplete;
	Throws RuntimeException if the user information is invalid.
	*/
	public static function fromUrl(Validator $validator, array $serverList): static {
		$server = null;
		$serverName = static::mServerNameFromUrl();
		foreach($serverList as $i) {
			if($i->name === $serverName) {
				$server = $i;
				break;
			}
		}
		if($server === null) {
			throw new RuntimeException("Cannot find a server named \"$serverName\"");
		}
		$error = false;
		$errorMessage = "The following URL parameters are not provided:\n";
		if(!isset($_GET["name"])) {
			$error = true;
			$errorMessage .= "\tname\n";
		}
		if(!isset($_GET["password"])) {
			$error = true;
			$errorMessage .= "\tpassword\n";
		}
		if($error) {
			throw new BadQueryStringException($errorMessage);
		}
		return new UserInfo($validator, $server, $_GET["name"], $_GET["password"]);
	}

}

// A high-level interface for viewing and manipulating accounts.
class AccountManager {

	public function __construct(public readonly Validator $validator, private Tt5Session $mSession) {}

	/*
	Returns an array of UserInfo objects representing all accounts that exist on the server.
	Throws CommandFailedException or InvalidArgumentException on error.
	*/
	public function getAllAccounts(): array {
		$result = array();
		$reply = $this->mSession->executeCommand("listaccounts");
		for($i = 0; $reply[$i]->name == "useraccount"; $i++) {
			$result[] = new UserInfo(
				validator: $this->validator,
				server: $this->mSession->account->server,
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
		$this->mSession->executeCommand(
			"newaccount username=\"$acc->username\" password=\"$acc->password\"" .
			" usertype={$acc->type->value} userrights=$acc->rights"
		);
		return $acc->username;
	}

}
