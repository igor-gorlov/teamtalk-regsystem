<?php


/*
Gives the ability to load, review, manipulate and store program settings.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "json.php";
require_once "server.php";


// Is thrown on a problem with configuration.
class InvalidConfigException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

// Provides an interface to configuration.
class Configurator {

	private Json $mSource;

	// Loads configuration from the given source.
	public function __construct(Json $source) {
		$this->mSource = $source;
	}

	/*
	Returns a set of validation rules (as required by Validator class).
	If no validation rules are configured, or if `validation` JSON object is absent, an empty array is returned.
	*/
	public function getValidationRules(): array {
		if(!$this->mSource->exists("validation") or count($this->mSource->get("validation")) == 0) {
			return array();
		}
		return $this->mSource->get("validation");
	}

	/*
	Returns a ServerInfo instance which describes the managed server pointed-to by the given name.
	Throws InvalidArgumentException if there is no server with such name.
	*/
	public function getServerInfo(string $name): ServerInfo {
		if(!$this->mSource->exists("servers.$name")) {
			throw new InvalidArgumentException("No server named \"$name\" is configured");
		}
		$data = $this->mSource->get("servers.$name");
		$validator = new Validator;
		if($this->mSource->exists("validation")) {
			$validator->setRules($this->mSource->get("validation"));
		}
		return new ServerInfo(
			validator: $validator,
			name: $name,
			title: $data["title"],
			host: $data["host"],
			port: $data["port"]
		);
	}

	/*
	Returns an array of ServerInfo objects describing all managed servers currently configured.
	If there are no servers, or the configuration does not contain `servers` object at all,
	the returned array is empty.
	*/
	public function getAllServersInfo(): array {
		if(!$this->mSource->exists("servers")) {
			return array();
		}
		$names = array_keys($this->mSource->get("servers"));
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
	throws InvalidConfigException if a system account is configured incorrectly
	or is not configured for this server at all.
	*/
	public function getSystemAccountInfo(string $serverName): UserInfo {
		if(!$this->mSource->exists("servers.$serverName")) {
			throw new InvalidArgumentException("No server named \"$serverName\" is configured");
		}
		if(!$this->mSource->exists("servers.$serverName.systemAccount")) {
			throw new InvalidConfigException("No system account is configured for server named \"$serverName\"");
		}
		$data = $this->mSource->get("servers.$serverName.systemAccount");
		$validator = new Validator;
		if($this->mSource->exists("validation")) {
			$validator->setRules($this->mSource->get("validation"));
		}
		try {
			return new UserInfo(
				validator: $validator,
				username: $data["username"],
				password: $data["password"],
				nickname: $data["nickname"]
			);
		}
		catch(InvalidArgumentException) {
			throw new InvalidConfigException("The system account for managed server \"$serverName\" is configured incorrectly");
		}
	}

}
