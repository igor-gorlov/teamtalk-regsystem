<?php


/*
Interaction with end-users.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "server.php";


/*
Turns on output buffering and prints the header of the registration page.

From Regsystem's perspective, a "header of a page" is the part of HTML code that precedes main contents of this page;
not to be confused with <header>, <h1>, or other HTML elements.
*/
function beginRegistrationPage(): void {
	ob_start();
	echo("<!DOCTYPE html><html lang=\"en\">");
	echo("<head><meta charset=\"UTF-8\" />");
	echo("<title>TeamTalk Registration System by Igor Gorlov</title></head>");
	echo("<body><h1 style=\"text-align: center; text-decoration: underline; font-weight: bold\">");
	echo("Register a New TeamTalk Account</h1>");
}

/*
Prints the footer of the registration page, turns off output buffering, and flushes the buffer.

From Regsystem's perspective, a "footer of a page" is the part of HTML code that succeeds main contents of this page;
not to be confused with <footer> or other HTML elements.
*/
function endRegistrationPage(): void {
	echo("</body></html>");
	ob_end_flush();
}

// Prints the account creation form. Requires an array of ServerInfo objects representing the managed servers.
function showRegistrationForm(array $servers): void {
	ob_start();
	echo("<form method=\"GET\" action=\"reg.php\">");
	echo("<div><label for=\"server\">Select a server you would like to register on:</label><br>");
	echo("<select id=\"server\" name=\"server\">");
	foreach($servers as $server) {
		echo("<option value=\"$server->name\">$server->title</option>");
	}
	echo("</select></div>");
	echo("<div><label for=\"name\">Enter your username:</label><br>");
	echo("<input id=\"name\" type=\"text\" name=\"name\"></div>");
	echo("<div><label for=\"password\">Enter your password:</label><br>");
	echo("<input id=\"password\" type=\"password\" name=\"password\"></div>");
	echo("<div><button type=\"submit\" name=\"form\" value=\"1\">Register now!</button></div>");
	ob_end_flush();
}

// The default handler for uncaught exceptions and errors. Displays a pretty formatted message in a browser.
function printErrorMessage(Throwable $e): void {
	ob_start();
	echo("<table><tr><td style=\"color: red\"><strong>Error!</strong></td><td><pre><code>");
	echo($e->getMessage());
	echo("<br>(" . $e->getFile() . " : " . $e->getLine() . ")");
	echo("</code></pre></td></tr></table>");
	ob_end_flush();
}
