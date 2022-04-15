<?php


/*
This script accepts registration data and creates a new TeamTalk 5 account from it.
© Igor Gorlov, 2022.
*/


// Configure the essential options.
$host = "localhost"; // TeamTalk 5 server address.
$port = "10333"; // TeamTalk 5 TCP port.
$systemUsername = "regsystem"; /* The name of a registered admin account which will be used for all operations
                                   involving the server (so-called "the system account"). */
$systemPassword = "qwerty123456"; // The password of the system account.
$systemNickname = "Registration System"; // The system account's nickname.


// Functions.

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
