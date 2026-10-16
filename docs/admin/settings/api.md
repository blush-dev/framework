# API settings

**Settings → API** turns on the site's [API](../../features/api/README.md)
and says who can read it.

- **API** turns it on (off by default; saved as `api.enabled`, over
  `config/api.php`), with its address shown and a link to it once it's
  on.
- **Who can read it:** **Anyone**, or **Only with a Token** for a
  private API (`api.access`; see [The API](../../features/api/README.md)).

Under **Changes**, shown only: how many changes a token may make in an
hour (`maxWrites` in `config/api.php`), and which roles may make API
tokens, with links to Your Account and Roles.

Under **MCP Server**, whether it's on, with a link to the
[AI screen](ai.md), where it's set.
