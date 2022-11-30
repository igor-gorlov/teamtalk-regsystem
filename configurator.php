<?php


/*
Gives the ability to load, review, manipulate and store program settings.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "vendor/autoload.php";

require_once "error.php";
require_once "json.php";
require_once "server.php";


// Provides an interface to configuration. At most one instance of this class can exist at any moment.
class Configurator {

	public const MAX_NUMBER_OF_INSTANCIES = 1;

	private static int $mNumberOfInstancies = 0;

	private Json $mSource;

	/*
	Loads configuration from the given source;
	throws BadMethodCallException if another object of type Configurator already exists,
	throws InvalidConfigException if the given JSON structure is not suitable for configuration purposes.
	*/
	public function __construct(Json $source) {
		if(static::$mNumberOfInstancies == static::MAX_NUMBER_OF_INSTANCIES) {
			throw new BadMethodCallException("Unable to construct a Configurator object: the maximum number of instancies is " . static::MAX_NUMBER_OF_INSTANCIES);
		}
		static::validate($source);
		$this->mSource = $source;
		static::$mNumberOfInstancies++;
	}

	/*
	Returns a set of validation rules (as required by Validator class).
	If no validation rules are configured, or if `validation` JSON object is absent, an empty array is returned.
	*/
	public function getValidationRules(): array {
		if(
			!$this->mSource->exists(new JsonPath("validation")) or
			count($this->mSource->get(new JsonPath("validation"))) == 0
		) {
			return array();
		}
		return $this->mSource->get(new JsonPath("validation"));
	}

	/*
	Returns a ServerInfo instance which describes the managed server pointed-to by the given name.
	Throws InvalidArgumentException if there is no server with such name.
	*/
	public function getServerInfo(string $name): ServerInfo {
		if(!$this->mSource->exists(new JsonPath("servers", $name))) {
			throw new InvalidArgumentException("No server named \"$name\" is configured");
		}
		$data = $this->mSource->get(new JsonPath("servers", $name));
		return new ServerInfo(
			validator: $this->validator,
			name: $name,
			title: $data["title"],
			host: $data["host"],
			port: $data["port"]
		);
	}

	// Returns true when premoderation for the given server is enabled, otherwise returns false.
	public function isPremoderatedServer(string $serverName): bool {
		return $this->mSource->get(new JsonPath($serverName, "premod", "enabled"));
	}

	/*
	Returns an array of ServerInfo objects describing all managed servers currently configured.
	If there are no servers, the returned array is empty.
	*/
	public function getAllServersInfo(): array {
		$names = array_keys($this->mSource->get(new JsonPath("servers")));
		$result = array();
		foreach($names as $name) {
			$result[] = $this->getServerInfo($name);
		}
		return $result;
	}

	/*
	Returns a UserInfo object representing the system account
	which belongs to the managed server pointed-to by the given name.

	Throws InvalidArgumentException if there is no server with such name;
	throws InvalidConfigException if a system account is configured incorrectly.
	*/
	public function getSystemAccountInfo(string $serverName): UserInfo {
		if(!$this->mSource->exists(new JsonPath("servers", $serverName))) {
			throw new InvalidArgumentException("No server named \"$serverName\" is configured");
		}
		$data = $this->mSource->get(new JsonPath("servers", $serverName));
		try {
			return new UserInfo(
				validator: $this->validator,
				server: $this->getServerInfo($serverName),
				username: $data["systemAccount"]["username"],
				password: $data["systemAccount"]["password"],
				nickname: $data["systemAccount"]["nickname"],
				type: UserType::ADMIN
			);
		}
		catch(InvalidArgumentException) {
			throw new InvalidConfigException("The system account for managed server \"$serverName\" is configured incorrectly");
		}
	}

	// Decrements the counter of instancies.
	public function __destruct() {
		static::$mNumberOfInstancies--;
	}

	// Throws InvalidConfigException if the given Json instance cannot be used as a configuration source.
	public static function validate(Json $source): void {
		// Prepare data
		$assoc = $source->get();
		$servers = @$assoc["servers"];
		$validation = @$assoc["validation"];
		$smtp = @$assoc["smtp"];
		// Check managed servers
		if(!is_array($servers)) {
			throw new InvalidConfigException($source->filename, "there are no managed servers");
		}
		foreach($servers as $serverName => $server) {
			$account = @$server["systemAccount"];
			$premod = @$server["premod"];
			$moderators = @$premod["moderators"];
			if(
				// Basic server properties
				!is_string(@$server["title"]) or
				!is_string(@$server["host"]) or
				!is_int(@$server["port"]) or
				// System account
				!is_array($account) or
				!is_string(@$account["username"]) or
				!is_string(@$account["password"]) or
				!is_string(@$account["nickname"]) or
				// Basic premoderation settings
				!is_array($premod) or
				!is_bool(@$premod["enabled"])
			) {
				throw new InvalidConfigException($source->filename, "something is bad about one of managed servers");
			}
			// Check moderators if necessary
			if($premod["enabled"]) {
				if(!is_array($moderators)) {
					throw new InvalidConfigException($source->filename, "the server \"$serverName\" is moderated, but no moderators is configured for it");
				}
				foreach($moderators as $moderator) {
					if(
						!is_array($moderator) or
						!is_string(@$moderator["email"]) or
						!is_string(@$moderator["locale"])
					) {
						throw new InvalidConfigException($source->filename, "one of moderators for the server \"$serverName\" is invalid");
					}
				}
			}
		}
		// Check validation settings
		if(!is_array($validation) and $validation !== null) {
			throw new InvalidConfigException($source->filename, "validation rules must be encapsulated into a JSON object");
		}
		// Check SMTP settings
		if(
			!is_array($smtp) or
			!is_string(@$smtp["username"]) or
			!is_string(@$smtp["password"])
		) {
			throw new InvalidConfigException($source->filename, "something is wrong with SMTP settings");
		}
	}

}
