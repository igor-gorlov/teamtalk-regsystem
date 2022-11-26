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

	public readonly Validator $validator;

	private static int $mNumberOfInstancies = 0;

	private Json $mSource;

	/*
	Loads configuration from the given source;
	throws BadMethodCallException if another object of type Configurator already exists,
	throws InvalidArgumentException if the given JSON structure is not suitable for configuration purposes.

	ATTENTION! The given Validator object is modified during construction of the Configurator instance:
	it gets a set of rules found in the configuration source.
	*/
	public function __construct(Validator $validator, Json $source) {
		if(static::$mNumberOfInstancies == static::MAX_NUMBER_OF_INSTANCIES) {
			throw new BadMethodCallException("Unable to construct a Configurator object: the maximum number of instancies is " . static::MAX_NUMBER_OF_INSTANCIES);
		}
		if(!$validator->isValidConfiguration($source)) {
			throw new InvalidArgumentException("Invalid configuration file \"$source->filename\"");
		}
		$this->mSource = $source;
		$validator->setRules($this->getValidationRules());
		$this->validator = $validator;
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

}
