<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "account.php";
require_once "configurator.php";
require_once "json.php";
require_once "language.php";
require_once "server.php";
require_once "validator.php";
require_once "ui.php";


// Set up GUI.
set_exception_handler("printErrorMessage");
ini_set("intl.use_exceptions", true);
$validator = new Validator;
$locale = Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
$langpack = new LanguagePack($validator, $locale);
beginRegistrationPage($langpack);
register_shutdown_function("endRegistrationPage");

// Configure the essential options.
$config = new Configurator($validator, new Json("config.json"));
$newAccount = null;
try {
	$newAccount = UserInfo::fromUrl($validator, $config->getAllServersInfo());
}
catch(Exception $e) {
	if(!isset($_GET["form"])) {
		showRegistrationForm($langpack, $config->getAllServersInfo());
		exit();
	}
	throw $e;
}
$systemAccount = $config->getSystemAccountInfo($newAccount->server->name);

// Create a new account.
$registrator = new AccountManager($validator, new Tt5Session($systemAccount));
$registrator->createAccount($newAccount);
echo($langpack->getMessage("registrationSucceeded", array("username" => $newAccount->username, "serverTitle" => $newAccount->server->title)));
