<?php


/*
Gives the ability to load, review, manipulate and store program settings.
© Igor Gorlov, 2022.
*/


declare(strict_types=1);


// Provides an interface to configuration stored in a file. Cannot be instantiated.
class Config
{

	private static array $mConf;

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
		$json = file_get_contents($filename);
		if($json === false)
		{
			throw new RuntimeException("Unable to read configuration file \"$filename\"");
		}
		static::$mConf = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
	}

	/*
	Returns true if the configuration option pointed by the given key exists, otherwise returns false.
	Just like in arrays, the key must be either a string or an integer.
	If the option you are looking for is nested, pass multiple keys.
	The option value must be of a scalar type: you can test neither for an array nor for an object,
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
	Returns a value of the configuration option pointed by the given key. If this option does not exist, returns null.
	Just like in arrays, the key must be either a string or an integer.
	If the option you are looking for is nested, pass multiple keys.
	The option value must be of a scalar type: you can request neither an array nor an object,
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

}


?>
