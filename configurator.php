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
	Returns a ServerInfo instance which describes the managed server pointed-to by the given address.
	Throws InvalidArgumentException if there is no such server.
	*/
	public function getServerInfo(Address $address): ServerInfo {
		if(!$this->mSource->exists(new JsonPath("servers", (string)$address))) {
			throw new InvalidArgumentException("Unknown server \"$address\"");
		}
		$data = $this->mSource->get(new JsonPath("servers", (string)$address));
		return new ServerInfo(
			address: $address,
			systemUsername: $data["systemUsername"],
			systemPassword: $data["systemPassword"],
			systemNickname: $data["systemNickname"]
		);
	}

	// Returns true when premoderation for the server with the given address is enabled, otherwise returns false.
	public function isPremoderatedServer(Address $address): bool {
		return $this->mSource->get(new JsonPath((string)$address, "premod", "enabled"));
	}

	/*
	Returns an array of ServerInfo objects describing all managed servers currently configured.
	If there are no servers, the returned array is empty.
	*/
	public function getAllServersInfo(): array {
		$addressesAsStrings = array_keys($this->mSource->get(new JsonPath("servers")));
		$result = array();
		foreach($addressesAsStrings as $addressAsString) {
			$result[] = $this->getServerInfo(Address::fromString($addressAsString));
		}
		return $result;
	}

	// Decrements the counter of instancies.
	public function __destruct() {
		static::$mNumberOfInstancies--;
	}

}
