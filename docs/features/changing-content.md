# Changing content

`Blush\Content\Entries`, which [finds entries](finding-entries.md), also
creates and changes them, the same way `content:new` and the admin do.
Each change names an entry by its id (or takes the `Entry` itself) and
answers the entry as it is afterward:

```php
use Blush\Content\Entries;
use Blush\Content\Writer\EntryChanges;

$post = $this->content->find('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74');

$post = $this->content->change($post, new EntryChanges(
	set: ['status' => 'draft', 'tags' => ['news']],
	remove: ['summary']
), $post->version);
```

- **Only what you change changes.** Other front matter keeps its order,
  comments, and spacing, and a key the file writes by an older name
  (`excerpt` for `summary`) is updated under that name.
- **Values** are plain data: text, numbers, `true`/`false`, `null`, and
  lists or maps of them. Write dates as text, such as
  `2026-09-29 09:00:00 -05:00`.
- **`$post->version`** protects against lost edits: if the entry changed
  since you read it, the change throws `WriteConflict` and writes
  nothing. Leave it out to skip the check.
- **Where a file goes** is Blush's to work out: a new slug or date
  renames a file its type names by date and moves it to the folder its
  type keeps it in, and a file that names its slug in `slug:` keeps its
  name.
- **Ids** (see [Ids and links](../concepts/ids-and-links.md)): every new
  entry, a copy included, gets an `id`, last in its front matter.
  Changes never set or remove `id`. An entry without one can't be
  changed until it has one: run `bin/blush content:ids --write`, or use
  Site Health.
- Every change refreshes cached pages.

## Every change

The other changes work the same way:

- `create($type, $slug, $changes)` writes a new entry of a type, and
  `create($type, $slug, $changes, $parent)` a tree's page under another
  (by id).
- `rename($entry, $slug)` gives it a new slug, so a new address.
- `move($page, $parent)` puts a tree's page under another, with the
  pages under it (`null` for the top).
- `duplicate($entry, $slug, $changes)` copies it under the first free
  slug from `$slug`.
- `trash($entry)` sets `status: trash` and `trashed`, leaving it where it
  is; `restore($entry)` brings it back as a draft.
- `delete($entry)` removes it for good.
- `editable($entry)` reads its front matter as written, its Markdown, and
  its version, for an editor of your own.

## Changing content as a person

To change content as a person would, with their permissions checked and
the change listed in the [activity log](mcp/README.md), use
`Blush\Content\Editing\EntryEditor`, the admin's and the MCP server's
write path: `create()`, `update()`, `replaceInBody()`, `setStatus()`,
`trash()`, and `restore()` each take a `Blush\Activity\Actor` (who, and
how) and throw `EditRefused`, saying why, when the change isn't allowed.

## Name entries by id

Content is kept as files for now, so changes are written only as
content files inside `user/content`, and any edit that can't be made
without changing something else is refused (`WriteException`), leaving
the file as it was. Name entries by id, never by file path, and your
code keeps working when a site keeps its content another way.
