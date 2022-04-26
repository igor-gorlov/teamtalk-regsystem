<?php


/*
This file helps to communicate with the TeamTalk 5 server.
© Igor Gorlov, 2022.
*/


declare(strict_types=1);


// Represents a single command.
class Command
{
	public string $name;
	public array $params;
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

/*
Accepts a command in the form of a string; returns an object containing the parsed data.
This function expects the input to be a syntactically correct TeamTalk 5 command; no validation is performed.
*/
function parseCommand(string $command): Command
{
	$result = new Command;
	$matches = array(); // a reusable array to store preg_match results in.
	// Extract the name.
	preg_match("/^([a-z]+\b)(\s*)/i", $command, $matches);
	$result->name = $matches[1];
	$offset = strlen($matches[0]);
	// Parse the parameters.
	while($offset!=strlen($command))
	{
		// Extract the parameter name.
		preg_match("/^([a-z]+\b)\=/i", substr($command, $offset), $matches);
		$paramName = $matches[1];
		$offset += strlen($matches[0]);
		// Extract the parameter value.
		$value = null;
		if(preg_match("/^(true\b)(\s*)/i", substr($command, $offset), $matches))
		{
			$value = true;
		}
		elseif(preg_match("/^(false\b)(\s*)/i", substr($command, $offset), $matches))
		{
			$value = false;
		}
		elseif(preg_match("/^(\d+\b)(\s*)/i", substr($command, $offset), $matches))
		{
			$value = intval($matches[1]);
		}
		elseif(preg_match('/^\"(.*?)(\\\\)*\"\s*/i', substr($command, $offset), $matches))
		{
			$value = $matches[1];
		}
		$result->params[$paramName] = $value;
		$offset += strlen($matches[0]);
	}
	return $result;
}

/*
Parses a server reply into an array of objects of type Command.
The reply must be syntactically correct; this function performs no validation.
*/
function parseRespondingText(string $text): array
{
	// Prepare a container for future results.
	$commands = array();
	// Split the text into lines, which in fact are equivalent to commands.
	$text = rtrim($text);
	$lines = explode("\r\n", $text);
	// Build the resulting array.
	foreach($lines as &$line)
	{
		$command = parseCommand($line);
		$commands[] = $command;
	}
	return $commands;
}


?>
