<?php


/*
Various operations on TeamTalk 5 accounts.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "vendor/autoload.php";

require_once "error.php";
require_once "server.php";


// TeamTalk 5 user type.
enum UserType: int {
	case NONE = 0;
	case DEFAULT = 1;
	case ADMIN = 2;
}

// Encapsulates TeamTalk 5 user information.
class UserInfo implements JsonSerializable {

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

	public function __construct(
		public readonly ServerInfo $server,
		public readonly string $username,
		public readonly string $password,
		public readonly string $nickname = "",
		public readonly UserType $type = UserType::DEFAULT,
		public readonly int $rights = self::RIGHT_DEFAULT
	) {}

	// Extracts a server name from the URL query string. If there seems to be no server name within URL, returns "default".
	private static function mServerNameFromUrl(): string {
		return isset($_GET["server"]) ? $_GET["server"] : "default";
	}

	/*
	Tries to construct an instance of UserInfo class from the parameters passed via the URL query string.
	This function also accepts an array of ServerInfo objects to search the server name extracted from URL in it.

	Throws BadQueryStringException when cannot collect all required information.
	*/
	public static function fromUrl(array $serverList): static {
		$username = @$_GET["name"];
		$password = @$_GET["password"];
		$server = null;
		$serverName = static::mServerNameFromUrl();
		$error = 0;
		foreach($serverList as $i) {
			if($i->name === $serverName) {
				$server = $i;
				break;
			}
		}
		if($server === null) {
			$error |= BadQueryStringException::INVALID_SERVER;
		}
		if(!$username) {
			$error |= BadQueryStringException::INVALID_NAME;
		}
		if(!$password) {
			$error |= BadQueryStringException::INVALID_PASSWORD;
		}
		if($error) {
			throw new BadQueryStringException($error);
		}
		return new UserInfo($server, $username, $password);
	}

	// Converts a UserInfo instance to an associative array which can be safely used by json_encode().
	public function jsonSerialize(): array {
		return array(
			"serverName" => $this->server->name,
			"username" => $this->username,
			"password" => $this->password,
			"nickname" => $this->nickname,
			"type" => $this->type->value,
			"rights" => $this->rights
		);
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

	// Returns true if an account with the given name exists on the current server; otherwise returns false.
	public function isRegistered(string $username): bool {
		$result = $this->findAccount(fn(UserInfo $account): bool => $account->username == $username);
		return $result === null ? false : true;
	}

	/*
	Creates a new account on the current server, returns its username.

	If the optional parameter $checkPremod is true (this is the default value)
	and the premoderation queue contains an account named $acc->username,
	AccountAlreadyExistsException is thrown.

	Throws AccountAlreadyExistsException if the name had previously been allocated on the server;
	may throw CommandFailedException in case of other problems.
	*/
	public function createAccount(UserInfo $acc, bool $checkPremod = true): string {
		if($checkPremod and $this->isDelayed($acc->username)) {
			throw new AccountAlreadyExistsException($acc->username);
		}
		if($this->isRegistered($acc->username)) {
			throw new AccountAlreadyExistsException($acc->username);
		}
		$this->mSession->executeCommand(
			"newaccount username=\"$acc->username\" password=\"$acc->password\"" .
			" usertype={$acc->type->value} userrights=$acc->rights"
		);
		return $acc->username;
	}

	/*
	Parses premod.json and returns it as a Json instance with autosaving disabled.
	If premod.json does not exist, the function creates that file.

	Throws RuntimeException in case of any problems.
	*/
	private static function mPreparePremodQueue(): Json {
		$queue = null;
		if(file_exists("premod.json")) {
			$queue = new Json("premod.json", false);
		}
		else {
			$file = @fopen("premod.json", "x+"); // create premod.json
			if($file === false) {
				throw new RuntimeException("Unable to create a premoderation file");
			}
			fwrite($file, "{}\n"); // add an empty JSON object to the created file
			fclose($file);
			$queue = new Json("premod.json", false);
		}
		return $queue;
	}

	/*
	Generates a new premoderation key.

	A premoderation key is a sequence of 32 random characters (0-9, a-z, A-Z, -, _),
	which pretends to be unique and crypto-resistant.
	It is used to identify a delayed account and to authorize actions with that account.
	*/
	private static function mGetPremodKey(): string {
		return sodium_bin2base64(random_bytes(24), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
	}

	/*
	Checks whether an account with the given username is waiting for premoderation on the current server;
	may throw RuntimeException.
	*/
	public function isDelayed(string $username): bool {
		$accounts = self::getDelayedAccountsFrom(array($this->mSession->account->server), $this->validator);
		foreach($accounts as $account) {
			if($account->username == $username) {
				return true;
			}
		}
		return false;
	}

	/*
	Returns an associative array, where keys are regular premoderation keys
	and values are UserInfo objects representing the corresponding accounts delayed for the given servers.
	*/
	public static function getDelayedAccountsFrom(array $servers, Validator $validator): array {
		if(!file_exists("premod.json")) {
			return array();
		}
		$result = array();
		$json = new Json("premod.json");
		$assoc = $json->get();
		foreach($assoc as $key => $item) {
			// Determine if there is a server with the required name.
			$server = null;
			foreach($servers as $i) {
				if($i->name == $item["serverName"]) {
					$server = $i;
				}
			}
			if($server === null) {
				continue;
			}
			// Construct a UserInfo object and add it to the resulting array.
			extract($item);
			$account = new UserInfo($validator, $server, $username, $password, "", UserType::from($type), $rights);
			$result[$key] = $account;
		}
		return $result;
	}

	/*
	Adds the given account to the premoderation queue and returns a unique key assigned to this new item.
	The queue is stored in file named premod.json, which is silently created if does not already exist.

	Throws AccountAlreadyExistsException if the username is unavailable;
	throws RuntimeException in case of other problems.
	*/
	public function delayAccount(UserInfo $acc): string {
		if($this->isDelayed($acc->username) or $this->isRegistered($acc->username)) {
			throw new AccountAlreadyExistsException($acc->username);
		}
		$queue = self::mPreparePremodQueue();
		$key = self::mGetPremodKey();
		$queue->set(new JsonPath($key), $acc);
		$queue->save();
		return $key;
	}

	/*
	Registers the delayed account (pointed-to by the given key) on the current TeamTalk 5 server,
	removes it from the premoderation queue, and returns its username.

	Throws InvalidArgumentException if there is no delayed account with this key;
	throws RuntimeException when it is impossible to construct a UserInfo object from the data stored in premod.json;
	throws AccountAlreadyExistsException if the name had previously been allocated on the server;
	may throw CommandFailedException in case of other problems.
	*/
	public function acceptAccount(string $key): string {
		$queue = new Json("premod.json");
		if(!$queue->exists(new JsonPath($key))) {
			throw new InvalidArgumentException("Invalid premoderation key");
		}
		$assoc = $queue->get(new JsonPath($key));
		$account = null;
		try {
			$account = new UserInfo(
				validator: $this->validator,
				server: $this->mSession->account->server,
				username: $assoc["username"],
				password: $assoc["password"],
				type: UserType::from($assoc["type"]),
				rights: $assoc["rights"]
			);
		}
		catch(InvalidArgumentException) {
			throw new RuntimeException("Corrupted premoderation queue");
		}
		$newUsername = $this->createAccount($account, false);
		$queue->unset(new JsonPath($key));
		return $newUsername;
	}

}
