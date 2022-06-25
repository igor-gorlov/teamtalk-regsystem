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
	Check configuration invariants; throws InvalidConfigException if something is violated.

	By default, this function works with $mConf,
	but another configuration source can be supplied via $conf optional argument.
	*/
	private function mValidate(?array &$conf = null): void {
		// Determine the configuration source.
		$source = array();
		if($conf === null) {
			$source = &$this->mConf;
		}
		else {
			$source = $conf;
		}
		// Check the managed servers.
		if(
			!isset($source["servers"]) or
			!is_array($source["servers"]) or
			empty($source["servers"])
		) {
			throw new InvalidConfigException("There are no managed servers or they are incorrectly configured");
		}
		foreach($source["servers"] as &$server) {
			if(
				!isset($server["host"]) or
				!is_string($server["host"]) or
				!isset($server["port"]) or
				!is_int($server["port"]) or
				!isset($server["systemAccount"]) or
				!isset($server["systemAccount"]["username"]) or
				!is_string($server["systemAccount"]["username"]) or
				!isset($server["systemAccount"]["password"]) or
				!is_string($server["systemAccount"]["password"]) or
				!isset($server["systemAccount"]["nickname"]) or
				!is_string($server["systemAccount"]["nickname"])
			) {
				throw new InvalidConfigException("One or more of managed servers are configured incorrectly");
			}
		}
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
	Returns true if the entry pointed-to by the given path is mandatory;
	returns false when that is not the case or when this entry does not exist at all.
	Throws InvalidArgumentException in case of incorrect path.
	*/
	public function isMandatory(string $path): bool {
		// Check existence of the entry.
		if(!$this->exists($path)) {
			return false;
		}
		// Copy the configuration to a local variable.
		$testBench = $this->mConf;
		// Try to delete a copy of the requested entry.
		$indices = static::mTranslatePath($path);
		$code = "
			if(!isset(\$testBench$indices)) {
				return false;
			}
			unset(\$testBench$indices);
			return true;
		";
		if(!eval($code)) { // deletion failed, the entry does not exist.
			return false;
		}
		// After the deletion, check whether the configuration in $testBench contains all mandatory entries.
		try {
			$this->mValidate($testBench);
		}
		catch(Exception) { // a problem, the deleted entry was mandatory!
			return true;
		}
		return false;
	}

	/*
	Loads configuration from the given file and validates it.

	Throws RuntimeException when the file cannot be read or when it contains syntactic errors;
	throws InvalidConfigException if one or more mandatory configuration options are missing or have unexpected types.

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
		$this->mValidate($assoc);
		$this->mConf = $assoc;
		$this->mFile = $file;
		$this->mIsModified = false;
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
	
	If the assignment operation breaks configuration validity,
	an instance of InvalidConfigException is thrown and no changes are applied.

	An incorrect configuration path given to this method results in InvalidArgumentException being thrown.
	*/
	public function set(string $path, object|array|string|int|float|bool|null $value): mixed {
		// Try to perform the operation on a local configuration copy.
		$code = "return \$virtualConf" . static::mTranslatePath($path) . " = \$value;";
		$virtualConf = $this->mConf;
		$assigned = null;
		try {
			$assigned = eval($code);
		}
		catch(Error) {
			throw new InvalidConfigException(
				"Unable to set configuration entry \"$path\": this path cannot be created"
			);
		}
		// The virtual operation succeeded, now apply the new configuration if it is valid.
		try {
			$this->mValidate($virtualConf);
		}
		catch(InvalidConfigException $e) {
			throw new InvalidConfigException(
				"Unable to set configuration entry \"$path\"; you would get the following on success:\n\t" .
				$e->getMessage()
			);
		}
		$this->mConf = $virtualConf;
		$this->mIsModified = true;
		return $assigned;
	}

	/*
	Deletes the entry pointed-to by the given path, returns the deleted value.

	If the requested entry does not exist or is mandatory, throws InvalidConfigException;
	but if the passed string cannot be used as a path at all, this function throws InvalidArgumentException.
	*/
	public function unset(string $path): mixed {
		if(!$this->exists($path)) {
			throw new InvalidConfigException(
				"Unable to remove configuration entry \"$path\": this path does not exist"
			);
		}
		if($this->isMandatory($path)) {
			throw new InvalidConfigException("Unable to remove mandatory configuration option \"$path\"");
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


?>
