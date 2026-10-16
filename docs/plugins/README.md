# Plugins

A plugin is where a site's code belongs: blocks, components, content
types, commands, routes, and the rest. It can be turned on and off,
versioned, and reused on another site. Most people build with plugins.

| Page | What it covers |
|---|---|
| [Creating a plugin](creating-a-plugin.md) | `plugin:new`, `plugin.json`, autoloading, and the details shown in the admin |
| [Service providers](service-providers.md) | How a plugin connects its classes to Blush |
| [A plugin without a provider](without-a-provider.md) | Plugins of helper functions or text only |
| [Requirements](requirements.md) | What a plugin needs: Blush, PHP, extensions, and other plugins |
| [Conflicts](conflicts.md) | What a plugin can't run with |
| [Replacing another extension](replacing-extensions.md) | Forks and renamed packages, and the links other extensions have to yours |
| [Providing a package](composer-packages.md) | Standing behind a shared name other extensions require |
| [Debugging](debugging.md) | `dump()` and `dd()` |
| [Site code in `src/`](site-code.md) | The escape hatch for code that's only ever one site's |

Every extension kind shares the same manifest keys and namespaces: see
[Extensions](../concepts/extensions.md). To install one or turn it on,
see [Installing extensions](../concepts/installing-extensions.md).

What a plugin can add is in [APIs and features](../features/README.md):
[routes](../features/routes.md), [commands](../features/commands.md),
[blocks](../features/blocks/README.md), [components](../features/components/README.md),
[content types](../features/content-types.md),
[statuses](../features/statuses.md), [workflows](../features/workflows.md),
[fields](../features/fields.md), [field types](../features/field-types.md),
[icons](../features/icons.md), [scripts and styles](../features/scripts-and-styles.md),
[text](../features/translations.md), [events](../features/events.md),
and more.
