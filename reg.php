<?php


/*
This script accepts registration data and creates a new TeamTalk 5 account from it.
© Igor Gorlov, 2022.
*/


require "server.php";


// Configure the essential options.
$host = "localhost"; // TeamTalk 5 server address.
$port = "10333"; // TeamTalk 5 TCP port.
$systemUsername = "regsystem"; /* The name of a registered admin account which will be used for all operations
                                   involving the server (so-called "the system account"). */
$systemPassword = "qwerty123456"; // The password of the system account.
$systemNickname = "Registration System"; // The system account's nickname.

// Get registration data.
$dataIsInvalid = false;
if(!isset($_GET["name"]) or !isValidUsername($_GET["name"]))
{
	echo "Error: invalid username!<br>";
	$dataIsInvalid = true;
}
if(!isset($_GET["password"]) or !isValidPassword($_GET["password"]))
{
	echo "Error: invalid password!<br>";
	$dataIsInvalid = true;
}
if($dataIsInvalid)
{
	exit("Operation failed.");
}
$newUsername = $_GET["name"];
$newPassword = $_GET["password"];

// Establish connection.
$socket = fsockopen($host, $port);
if(!$socket)
{
	exit("Failed to connect to server");
}

// Authorize under the system account.
$isAuthorized = executeCommand(
	"login username=\"$systemUsername\" password=\"$systemPassword\" nickname=\"$systemNickname\" protocol=\"5.0\""
);
if(!$isAuthorized)
{
	exit("Error: unable to log in");
}

// Create a new account.
$isCreated = executeCommand("newaccount username=\"$newUsername\" password=\"$newPassword\" usertype=1");
if($isCreated)
{
	echo("Successfully created a new account named $newUsername!");
}
else
{
	exit("Error: unable to create the account");
}


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
