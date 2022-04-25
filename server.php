<?php


/*
This file helps to communicate with the TeamTalk 5 server.
© Igor Gorlov, 2022.
*/


declare(strict_types=1);


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
