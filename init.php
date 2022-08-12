<?php


/*
Executes initial commands. Should be run on program startup, before any other code.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


// The default handler for uncaught exceptions and errors. Displays a pretty formatted message in a browser.
function printErrorMessage(Throwable $e): void {
	ob_start();
	echo("<table><tr><td style=\"color: red\"><strong>Error!</strong></td><td><pre><code>");
	echo($e->getMessage());
	echo("<br>(" . $e->getFile() . " : " . $e->getLine() . ")");
	echo("</code></pre></td></tr></table>");
	ob_end_flush();
}
