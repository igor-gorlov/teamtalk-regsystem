# Notes on Development of TeamTalk 5 Registration System

## Classification of PHP Scripts

There are two types of PHP source files in the project: an end-user directly calls _root_ scripts, while root scripts
themselves include _helper_ scripts. Usually, root scripts are **never** included anywhere, and helper ones are
**never** executed directly.

## General Rules

-   Don't forget to update copyright records at the top of the source files whenever needed.
-   Do **not** store third-party dependencies (both development and production ones) within the repository; use Git
    submodules and/or other dependency managers.
-   Use only **`require_once`** to include PHP scripts.
-   All validation methods must **not** restrict type of their input entity with type declarations; use `mixed`
    declaration, but check the actual type of the entity inside method body.
-   Do **not** pass a `Configurator` instance itself to any function (including but not limiting to other constructors);
    instead, provide only **data** retrieved from a `Configurator` object.
-   `init.php` must be the **very first** file required by any root script (that is, the line `require_once "init.php";`
    must go before all other includes); helper scripts must **not** require `init.php`.
