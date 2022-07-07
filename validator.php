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

The behaviour of any validation method depends only and only on two factors:
	1. The accepted entity.
	2. The underlying set of validation rules stored within the current class instance.
*/
class Validator {

	/*
	The constructor accepts a set of validation rules.
	This is an associative array, where each key denotes an entity type (rule name),
	and the corresponding value establishes requirements and constraints that are to be met (rule body).

	Each particular validation method uses its own rule name and its own format of the rule body;
	both are described in header comment of the method.
	*/
	public function __construct(private array $mRules) {}

}
