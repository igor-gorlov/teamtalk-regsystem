<?php


/*
An object-oriented API for reading, writing, and manipulating JSON.
Built on top of the standard PHP functions related to JSON and filesystem.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


// Is thrown on a problem with JSON.
class InvalidJsonException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

// A generic container for JSON loaded from a file.
class Json {

	public const MAX_DEPTH = 2147483646;

	private $mFile;
	private array $mJson;
	private bool $mIsModified;

	/*
	Loads and parses the JSON file denoted by the given name.
	Throws RuntimeException when the file cannot be read or when it contains syntactic errors.

	If $autosave optional argument is set to true,
	the updated information will be stored back to the file on destruction of the current JSON instance.
	*/
	public function __construct(string $filename, public readonly bool $autosave = true) {
		$file = fopen($filename, "r+");
		if($file === false) {
			throw new RuntimeException("Unable to open JSON file \"$filename\"");
		}
		$json = stream_get_contents($file);
		if($json === false) {
			throw new RuntimeException("Unable to read JSON file \"$filename\"");
		}
		$assoc = json_decode($json, true, self::MAX_DEPTH);
		if($assoc === null) {
			throw new RuntimeException("Invalid syntax of JSON file \"$filename\"");
		}
		$this->mJson = $assoc;
		$this->mFile = $file;
		$this->mIsModified = false;
	}

	// Checks whether the given string is a valid JSON path.
	public static function isValidPath(string $str): bool {
		return boolval(preg_match("/^[a-z0-9]+(\.[a-z0-9]+)*\$/i", $str));
	}

	/*
	Checks existence of the JSON entry pointed-to by the given path.
	Throws InvalidArgumentException if the path has incorrect format.
	*/
	public function exists(string $path): bool {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid JSON path");
		}
		$indices = static::mTranslatePath($path, -1);
		$keys = static::splitPath($path);
		$lastKey = array_pop($keys);
		$code = "return is_array(@\$this->mJson$indices) and array_key_exists(\"$lastKey\", \$this->mJson$indices);";
		return eval($code);
	}

	/*
	Splits a string representing a JSON path into individual keys.
	Throws InvalidArgumentException if the path is incorrect.
	*/
	public static function splitPath(string $path): array {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid JSON path");
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
	especially when access to a JSON entry is required.

	This method throws InvalidArgumentException if the given path has incorrect format.
	*/
	private static function mTranslatePath(string $path, int $offset = 0): string {
		if(!static::isValidPath($path)) {
			throw new InvalidArgumentException("Invalid JSON path");
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
	Returns the value of the JSON entry pointed-to by the given path.
	Throws InvalidArgumentException if the given path is incorrect.
	*/
	public function get(string $path): mixed {
		$indices = static::mTranslatePath($path);
		if(!$this->exists($path)) {
			throw new InvalidArgumentException("JSON entry \"$path\" does not exist");
		}
		$code = "return \$this->mJson$indices;";
		return eval($code);
	}

	/*
	Assigns a value (passed as the second argument) to an entry pointed-to by a path (passed as the first argument).
	Returns the assigned value.

	If the requested entry does not exist, the method will try to create it silently;
	InvalidJsonException will be thrown on failure.
	
	An incorrect JSON path given to this method results in InvalidArgumentException being thrown.
	*/
	public function set(string $path, object|array|string|int|float|bool|null $value): mixed {
		$code = "return \$this->mJson" . static::mTranslatePath($path) . " = \$value;";
		$assigned = null;
		try {
			$assigned = eval($code);
		}
		catch(Error) {
			throw new InvalidJsonException(
				"Unable to set JSON entry \"$path\": this path cannot be created"
			);
		}
		$this->mIsModified = true;
		return $assigned;
	}

	/*
	Deletes the entry pointed-to by the given path, returns the deleted value.

	If the requested entry does not exist, throws InvalidJsonException;
	but if the passed string cannot be used as a path at all, this function throws InvalidArgumentException.
	*/
	public function unset(string $path): mixed {
		if(!$this->exists($path)) {
			throw new InvalidJsonException(
				"Unable to remove JSON entry \"$path\": this path does not exist"
			);
		}
		$access = "\$this->mJson" . static::mTranslatePath($path);
		$deleted = eval("return $access;");
		eval("unset($access);");
		$this->mIsModified = true;
		return $deleted;
	}

	/*
	Stores JSON code back to the file. If none of the options was modified, does nothing.
	Throws RuntimeException when cannot write data.
	*/
	public function save(): void {
		if(!$this->mIsModified) {
			return;
		}
		$json = json_encode(
			$this->mJson,
			JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION |
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		) . "\n";
		ftruncate($this->mFile, 0);
		rewind($this->mFile);
		if(fwrite($this->mFile, $json) === false) {
			throw new RuntimeException("Unable to save JSON to the file");
		}
		$this->mIsModified = false;
	}

	/*
	Automatically writes JSON to the file if it was modified since construction of the object
	or since the last call to save() (provided that any such calls have took place).
	If none of the entries was modified, does nothing.
	
	Throws RuntimeException when cannot write data.
	*/
	public function __destruct() {
		if($this->autosave) {
			$this->save();
		}
	}

}