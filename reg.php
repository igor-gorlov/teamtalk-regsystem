<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "account.php";
require_once "configurator.php";
require_once "json.php";
require_once "server.php";
require_once "validator.php";
require_once "ui.php";


// Set up GUI.
set_exception_handler("printErrorMessage");
beginRegistrationPage();
register_shutdown_function("endRegistrationPage");

// Configure the essential options.
$validator = new Validator;
$config = new Configurator($validator, new Json("config.json"));
$newAccount = null;
try {
	$newAccount = UserInfo::fromUrl($validator, $config->getAllServersInfo());
}
catch(Exception $e) {
	if(!isset($_GET["form"])) {
		showRegistrationForm($config->getAllServersInfo());
		exit();
	}
	throw $e;
}
$systemAccount = $config->getSystemAccountInfo($newAccount->server->name);

// Create a new account.
$registrator = new AccountManager($validator, new Tt5Session($systemAccount));
$registrator->createAccount($newAccount);
echo("Successfully created a new account named $newAccount->username on {$newAccount->server->title}!");
