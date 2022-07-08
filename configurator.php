<?php


/*
Gives the ability to load, review, manipulate and store program settings.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


// Is thrown on a problem with mandatory configuration entries.
class InvalidConfigException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

// Provides an interface to configuration stored in a file.
class Configurator {

	public const MAX_DEPTH = 2147483646;

	private $mFile;
	private array $mConf;
	private bool $mIsModified;

	/*
	Loads configuration from the given file.
	Throws RuntimeException when the file cannot be read or when it contains syntactic errors.

	If $autosave optional argument is set to true,
	the updated configuration will be stored back to the file on destruction of the current Configurator instance.
	*/
	public function __construct(string $filename, public readonly bool $autosave = true) {
		$file = fopen($filename, "r+");
		if($file === false) {
			throw new RuntimeException("Unable to open configuration file \"$filename\"");
		}
		$json = stream_get_contents($file);
		if($json === false) {
			throw new RuntimeException("Unable to read configuration file \"$filename\"");
		}
		$assoc = json_decode($json, true, self::MAX_DEPTH);
		if($assoc === null) {
			throw new RuntimeException("Invalid syntax of configuration file \"$filename\"");
		}
		$this->mConf = $assoc;
		$this->mFile = $file;
		$this->mIsModified = false;
	}

	// Checks whether the given string is a valid configuration path.
	public static function isValidPath(string $str): bool {
		return boolval(preg_match("/^[a-z0-9]+(\.[a-z0-9]+)*\$/i", $str));
	}

	/*
	Checks existence of the configuration entry pointed-to by the given path.
	Throws InvalidArgumentException if the path has incorrect format.
	*/
	public function exists(string $path): bool {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid configuration path");
		}
		$indices = static::mTranslatePath($path, -1);
		$keys = static::splitPath($path);
		$lastKey = array_pop($keys);
		$code = "return is_array(@\$this->mConf$indices) and array_key_exists(\"$lastKey\", \$this->mConf$indices);";
		return eval($code);
	}

	/*
	Splits a string representing a configuration path into individual keys.
	Throws InvalidArgumentException if the path is incorrect.
	*/
	public static function splitPath(string $path): array {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid configuration path");
		}
		return explode(".", $path);
	}

	/*
	Converts the given path to a sequence of array indices,
	so that "servers.default.host" is translated to "[\"servers\"][\"default\"][\"host\"]".

	The optional argument $offset determins how many indices should be trimmed from the final output.
	If the value is positive, first $offset indices are skipped;
	if it is negative, the same is done for last abs($offset) elements.
	When abs($offset) is not less than total number of indices, an empty string is returned.

	The output may be used to construct a string of code for eval(),
	especially when access to a configuration entry is required.

	This method throws InvalidArgumentException if the given path has incorrect format.
	*/
	private static function mTranslatePath(string $path, int $offset = 0): string {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid configuration path");
		}
		$code = "";
		$keys = static::splitPath($path);
		if(abs($offset) >= count($keys)) {
			return "";
		}
		if($offset > 0) {
			$keys = array_slice($keys, $offset);
		}
		elseif($offset < 0) {
			$keys = array_slice($keys, 0, $offset);
		}
		foreach($keys as $key) {
			$code .= "[\"$key\"]";
		}
		return $code;
	}

	/*
	Returns the value of the configuration entry pointed-to by the given path.
	Throws InvalidArgumentException if the given path is incorrect.
	*/
	public function get(string $path): mixed {
		$indices = static::mTranslatePath($path);
		if(!$this->exists($path)) {
			throw new InvalidConfigException("Configuration entry \"$path\" does not exist");
		}
		$code = "return \$this->mConf$indices;";
		return eval($code);
	}

	/*
	Assigns a value (passed as the second argument) to an entry pointed-to by a path (passed as the first argument).
	Returns the assigned value.

	If the requested entry does not exist, the method will try to create it silently;
	InvalidConfigException will be thrown on failure.
	
	An incorrect configuration path given to this method results in InvalidArgumentException being thrown.
	*/
	public function set(string $path, object|array|string|int|float|bool|null $value): mixed {
		$code = "return \$this->mConf" . static::mTranslatePath($path) . " = \$value;";
		$assigned = null;
		try {
			$assigned = eval($code);
		}
		catch(Error) {
			throw new InvalidConfigException(
				"Unable to set configuration entry \"$path\": this path cannot be created"
			);
		}
		$this->mIsModified = true;
		return $assigned;
	}

	/*
	Deletes the entry pointed-to by the given path, returns the deleted value.

	If the requested entry does not exist, throws InvalidConfigException;
	but if the passed string cannot be used as a path at all, this function throws InvalidArgumentException.
	*/
	public function unset(string $path): mixed {
		if(!$this->exists($path)) {
			throw new InvalidConfigException(
				"Unable to remove configuration entry \"$path\": this path does not exist"
			);
		}
		$access = "\$this->mConf" . static::mTranslatePath($path);
		$deleted = eval("return $access;");
		eval("unset($access);");
		$this->mIsModified = true;
		return $deleted;
	}

	/*
	Stores configuration back to the file. If none of the options was modified, does nothing.
	Throws RuntimeException when cannot write data.
	*/
	public function save(): void {
		if(!$this->mIsModified) {
			return;
		}
		$json = json_encode(
			$this->mConf,
			JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION |
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		) . "\n";
		ftruncate($this->mFile, 0);
		rewind($this->mFile);
		if(fwrite($this->mFile, $json) === false) {
			throw new RuntimeException("Unable to save configuration to the file");
		}
		$this->mIsModified = false;
	}

	/*
	Returns a ServerInfo instance which describes the managed server pointed-to by the given name.
	Throws InvalidArgumentException if there is no server with such name.
	*/
	public function getServerInfo(string $name): ServerInfo {
		if(!$this->exists("servers.$name")) {
			throw new InvalidArgumentException("No server named \"$name\" is configured");
		}
		$data = $this->get("servers.$name");
		return new ServerInfo(
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
		if(!$this->exists("servers")) {
			return array();
		}
		$names = array_keys($this->get("servers"));
		$result = array();
		foreach($names as $name) {
			$result[] = $this->getServerInfo($name);
		}
		return $result;
	}

	/*
	Automatically writes configuration to the file if it was modified since construction of the object
	or since the last call to save() (provided that any such calls have took place).
	If none of the options was modified, does nothing.
	
	Throws RuntimeException when cannot write data.
	*/
	public function __destruct() {
		if($this->autosave) {
			$this->save();
		}
	}

}
