# Statuses

An entry's `status` is a name that Blush, a plugin, or the site
registers. Blush has `draft` and `published`, plus `trash`, `scheduled`,
and `expired`, which it sets itself. Statuses are moved between by a
[workflow](workflows.md).

## Statuses from a plugin

A plugin adds statuses with a class that implements `StatusSource`:

```php
<?php

declare(strict_types=1);

namespace Acme\Review;

use Blush\Workflow\Status;
use Blush\Workflow\StatusSource;
use Blush\Workflow\Tone;

final class Statuses implements StatusSource
{
	public function statuses(): iterable
	{
		yield new Status('acme_in_review', 'In Review', tone: Tone::Warn);
		yield new Status('acme_featured', 'Featured', live: true, tone: Tone::Accent);
	}
}
```

Tag it in the plugin's [provider](../plugins/service-providers.md):

```php
protected const array TAGS = [
	StatusSource::TAG => [Statuses::class]
];
```

A status's name is lowercase letters, digits, and underscores, and
it's what files write (`status: acme_in_review`). It also has:

| Option | What it does |
|---|---|
| `label` | What the admin calls it ("In Review") |
| `plural` | What a list of them is called; the label when not given |
| `live` | Whether entries with it are on the site once their `date` comes; `false` by default. A live status with a date still to come is scheduled, as `published` is |
| `active` | Whether lists and queries for "any status" include it; `true` by default. Only the trash is left out among Blush's own |
| `tone` | How the admin colors its pill: `Tone::Neutral` (the default), `Good`, `Warn`, `Danger`, or `Accent` |

## Finding entries by status

Queries find a status by name (`$query->status('acme_in_review')`), and
`live()`, the default, finds entries of every live status whose date has
come (see [Finding entries](finding-entries.md)).

## Names

When two plugins register a status by the same name, or one uses
`trash` or `scheduled`, the first one is kept and the other is left
out, so give yours a prefix of your own.

An entry whose status nothing registers, such as one from a plugin
that's turned off, isn't on the site. It keeps its status as written,
and `content:lint` warns about it.

## The site's own statuses

A site can have statuses of its own, without a plugin. **Structure →
Workflows** in the admin makes and edits them (see
[Workflows](../admin/workflows.md)). They're JSON files in
`user/data/statuses`, each named for its status.
`user/data/statuses/in_review.json`:

```json
{ "label": "In Review", "plural": "In Review", "tone": "warn" }
```

A status takes `label`, `plural`, `tone`, `live`, and `active`, as a
plugin's does. One named for a status Blush or a plugin has
(`published`) changes only its `label`, `plural`, and `tone`, never
whether entries with it are on the site: `{ "label": "Live" }` renames
Published. The names Blush works out itself (`trash`, `scheduled`,
`expired`) can't be changed.

A file that can't be read is left out. Site Health's **Statuses and
workflows left out** and `content:lint` list it.
