<?php


/*
A language pack's manager built on top of the native PHP internationalization system.

For code in this file to work properly, PHP builtin extention Intl must have exception support enabled.
You can switch it on using `intl.use_exceptions` PHP configuration setting;
the latter can be controlled directly from a script by calling ini_set() function.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "json.php";
require_once "validator.php";


// Represents a single language pack.
class LanguagePack {

	private Json $mSource;

	/*
	Accepts a standard locale string and a Json instance which contains messages
	translated to the language pointed-to by the locale.

	Throws InvalidArgumentException if the Json object cannot be used as a localization source.
	*/
	public function __construct(public readonly Validator $validator, public readonly string $locale, Json $source) {
		if(!$validator->isValidLocalization($source)) {
			throw new InvalidArgumentException("Invalid localization data");
		}
		$this->mSource = $source;
	}

	/*
	Preprocesses the message pointed-to by the given identifier using the given array of arguments,
	returns the resulting string.
	
	Throws InvalidArgumentException if there is no matching identifier in this language pack;
	throws IntlException in case of other problems.
	*/
	public function getMessage(string $id, array $args = array()): string {
		if(!$this->mSource->exists(new JsonPath($id))) {
			throw new InvalidArgumentException("Cannot find a message with identifier \"$id\" in $this->locale language pack");
		}
		return MessageFormatter::formatMessage($this->locale, $this->mSource->get(new JsonPath($id)), $args);
	}

}
