<?php


/*
Interaction with end-users.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


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
