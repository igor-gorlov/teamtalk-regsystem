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
