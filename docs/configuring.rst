Configuring
===========

The system is controlled by settings (more formally, :dfn:`configuration entries`)
from :file:`config.json` file located within the root folder.
It is impossible to launch Regsystem without a file with this name, so you have to create one first of all.
To simplify the task, don't write all the settings manually, but copy, rename and customize :file:`config.json.sample`,
which serves as a configuration template.

There are :dfn:`mandatory` and :dfn:`optional` entries.
All mandatory entries **must** be present in the configuration file.
Optional entries can be omitted; they often have default values.

All keys are strings in :dfn:`lower camel case`:
**no** spaces, **no** underscores, **no** hyphens, only lower-case letters (a-z) and digits (0-9);
each word except of the first one begins with a digit or with a **capital** letter.

The following sections provide comprehensive description of all configuration entries used by the Registration System.

Mandatory ``servers`` Object
----------------------------

This is a container for metadata of all TeamTalk 5 servers your Regsystem copy "knows" about;
they are called :dfn:`managed servers`.

Here, each top-level key is a unique :dfn:`server name` mapped to an object
encapsulating information directly related to this particular server.

Server information object itself has the following fields, which are all mandatory regardless of their nesting level:

``title`` (type: ``string``)
  A human-readable name intended to be displayed within Regsystem's GUI.

``host`` (type: ``string``)
  URL or IP address of the server.

``port`` (type: ``int``)
  TCP port of the server.

``systemAccount`` (type: ``object``)
  Specifies what data should be provided by Regsystem in order to authorize itself under an admin account. The format is intuitive:

  * ``username`` (type: ``string``)
  * ``password`` (type: ``string``)
  * ``nickname`` (type: ``string``)

Optional ``validation`` Object
------------------------------

Defines the rules by which user input is validated.

Contains two optional fields of type ``string``: ``username`` and ``password``.
Their values are both **PHP regular expressions** describing the respective entities.
By default, these regular expressions are equivalent:
":regexp:`/.+/i`" (in English: "At least one arbitrary character is required").
