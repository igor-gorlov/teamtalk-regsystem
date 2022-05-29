Configuring
===========

The system is controlled by settings from :file:`config.json` file located within the root folder.

All keys are strings in :dfn:`lower camel case`:
**no** spaces, **no** underscores, **no** hyphens, only lower-case letters (a-z) and digits (0-9);
each word except of the first one begins with a digit or with a **capital** letter.

Although JSON specification permits to use ``null`` values, they are **forbidden** in Regsystem configuration.

The following sections will describe JSON entities used by the Registration System.

``servers`` Object
------------------

This is a container for metadata of all TeamTalk 5 servers being managed by your Regsystem copy.

Here, each key is a unique :dfn:`server name` mapped to an object
encapsulating information directly related to this particular server.

Server information object itself has the following fields:

``host``
  URL or IP address of the server.

``port``
  TCP port of the server.

``systemAccount``
  Specifies what data should be provided by Regsystem in order to authorize itself under an admin account. The format is intuitive:

  * ``username``
  * ``password``
  * ``nickname``
