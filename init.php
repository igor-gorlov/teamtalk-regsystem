<?php


/*
Includes mandatory files which a typical script needs access to, executes initial commands.
Should be run on program startup, before any other code.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "conf.php";


// The default handler for uncaught exceptions and errors. Displays a pretty formatted message in a browser.
function printErrorMessage(Throwable $e): void {
	echo("<table><tr><td style=\"color: red\"><strong>Error!</strong></td><td><pre><code>");
	echo($e->getMessage());
	echo("</code></pre></td></tr></table>");
}


set_exception_handler("printErrorMessage");
Config::init("config.json");


?>
