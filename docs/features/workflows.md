# Workflows

A workflow is the [statuses](statuses.md) a type's entries move
through, and the transitions between them. Blush has one, `standard`:
`draft` and `published`, with Publish and Unpublish. A type uses it
unless it names another with `workflow` (see
[Content types](../concepts/content-types.md)).

## Workflows from a plugin

A plugin adds workflows with a class that implements `WorkflowSource`:

```php
<?php

declare(strict_types=1);

namespace Acme\Review;

use Blush\Workflow\Status;
use Blush\Workflow\Transition;
use Blush\Workflow\Workflow;
use Blush\Workflow\WorkflowSource;

final class Workflows implements WorkflowSource
{
	public function workflows(): iterable
	{
		yield new Workflow('acme_review', 'Review', [Status::DRAFT, 'acme_in_review', Status::PUBLISHED], Status::DRAFT, [
			new Transition('submit', 'Submit for Review', [Status::DRAFT], 'acme_in_review'),
			new Transition('publish', 'Publish', ['acme_in_review'], Status::PUBLISHED, capability: false),
			new Transition('send_back', 'Send Back', ['acme_in_review'], Status::DRAFT),
			new Transition('unpublish', 'Unpublish', [Status::PUBLISHED], Status::DRAFT, capability: false)
		]);
	}
}
```

Tag it in the plugin's [provider](../plugins/service-providers.md),
beside its statuses:

```php
protected const array TAGS = [
	StatusSource::TAG   => [Statuses::class],
	WorkflowSource::TAG => [Workflows::class]
];
```

A workflow has a name, a label, its statuses (registered ones, by
name), the status new entries start at (`initial`), and its
transitions, in the order the admin offers them. A transition has a
name, a label in Title Case, the statuses it moves from (none for any
of the workflow's), and the status it moves to.

## Moving between statuses

Moving an entry from one status to another needs a transition between
them. An entry whose status isn't in its type's workflow is offered the
transitions from `initial`, and can be moved to `initial` itself. Its
status still decides whether it's on the site, and `content:lint` warns
about it. Making a transition needs:

- Edit access to the entry.
- The transition's [capability](capabilities.md),
  `content.{type}.transition.{name}` (`content.post.transition.submit`),
  or `content.*.transition.{name}` for every type. A transition with
  `capability: false` has none of its own. The administrator and editor
  roles have every transition's.
- Publishing the entry, when it moves into or out of a live status.

The trash isn't part of any workflow: an entry in any status can be
moved there, and is restored at its workflow's `initial`. New and
copied entries start there too.

## Names and problems

When two plugins register a workflow by the same name, the first one is
kept. A workflow that names a status nothing registers, or whose
`initial` or transitions name statuses it doesn't list, is left out,
and its types use `standard`. `content:lint` lists both.

## Status events

Every change of status dispatches two [events](events.md), whether a
transition, Move to Trash, Restore, or Undo made it (`transition` is
`null` for those):

| Event | When |
|---|---|
| `Blush\Workflow\StatusChanging` | Before the change is saved. A listener can call `refuse($reason)` to stop it, and the person or app is told the reason |
| `Blush\Workflow\StatusChanged` | Once it's saved |

Each has the `actor` (who made it, and how), the `entry`, the statuses
it moves `from` and `to` (`published`, never `scheduled`), and the
`transition`.

```php
use Blush\Event\Listener\ListenerRegistry;
use Blush\Workflow\Status;
use Blush\Workflow\StatusChanging;

$this->container->get(ListenerRegistry::class)->listen(StatusChanging::class, function (StatusChanging $event): void {
	if ($event->to === Status::PUBLISHED && $event->entry->field('reviewed_by') === null) {
		$event->refuse('A reviewer has to sign off first.');
	}
});
```

## The site's own workflows

A site can have workflows of its own, without a plugin. **Structure →
Workflows** in the admin makes and edits them (see
[Workflows](../admin/workflows.md)); they're JSON files in
`user/data/workflows`, each named for its workflow, beside the site's
own [statuses](statuses.md) in `user/data/statuses`.
`user/data/workflows/standard.json`:

```json
{
	"label": "Reviewed",
	"statuses": ["draft", "in_review", "published"],
	"initial": "draft",
	"transitions": [
		{ "name": "submit", "label": "Submit for Review", "from": ["draft"], "to": "in_review" },
		{ "name": "publish", "label": "Publish", "from": ["in_review"], "to": "published", "capability": false },
		{ "name": "unpublish", "label": "Unpublish", "from": ["published"], "to": "draft", "capability": false }
	]
}
```

A workflow takes what a plugin's does, and replaces one by its name,
`standard` too, which every type without a workflow of its own uses. A
transition needs a capability of its own unless it says
`"capability": false`.

A file that can't be read, or a workflow that names statuses nothing
registers, is left out, and the one it would replace stays. Site
Health's **Statuses and workflows left out** and `content:lint` list
them. When a type's workflow changes, entries keep their status; Site
Health's **Statuses Outside Their Workflow** lists those the new one
doesn't use, and **Reset** moves them to its first status.
