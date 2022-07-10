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
