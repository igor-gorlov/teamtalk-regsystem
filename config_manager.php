<?php


/*
Gives the ability to load, review, manipulate and store program settings.

© Igor Gorlov, 2022.
*/


declare(strict_types=1);


// Provides an interface to configuration stored in a file. Cannot be instantiated.
class Config
{

	private static $mFile;
	private static array $mConf;
	private static bool $mIsModified;

	/*
	Loads configuration from the given file.
	Throws RuntimeException when cannot read the file specified or JsonException if the file contains syntactic errors.

	This method must be called first of all and only once; BadMethodCallException will be thrown on subsequent calls.
	*/
	public static function init(string $filename): void
	{
		static $hasBeenCalled = false;
		if($hasBeenCalled == true)
		{
			throw new BadMethodCallException("Unneeded call to Config::init()");
		}
		$hasBeenCalled = true;
		$file = fopen($filename, "r+");
		if($file === false)
		{
			throw new RuntimeException("Unable to open configuration file \"$filename\"");
		}
		$json = stream_get_contents($file);
		if($json === false)
		{
			throw new RuntimeException("Unable to read configuration file \"$filename\"");
		}
		static::$mConf = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		static::$mFile = $file;
		static::$mIsModified = false;
	}

	/*
	Returns true if the configuration option pointed by the given sequence of keys exists, otherwise returns false.

	Just like in arrays, any key must be either a string or an integer.

	The option value must be of a nullable scalar type: you can test neither for an array nor for an object,
	but only for an individual item of an array or for an individual object field.
	The method will return false if the path determined by the given key(s) ends with an object or with an array.
	*/
	public static function isDefined(string|int $firstKey, string|int ...$keys): bool
	{
		if(!array_key_exists($firstKey, static::$mConf))
		{
			return false;
		}
		$value = static::$mConf[$firstKey];
		foreach($keys as $key)
		{
			if(!is_array($value) or !array_key_exists($key, $value))
			{
				return false;
			}
			$value = $value[$key];
		}
		if(is_array($value))
		{
			return false;
		}
		return true;
	}

	/*
	Returns a value of the configuration option pointed by the given sequence of keys.
	If this option does not exist, returns null.

	Just like in arrays, any key must be either a string or an integer.

	The option value must be of a nullable scalar type: you can request neither an array nor an object,
	but only an individual item of an array or an individual object field.

	Please note: using this function, it is impossible to distinguish an existing option set to null
	from an option that is not present in the configuration file at all.
	You can test whether an option exists with isDefined() method.
	*/
	public static function get(string|int $firstKey, string|int ...$keys): string|bool|int|float|null
	{
		if(!array_key_exists($firstKey, static::$mConf))
		{
			return null;
		}
		$value = static::$mConf[$firstKey];
		foreach($keys as $key)
		{
			if(!is_array($value) or !array_key_exists($key, $value))
			{
				return null;
			}
			$value = $value[$key];
		}
		return $value;
	}

	/*
	Assigns the value (passed as the last argument)
	to the option pointed by a key or a sequence of keys (passed via all preceding arguments).
	Returns the assigned value.

	If this option does not exist, the method will try to create it silently;
	InvalidArgumentException will be thrown on failure.
	You can ensure beforehand whether a specific property is defined by calling to isDefined() method.

	Just like in arrays, all the keys must be either a string or an integer;
	InvalidArgumentException will be thrown if at least one key has another type.
	*/
	public static function set(string|int $arg1, string|bool|int|float|null $arg2, string|bool|int|float|null ...$args): string|bool|int|float|null
	{
		/*
		Handle a special case when only the mandatory arguments are given,
		so that $arg1 is the key and $arg2 is the value.
		*/
		if(count($args) == 0)
		{
			static::$mConf[$arg1] = $arg2;
			static::$mIsModified = true;
			return $arg2;
		}
		// Split the arguments into the value and the sequence of keys.
		$keys = $args;
		$value = array_pop($keys);
		array_unshift($keys, $arg1, $arg2);
		/*
		Check the key sequence:
			* Each key must be a string or an integer to meat the array requirements.
			* It is critical to prevent accidental modification or deletion of third-party nullable scalars,
				hence all values lying on the path determined by the keys must be either unset or an array,
				but the last value (which is to be written) must be either unset or a nullable scalar.
		*/
		$previousValue = static::$mConf;
		foreach($keys as $i => $key)
		{
			if(!is_string($key) and !is_int($key))
			{
				throw new InvalidArgumentException("Unable to set a configuration option");
			}
			if($i == count($keys)-1)
			{
				if(is_array($previousValue[$key]))
				{
					throw new InvalidArgumentException("Unable to set a configuration option");
				}
			}
			elseif(!is_array($previousValue[$key]))
			{
				throw new InvalidArgumentException("Unable to set a configuration option");
			}
			$previousValue = $previousValue[$key];
		}
		// All right, now set the value.
		$code = "static::\$mConf";
		foreach($keys as $key)
		{
			$code .= "[\"$key\"]";
		}
		$code .= " = \$value;";
		eval($code);
		static::$mIsModified = true;
		return $value;
	}

	/*
	Stores configuration back to the file. If none of the options was modified, does nothing.
	Throws RuntimeException when cannot write data or JsonException if there is a problem with configuration itself.
	*/
	public static function save(): void
	{
		if(!static::$mIsModified)
		{
			return;
		}
		$json = json_encode(static::$mConf, JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
		ftruncate(static::$mFile, 0);
		rewind(static::$mFile);
		if(fwrite(static::$mFile, $json) === false)
		{
			throw new RuntimeException("Unable to save configuration to the file");
		}
	}
	
}


?>
