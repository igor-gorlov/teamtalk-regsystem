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
	Check configuration invariants; throws InvalidConfigException if something is violated.

	By default, this function works with static::$mConf,
	but another configuration source can be supplied via $conf optional argument.
	*/
	private static function mValidate(?array &$conf = null): void {
		// Determine the configuration source.
		$source = array();
		if($conf === null) {
			$source = &static::$mConf;
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

	/*
	Checks whether the entry pointed-to by the given path had come from the configuration file
	(but not from the array of defaults).
	*/
	public static function isLoaded(string $path): bool {
		$indices = static::translatePath($path);
		$code = "return isset(static::\$mConf$indices);";
		return eval($code);
	}

	// Checks whether the configuration entry pointed-to by the given path has a default value.
	public static function hasDefaultValue(string $path): bool {
		$indices = static::translatePath($path);
		$code = "return isset(static::DEFAULT$indices);";
		return eval($code);
	}

	// Checks existence of the configuration entry pointed-to by the given path.
	public static function exists(string $path): bool {
		$indices = static::translatePath($path);
		$code = "return isset(static::\$mConf$indices);";
		return eval($code);
	}

	/*
	Returns true if the entry pointed-to by the given path is mandatory;
	returns false when that is not the case or when this entry does not exist at all.
	*/
	public static function isMandatory(string $path): bool {
		// Copy the configuration to a local variable.
		$testBench = static::$mConf;
		// Try to delete a copy of the requested entry.
		$indices = static::translatePath($path);
		$code = "
			if(@\$testBench$indices === null) {
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
			static::mValidate($testBench);
		}
		catch(Exception) { // a problem, the deleted entry was mandatory!
			return true;
		}
		return false;
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
		static::mValidate($assoc);
		static::$mConf = $assoc;
		static::$mFile = $file;
		static::$mIsModified = false;
		if($autosave) {
			register_shutdown_function("Config::save");
		}
		$hasBeenCalled = true;
	}

	/*
	Converts the given path to a sequence of array indices,
	so that "servers.default.host" is translated to "[\"servers\"][\"default\"][\"host\"]".

	The output may be used to construct a string of code for eval(),
	especially when access to a configuration entry is required.
	*/
	private static function translatePath(string $path): string {
		$code = "";
		$keys = explode(".", $path);
		foreach($keys as $key) {
			$code .= "[\"$key\"]";
		}
		return $code;
	}

	/*
	Returns the value of the configuration entry pointed by the given path.
	If this entry does not exist in the loaded configuration, returns its default value.
	If there is no default value for this entry, returns null.
	*/
	public static function get(string $path): mixed {
		$indices = static::translatePath($path);
		$code = "return static::\$mConf$indices;";
		if(($result = @eval($code)) !== null) {
			return $result;
		}
		$code = "return static::DEFAULT$indices;";
		return @eval($code);
	}

	/*
	Assigns a value (passed as the second argument) to an entry pointed-to by a path (passed as the first argument).
	Returns the assigned value, which can be of any type except of null and resource.

	If the requested entry does not exist, the method will try to create it silently;
	InvalidConfigException will be thrown on failure.
	
	If the assignment operation breaks configuration validity,
	an instance of InvalidConfigException is thrown and no changes are applied.
	*/
	public static function set(string $path, object|array|string|int|float|bool $value): mixed {
		// Try to perform the operation on a local configuration copy.
		$virtualConf = static::$mConf;
		$code = "return \$virtualConf" . static::translatePath($path) . " = \$value;";
		$assigned = null;
		try {
			$assigned = eval($code);
		}
		catch(Error) {
			$assigned = null;
		}
		if($assigned === null) {
			throw new InvalidConfigException(
				"Unable to set configuration entry \"$path\": this path cannot be created"
			);
		}
		// The virtual operation succeeded, now apply the new configuration if it is valid.
		try {
			static::mValidate($virtualConf);
		}
		catch(InvalidConfigException $e) {
			throw new InvalidConfigException(
				"Unable to set configuration entry \"$path\"; you would get the following on success:\n\t" .
				$e->getMessage()
			);
		}
		static::$mConf = $virtualConf;
		static::$mIsModified = true;
		return $assigned;
	}

	/*
	Deletes the entry pointed-to by the given path, returns the deleted value;
	throws InvalidConfigException if an error occurres.

	Sometimes, an optional entry may persist even after removal:
	if it has a default value, this value will still be accessible.
	*/
	public static function unset(string $path): mixed {
		if(static::isMandatory($path)) {
			throw new InvalidConfigException("Unable to remove mandatory configuration option \"$path\"");
		}
		$access = "static::\$mConf" . static::translatePath($path);
		$deleted = null;
		$code = "
			if((\$deleted = @$access) === null) {
				return null;
			}
			unset($access);
			return \$deleted;
		";
		if(eval($code) === null) {
			throw new InvalidConfigException(
				"Unable to remove configuration entry \"$path\": this path does not exist"
			);
		}
		static::$mIsModified = true;
		return $deleted;
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
