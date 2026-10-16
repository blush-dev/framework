# A plugin without a provider

A [provider](service-providers.md) is what connects a plugin's classes
to Blush: blocks, components, icons, commands, listeners, and the rest.
A plugin that has none of those can leave `provider` out of its
`plugin.json`. It can still:

- load files of functions, such as helpers for templates, with
  `autoload.files`;
- need other plugins, PHP, or extensions, with
  [`require`](requirements.md);
- carry a `lang/` catalog for its text (see
  [Text in PHP code](../features/translations.md)).

```json
{
	"name": "acme/template-helpers",
	"autoload": { "files": ["src/helpers.php"] }
}
```

Its files load when it's on, before any provider registers, so templates
can call its functions. They can't reach Blush's services. Wrap each
function in `if (! function_exists(…))`, since a file of functions
can't be loaded twice.

A plugin with none of these is allowed too; it's listed, and turning it
on does nothing.
