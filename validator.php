<?php


/*
Unreliable data can be safely verified using algorithms defined in this file.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


/*
A generic class that validates everything.

Each individual check is implemented as a distinct method accepting the entity being validated
and returning boolean result.

Names of validation methods are constructed according to the following pattern: `isValid<Subject>`.
For example, a method that checks passwords has name `isValidPassword`.

Validation methods never modify the passed entity (even if it is passed by reference).
Also, they never change the internal state of the class or internal state of its current instance
and have no other side effects.

The behavior of any validation method depends only and only on two factors:
	1. The accepted entity.
	2. The underlying set of validation rules stored within the current class instance.
*/
class Validator {

	/*
	The constructor accepts an optional set of validation rules.
	This is an associative array, where each key denotes an entity type (rule name),
	and the corresponding value establishes requirements and constraints that are to be met (rule body).

	Each particular validation method uses its own rule name and its own format of rule body;
	both are described in header comment of the method.

	If a rule with an appropriate name is absent, a method which uses that rule may apply hardcoded defaults.
	*/
	public function __construct(private array $mRules = array()) {}

	// Overrides the current validation rules.
	public function setRules(array $rules): void {
		$this->mRules = $rules;
	}

	// Returns the current validation rules.
	public function getRules(): array {
		return $this->mRules;
	}

	/*
	Validates a username against the configured regular expression;
	if no regexp is configured, the following is used: "/.+/i".

	If an error occurres during validation process, the method throws RuntimeException.
	*/
	public function isValidUsername(mixed $entity): bool {
		if(!is_string($entity)) {
			return false;
		}
		$regexp = "";
		if(!array_key_exists("username", $this->mRules)) {
			$regexp = "/.+/i";
		}
		else {
			$regexp = $this->mRules["username"];
		}
		$result = @preg_match($regexp, $entity);
		if($result === false) {
			throw new RuntimeException("Unable to validate a username");
		}
		return boolval($result);
	}

	/*
	Validates a password against the configured regular expression;
	if no regexp is configured, the following is used: "/.+/i".

	If an error occurres during validation process, the method throws RuntimeException.
	*/
	public function isValidPassword(mixed $entity): bool {
		if(!is_string($entity)) {
			return false;
		}
		$regexp = "";
		if(!array_key_exists("password", $this->mRules)) {
			$regexp = "/.+/i";
		}
		else {
			$regexp = $this->mRules["password"];
		}
		$result = @preg_match($regexp, $entity);
		if($result === false) {
			throw new RuntimeException("Unable to validate a password");
		}
		return boolval($result);
	}

	// Validates a nickname.
	public function isValidNickname(mixed $entity): bool {
		return is_string($entity);
	}

}
