<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "vendor/autoload.php";

require_once "account.php";
require_once "configurator.php";
require_once "json.php";
require_once "language.php";
require_once "server.php";
require_once "validator.php";
require_once "ui.php";


use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

set_exception_handler("printErrorMessage");
ini_set("intl.use_exceptions", true);
ob_start();
register_shutdown_function("ob_end_flush");

// Prepare common services needed for all operations.
$view = new TwigEnvironment(new TwigFilesystemLoader("templates/"));
$validator = new Validator;
$locale = Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
$langpack = new LanguagePack($validator, $locale);
$config = new Configurator($validator, new Json("config.json"));
$allServers = $config->getAllServersInfo();

// Try to create a new account directly from its properties passed via URL.
if(isset($_GET["form"])) {
	$newAccount = null;
	try {
		$newAccount = UserInfo::fromUrl($validator, $allServers);
	}
	catch(BadQueryStringException $e) {
		echo $view->render("reg_results.html", array(
			"langpack" => $langpack,
			"succeeded" => false,
			"invalidUrlParams" => $e->invalidUrlParams
		));
		exit();
	}
	$systemAccount = $config->getSystemAccountInfo($newAccount->server->name);
	$registrator = new AccountManager($validator, new Tt5Session($systemAccount));
	try {
		$registrator->createAccount($newAccount);
	}
	catch(AccountAlreadyExistsException) {
		echo $view->render("reg_results.html", array(
			"langpack" => $langpack,
			"succeeded" => false,
			"newAccount" => $newAccount
		));
		exit();
	}
	echo $view->render("reg_results.html", array(
		"langpack" => $langpack,
		"succeeded" => true,
		"newAccount" => $newAccount
	));
}

// Display a registration form.
else {
	echo $view->render("reg_form.html", array(
		"langpack" => $langpack,
		"servers" => $allServers
	));
}
