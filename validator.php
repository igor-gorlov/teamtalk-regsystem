<?php


/*
User input validation.
© Igor Gorlov, 2022.
*/


declare(strict_types=1);


// Validates a username.
function isValidUsername($str)
{
	if(strlen($str)>0)
	{
		return true;
	}
}

// Validates a password.
function isValidPassword($str)
{
	if(strlen($str)>0)
	{
		return true;
	}
}


?>
