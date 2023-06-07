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
	Throws BadMethodCallException if another object of type Configurator already exists.
	*/
	public function __construct(Json $source) {
		if(static::$mNumberOfInstancies == static::MAX_NUMBER_OF_INSTANCIES) {
			throw new BadMethodCallException("Unable to construct a Configurator object: the maximum number of instancies is " . static::MAX_NUMBER_OF_INSTANCIES);
		}
		$this->mSource = $source;
		static::$mNumberOfInstancies++;
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

	Throws InvalidArgumentException if there is no server with such name.
	*/
	public function getSystemAccountInfo(string $serverName): UserInfo {
		if(!$this->mSource->exists(new JsonPath("servers", $serverName))) {
			throw new InvalidArgumentException("No server named \"$serverName\" is configured");
		}
		$data = $this->mSource->get(new JsonPath("servers", $serverName));
		return new UserInfo(
			server: $this->getServerInfo($serverName),
			username: $data["systemAccount"]["username"],
			password: $data["systemAccount"]["password"],
			nickname: $data["systemAccount"]["nickname"],
			type: UserType::ADMIN
		);
	}

	// Decrements the counter of instancies.
	public function __destruct() {
		static::$mNumberOfInstancies--;
	}

}
