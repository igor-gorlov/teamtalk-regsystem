<?php


/*
Includes mandatory files which a typical script needs access to, executes initial commands.
Should be run on program startup, before any other code.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "conf.php";


Config::init("config.json");


?>
