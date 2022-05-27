Configuring
===========

The system is controlled by settings from :file:`config.json` file located within the root folder.

All keys are strings in *lower camel case*:
no spaces, no underscores, no hyphens, only lower-case letters (a-z) and digits (0-9);
each word except of the first one begins with a digit or with a capital letter.

Although JSON specification permits to use ``null`` values, they are **forbidden** in Regsystem configuration.
