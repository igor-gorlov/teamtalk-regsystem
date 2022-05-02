<?php


/*
This script accepts registration data and creates a new TeamTalk 5 account from it.
© Igor Gorlov, 2022.
*/


require "server.php";


try
{

	// Configure the essential options.
	$host = "localhost"; // TeamTalk 5 server address.
	$port = "10333"; // TeamTalk 5 TCP port.
	$systemUsername = "regsystem"; /* The name of a registered admin account which will be used for all operations
	                                   involving the server (so-called "the system account"). */
	$systemPassword = "qwerty123456"; // The password of the system account.
	$systemNickname = "Registration System"; // The system account's nickname.

	// Establish connection.
	$connection = new TtServerConnection($host, $port);

	// Authorize under the system account.
	$connection->login($systemUsername, $systemPassword, $systemNickname);

	// Create a new account.
	$newUsername = $_GET["name"];
	$newPassword = $_GET["password"];
	$connection->createAccount($newUsername, $newPassword);
	echo("Successfully created a new account named $newUsername!");

}
catch(Exception $e)
{
	echo("<pre>Error: ".$e->getMessage()."</pre>");
}


?>
