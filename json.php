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

// Represents a valid path to a JSON entry.
class JsonPath {

	private array $mPath;

	/*
	A JsonPath instance can be constructed either from an array of strings (so-called "full notation")
	or from a single string (referred to as "short notation").

	Both these notations are designed to hold a set of JSON keys
	that should be applied sequentially in order to reach some entry. Full notation is simply an array of keys;
	short notation compresses keys into one single string where they are separated by dot characters (.).

	Despite that the latter approach is very intuitive and seems to be more effective, it has important limitations:
	only ASCII letters (A-Z, a-z), digits (0-9), and underscores (_) are allowed within a key
	when short notation is used; while full notation sets no restrictions (except of those enforced by PHP).

	The constructor throws InvalidArgumentException if the given path is incorrect.
	*/
	public function __construct(string|array $notation) {
		if(!static::isValidNotation($notation)) {
			throw new InvalidArgumentException("Invalid JSON path");
		}
		if(!is_array($notation)) {
			$notation = static::toFullNotation($notation);
		}
		$this->mPath = $notation;
	}

	// Checks if the given entity is a valid JSON path notation.
	public static function isValidNotation(mixed $entity): bool {
		if(!is_string($entity) and !is_array($entity)) {
			return false;
		}
		if(is_array($entity)) {
			foreach($entity as $i) {
				if(!is_string($i) and !is_int($i)) {
					return false;
				}
			}
			return true;
		}
		return boolval(preg_match("/^[a-z0-9_]+(\.[a-z0-9_]+)*\$/i", $entity));
	}

	/*
	Converts the given path in short notation to an equivalent path in full notation.
	Throws InvalidArgumentException if the path is incorrect.
	*/
	public static function toFullNotation(string $path): array {
		if(!static::isValidNotation($path)) {
			throw new InvalidArgumentException("Invalid JSON path");
		}
		return explode(".", $path);
	}

	/*
	Converts the underlying path to a sequence of array indices.
	For example, short notation "servers.default.host" is translated to "[\"servers\"][\"default\"][\"host\"]".

	The optional argument $offset determins how many indices should be trimmed from the final output.
	If the value is positive, first $offset indices are skipped;
	if it is negative, the same is done for last abs($offset) elements.
	When abs($offset) is not less than total number of indices, an empty string is returned.

	The output may be used to construct a string of code for eval(),
	especially when access to a JSON entry is required.
	*/
	public function toIndices(int $offset = 0): string {
		$code = "";
		$keys = $this->mPath;
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

	// Returns the underlying path in full notation.
	public function getFullNotation(): array {
		return $this->mPath;
	}

	// Returns true if the underlying path can be represented in short notation; returns false otherwise.
	public function hasShortNotation(): bool {
		$short = implode(".", $this->mPath);
		return static::isValidNotation($short);
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
