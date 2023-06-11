# Notes on Development of TeamTalk 5 Registration System

## Branching Strategy

There are four linear branches, each of them represents a level of abstraction:

-   `silent`: every commit changing anything in the codebase. All commits are prefixed with Unicode character “Muted
    Speaker” (🔇).
-   `quiet`: only merges from `silent` which contain **minor** changes (like a multi-step refactoring of a couple of
    classes). All commits are prefixed with Unicode character “Speaker Low Volume” (🔈).
-   `medium`: only merges from `quiet`which contain **major** changes (like adding a new class). All commits are
    prefixed with Unicode character “Speaker Medium Volume” (🔉).
-   `loud`: the most global and important events (like finalizing a new subsystem). All commits are prefixed with
    Unicode character “Speaker High Volume” (🔊).

Releases are tagged exclusively on `loud`.

## Project Structure

-   Store templates of webpages in `/templates/` subdirectory.
-   Store CSS stylesheets in `/styles/` subdirectory.

## Miscellaneous Rules

-   Don't forget to update copyright records at the top of the source files whenever needed.
-   Do **not** store third-party dependencies (both development and production ones) within the repository; use Git
    submodules and/or other dependency managers.
-   Use only **`require_once`** to include PHP scripts.
