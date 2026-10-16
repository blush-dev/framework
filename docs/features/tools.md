# Tools

A tool is something your site can do, written once in PHP and offered
everywhere it fits:

- to [AI apps connected to your site](mcp/README.md), which can be asked
  to use it;
- as a button on [the admin's](../admin/README.md) Tools screen, when it
  needs no input, grouped under your plugin (by its autoload prefix or
  its provider's namespace); the admin draws it, so you don't write any
  JavaScript;
- on [the command line](../reference/cli/README.md):
  `bin/blush tool acme/rebuild-thumbnails`.

## Writing a tool

Extend `Blush\Tool\Tool`:

```php
namespace Acme\Gallery\Tools;

use Acme\Gallery\Thumbnails;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolResult;

final class RebuildThumbnails extends Tool
{
	public function __construct(private readonly Thumbnails $thumbnails)
	{}

	public function title(): string
	{
		return 'Rebuild Thumbnails';
	}

	public function description(): string
	{
		return 'Makes every album\'s thumbnails again from the original photos.';
	}

	public function capability(): string
	{
		return 'gallery.manage';
	}

	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	public function handle(array $input, ToolContext $context): ToolResult
	{
		return ToolResult::done(sprintf('Rebuilt %d thumbnails.', $this->thumbnails->rebuild()));
	}
}
```

Register it from your plugin's [provider's](../plugins/service-providers.md)
`boot()` under a key, `vendor/name` in lowercase letters, digits, and
hyphens, each part starting with a letter. A key that's taken is
refused:

```php
use Blush\Tool\ToolRegistry;

$this->container->make(ToolRegistry::class)->register('acme/rebuild-thumbnails', Tools\RebuildThumbnails::class);
```

An AI app knows it by the key as one word, `acme_rebuild_thumbnails`;
that's at most 64 characters, so keep keys short.

## What a tool says

- **`title()`** is a short name in Title Case, the button's label.
- **`description()`** is read by people on the Tools screen and by AIs
  deciding whether to use it: say what it does and, for one that
  answers something, when to use it and what it answers. Keep tools few
  and shaped around tasks; an app works better with a handful of clear
  tools than with many small ones.
- **`input()`** is the JSON Schema of what it takes, an object; leave it
  out for a tool that takes nothing. Input is checked against it before
  `handle()` runs (`type`, `properties`, `required`,
  `additionalProperties`, `enum`, `items`, and lengths and ranges), so
  `handle()` reads what it declared.
- **`output()`** is the JSON Schema of what it answers, optional.
- **`capability()`** is required: a tool without one is never offered.
  Use one of Blush's (`ContentAction::Edit` is editing entries of any
  type) or [your own](capabilities.md), which roles grant on their
  screen.
- **`annotations()`** says what it does: `ToolAnnotations::reads()`
  (the default), or `ToolAnnotations::writes()`, with whether it can
  destroy something, whether repeating it changes nothing more, and
  whether it only ever writes drafts (`draftsSafe`). Apps use them to
  ask the person before a change. A token or app limited to **Read**
  is offered only tools that read; one limited to **Write drafts** only
  those that read or are `draftsSafe`.

## Answering and failing

- **Answering:** return a `ToolResult` with data (what an app or
  `--json` reads), a line of text for people, and optional details, a
  line each. `ToolResult::done('Rebuilt 12 thumbnails.')` is a line
  of text alone.
- **Failing:** throw `ToolError` with words a person or an AI can act
  on, and optional details. It goes back as the tool's answer, marked as
  an error, so they can correct it and try again.
- **Changing content** goes through `$context->entries`
  (`Blush\Content\Editing\EntryEditor`) and media through
  `$context->media` (`Blush\Media\MediaEditor`): the same checks the
  admin makes, narrowed to what an app may do, and listed in the
  [activity log](mcp/README.md) with Undo.
- **`$context->account()`** is the account it acts as, and
  `$context->can()` checks its capabilities, narrowed as a token or app
  is.

## Where it shows

- **The Tools screen** shows a tool that needs no input (nothing in its
  schema is `required`) and writes. One that only reports something,
  such as counting words, returns `true` from `button()`; one that
  shouldn't be a button returns `false`. `confirm()` returns a question
  to ask before it runs; a tool that may destroy something is asked
  about anyway.
- **AI apps** are offered every tool unless `mcp()` returns `false`, as
  Blush's own Publish and Clear Caches do: they're for looking after the
  site.
- **Work that may outlast a request:** return `true` from `queued()`.
  The Tools screen (and an app) then runs it in the background as a
  [job](background-jobs.md), and the admin follows it with its progress
  to how it ended. The command line runs it straight away.

**Settings → AI** lists your plugin's tools for apps under **Tools**,
grouped under your plugin's name. When your plugin is off, its tools
are gone.
