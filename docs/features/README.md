# APIs and features

What your code can do with Blush, one feature a page. The examples are
written for a [plugin](../plugins/README.md), which is where most code
belongs; each works the same from a theme's provider or from
[site code in `src/`](../plugins/site-code.md).

## Pages, commands, and content

| Page | What it covers |
|---|---|
| [Routes](routes.md) | Your own pages: forms, APIs, and webhooks |
| [Commands](commands.md) | Your own `bin/blush` commands |
| [Finding entries](finding-entries.md) | Querying entries with `Blush\Content\Entries` |
| [Changing content](changing-content.md) | Creating, changing, and deleting entries from code |
| [Content types from a plugin](content-types.md) | Defining types and relations in code |
| [Statuses](statuses.md) | Statuses of your own, and what they mean |
| [Workflows](workflows.md) | The statuses a type's entries move through |
| [Fields from a plugin](fields.md) | Registering the fields a plugin saves |
| [Field types](field-types.md) | New kinds of field |

## Output

| Page | What it covers |
|---|---|
| [Making blocks](blocks/README.md) | Blocks of your own, their variants, and using them in templates |
| [Components](components/README.md) | Reusable pieces of templates |
| [Menus in themes](menus.md) | Declaring menu locations and printing menus |
| [Menu links](menu-links.md) | New kinds of menu link |
| [Embed providers](embed-providers.md) | Turning links to other sites into embeds |
| [Icons from a plugin](icons.md) | A plugin's own icons |
| [Scripts and styles](scripts-and-styles.md) | Registering assets by handle, and text in scripts |
| [Text in PHP code](translations.md) | `t()`, catalogs, and translation domains |
| [Template engines](template-engines.md) | Templates in something besides plain PHP |
| [Reading more from media files](media-readers.md) | Metadata readers for more file formats |

## Data, settings, and work in the background

| Page | What it covers |
|---|---|
| [Storing data](storing-data.md) | A plugin's own records |
| [Settings](settings.md) | A plugin's or theme's settings |
| [Secrets](secrets.md) | API keys and tokens for other services |
| [Tools](tools.md) | Actions offered on the Tools screen, to AI apps, and on the command line |
| [Background jobs](background-jobs.md) | Work done outside the request that asked for it |
| [Scheduled tasks](scheduled-tasks.md) | Jobs run on a schedule |
| [Notifications](notifications.md) | Telling people something happened |
| [Events](events.md) | Listening for what Blush does |
| [Capabilities](capabilities.md) | What accounts may do, and signed-in routes |

## Your site from outside

| Page | What it covers |
|---|---|
| [The API](api/README.md) | Your site as JSON, for other front ends and apps |
| [AI apps and MCP](mcp/README.md) | Connecting AI apps to work on your site |
| [The admin's API](admin-api/README.md) | Building your own admin |
