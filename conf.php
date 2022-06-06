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

// Provides an interface to configuration stored in a file. Cannot be instantiated.
class Config {

	public const MAX_DEPTH = 2147483646;

	// The default configuration for optional entries.
	public const DEFAULT = array(
		"validation" => array(
			"username" => "/.+/i",
			"password" => "/.+/i"
		)
	);

	private static $mFile;
	private static array $mConf;
	private static bool $mIsModified;

	/*
	Check whether all mandatory entries are present and have the required types;
	throws InvalidConfigException if that is not the case.
	*/
	private static function mCheckMandatoryEntries(): void {
		// Managed servers.
		if(
			!isset(static::$mConf["servers"]) or
			!is_array(static::$mConf["servers"]) or
			empty(static::$mConf["servers"])
		) {
			throw new InvalidConfigException("There are no managed servers or they are incorrectly configured");
		}
		foreach(static::$mConf["servers"] as $server) {
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

	/*
	Loads configuration from the given file;
	registers save() method as a shutdown function if the optional argument autosave is set to true.

	Throws RuntimeException when the file cannot be read or when it contains syntactic errors;
	throws InvalidConfigException if one or more mandatory configuration options are missing or have unexpected types.

	This method must be called first of all and only once; BadMethodCallException will be thrown on subsequent calls.
	*/
	public static function init(string $filename, bool $autosave = true): void {
		static $hasBeenCalled = false;
		if($hasBeenCalled == true) {
			throw new BadMethodCallException("Unneeded call to Config::init()");
		}
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
		static::$mConf = $assoc;
		static::mCheckMandatoryEntries();
		static::$mFile = $file;
		static::$mIsModified = false;
		if($autosave) {
			register_shutdown_function("Config::save");
		}
		$hasBeenCalled = true;
	}

	/*
	Translates the given path from a sequence of dot-separated keys
	to a valid PHP code which accesses the target configuration entry.
	*/
	private static function translatePath(string $path): string {
		$code = "static::\$mConf";
		$keys = explode(".", $path);
		foreach($keys as $key) {
			$code .= "[\"$key\"]";
		}
		return $code;
	}

	/*
	Returns the value of the configuration option pointed by the given path.
	If this option does not exist, returns null.
	*/
	public static function get(string $path): mixed {
		$code = "return " . static::translatePath($path) . ";";
		return @eval($code);
	}

	/*
	Assigns a value (passed as the second argument) to an entry pointed by a path (passed as the first argument).
	Returns the assigned value on success or null on failure.

	Caution!
	This method may return boolean false after a successfull assignment if you pass that boolean false as the value.
	Compare the result against null using === operator to determine whether the assignment has failed.

	If the requested entry does not exist, the method will create it silently.
	
	The value can be of any type except of null and resource.
	*/
	public static function set(string $path, object|array|string|int|float|bool $value): mixed {
		$code = "return " . static::translatePath($path) . " = \$value;";
		static::$mIsModified = true;
		try {
			return eval($code);
		}
		catch(Error $e) {
			return null;
		}
	}

	/*
	Deletes the entry pointed-to by the given path; returns the deleted value on success or null on failure.

	Caution!
	This method may return boolean false after a successfull deletion if the value being removed is identical to false.
	Compare the result against null using === operator to determine whether the deletion has failed.
	*/
	public static function unset(string $path): mixed {
		$access = static::translatePath($path);
		$code = "
			if((\$deleted = @$access) === null) {
				return null;
			}
			unset($access);
			static::\$mIsModified = true;
			return \$deleted;
		";
		return eval($code);
	}

	/*
	Stores configuration back to the file. If none of the options was modified, does nothing.
	Throws RuntimeException when cannot write data.
	*/
	public static function save(): void {
		if(!static::$mIsModified) {
			return;
		}
		$json = json_encode(
			static::$mConf, JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR
		) . "\n";
		ftruncate(static::$mFile, 0);
		rewind(static::$mFile);
		if(fwrite(static::$mFile, $json) === false) {
			throw new RuntimeException("Unable to save configuration to the file");
		}
	}
	
}


?>
