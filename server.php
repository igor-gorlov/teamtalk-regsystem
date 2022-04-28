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
function getRespondingText(int $id, string &$text): bool
{
	global $socket;
	$text = "";
	while(true)
	{
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
		if(preg_match("/^(true\b|false\b)(\s*)/i", substr($command, $offset), $matches)) // boolean
		{
			if($matches[1] == "true")
			{
				$value = true;
			}
			else
			{
				$value = false;
			}
		}
		elseif(preg_match("/^(\d+\b)(\s*)/i", substr($command, $offset), $matches)) // integer
		{
			$value = intval($matches[1]);
		}
		elseif(preg_match("/^\[((\d+,)*\d+)\]\s*/i", substr($command, $offset), $matches)) // array of integers
		{
			$value = explode(",", $matches[1]);
			foreach($value as &$elem)
			{
				$elem = intval($elem);
			}
		}
		elseif(preg_match('/^\"(.*?)(\\\\)*\"\s*/i', substr($command, $offset), $matches)) // string
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

/*
Sends the given command to the TeamTalk 5 server; waits for the server's reply;
outputs the result as an array of commands to $reply argument if the latter is provided.
Returns true if the command succeeded, otherwise returns false.
Note that you must NOT explicitly use "id" parameter in your command or finish it with "\r\n" sequence:
the function will handle those things implicitly.
This function implies (but does not verify) that $socket global variable is set
and represents connection between the script and the TeamTalk 5 server;
the caller is responsible for meating that prerequisite.
*/
function executeCommand(string $cmd, array|null &$reply=null): bool
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
	$respondingCommands = parseRespondingText($respondingText);
	if($reply != null)
	{
		$reply = $respondingCommands;
	}
	// Determine whether the command succeeded.
	if($respondingCommands[array_key_last($respondingCommands)]->name == "ok")
	{
		return true;
	}
	return false;
}


?>