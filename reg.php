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

/*
Returns true if a reply to the command pointed by the given id has already arrived; otherwise returns false.
Writes the reply (with "begin" and "end" parts excluded) to $text argument.
This function implies (but does not verify) that $socket global variable is set
and represents connection between the script and the TeamTalk 5 server;
the caller is responsible for meating that prerequisite.
*/
function getRespondingText($id, &$text)
{
	while(true)
	{
		global $socket;
		$text = "";
		$line = fgets($socket);
		if($line==false) // the end of the stream is reached.
		{
			return false;
		}
		if($line=="begin id=$id\r\n") // the beginning of the reply is found.
		{
			while(true)
			{
				$line = fgets($socket);
				if($line == "end id=$id\r\n") // the end of the reply is found.
				{
					return true;
				}
				$text .= $line;
			}
		}
	}
}

/*
Sends the given command to the TeamTalk 5 server; waits for the server's reply;
assigns the responding text to $reply argument (if provided).
Returns true if the command succeeded, otherwise returns false.
Note that you must NOT explicitly use "id" parameter in your command or finish it with "\r\n" sequence:
the function will handle those things implicitly.
This function implies (but does not verify) that $socket global variable is set
and represents connection between the script and the TeamTalk 5 server;
the caller is responsible for meating that prerequisite.
*/
function executeCommand($cmd, &$reply=null)
{
	// Prepare data.
	static $id = 0;
	global $socket;
	$id++;
	$cmd .= " id=$id\r\n";
	// Send the command.
	fwrite($socket, $cmd);
	// Wait for the reply; output its body if required.
	$respondingText = "";
	while(!getRespondingText($id, $respondingText))
	{}
	if($reply != null)
	{
		$reply = $respondingText;
	}
	// Determine whether the command succeeded.
	if(preg_match("/ok\r\n/", $respondingText))
	{
		return true;
	}
	return false;
}


?>
