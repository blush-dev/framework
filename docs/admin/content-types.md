# Content types

With `site.settings`, **Structure → Content Types** lists every
[content type](../concepts/content-types.md), and each type has a page
of its settings. To make a new one, see
[Creating a content type](new-content-type.md).

## The list

Tabs narrow it to a kind (Collections, Taxonomies, Trees, Profiles;
only the ones the site has), beside a search. Each row has:

- its name, with its key and address under it;
- its kind, with what a taxonomy files, or that a tree or collection
  nests;
- where it's defined (this site, built in, or the plugin, by name), and
  whether anything is changed here;
- how many fields and entries it has.

## What can change here

Types in `user/data/types` are changed on their page. So are
collections and [trees](../concepts/trees.md) from plugins: what you
change is saved in `user/data/types/{key}.json` as only what differs from
the plugin's (see [Content types](../concepts/content-types.md)),
and each setting changed there says **Changed Here** and what the plugin
sets. The built-in pages and profiles show their settings as they are;
their place in the menu, their fields, and their relationships still
change. A type still written as a taxonomy can't be changed until it's
migrated (see [Site Health](site-health/checks.md#taxonomies) and
[Relationships](../concepts/relationships.md)), nor
can one that still names its own folder until it's moved (see
[Type Folders](site-health/checks.md#type-folders)).

## A type's page

A type's page is one long page of panels. A dot marks a setting with
changes not saved yet.

- **General:** its kind and name, both fixed once it's made, its icon,
  and who can use it: the roles that grant every type, and the ones that
  name types one by one.
- **Labels:** its plural and singular, its description, and every label
  made from them (its name in the menu, in a sentence, on its New
  button, the editor's title, and its search box), each blank for the
  default shown.
- **Supports:** the built-in fields its entries have beyond the title,
  slug, status, and dates: **Featured Image**, **Summary**, **Subtitle**,
  **Position**, **Visibility**, and **Expiration** (see
  [Supports](../concepts/content-types.md)).
- **In the Menu:** **Sits Under** and **Position** in the Content menu.
- **Behavior:** on the site (**Public**, **Sitemap**, **Listed for AI**
  for `llms.txt`, **Feeds**, and the feeds' categories and listing), its
  entries (**Order**, its **Listing Page**: what it lists, by what, in
  which direction, and how many to a page, and its **Workflow**), and,
  when content is kept in files, **File Names** and **Folders**.
- **Archives:** a collection's **Date Archives** and its **Index Page**.
- **Relationships:** **Nesting** (an entry can name a parent),
  **Author Credits** (joining the `authors` relationship), each
  relationship archive's list page, and the **Byline**, then every
  relationship it takes part in (see [below](#relationships)).
- **Addresses:** **Routes** (off, it's still public but nothing has an
  address), its **URL Prefix**, and each address's path (see
  [below](#addresses)).
- **Fields** (see [below](#fields)), and a **Danger Zone** with
  **Delete** (or **Reset** for a plugin's type).

What a kind doesn't have is left out, with a line saying so. What
another setting turns off stays in place, disabled, naming that setting
("Disabled while Public is off"). What a change does is said under the
setting as you make it: pages that move to new addresses, new file
names for new files only, statuses kept. **Update**, at the top right,
saves every change (it says **Saved** when there's nothing to save),
and the menu beside it has **Revert changes**. Leaving with changes
unsaved asks first.

Only the options you change are written, and nothing at its default:
the rest of the file stays as you wrote it, comments included. A change
that doesn't fit with the other types, such as two types in one folder,
is refused with the reason, and the file is left as it was. The site
uses a change on the next request.

### Relationships

The **Relationships** panel lists every [relation](../concepts/relationships.md)
the type takes part in, from either side, each said as a sentence from
its side ("Credits **Profiles** as Cooks", "Filed under **Courses**",
"Linked from **Posts** as Mentions"), with its key and what it takes
("At least 1, in order", "Up to 3"). The rows are split by where each
is stored: the ones **Stored on** this type (its files carry the key)
have **Edit** and **Remove**; one stored on another type has only the
way to that type (**Edit on Posts**), since that's where it's edited.
One from a plugin keeps its row with **In code**, and says where it's
defined. **View in Relationships** opens the
[Relationships](relationships.md) list for this type, and **Add
Relationship** starts one stored here. Whether a relation has archives,
and their word, are set on its [screen](relationships.md#a-relationships-screen); a
page written for one target's archive is kept when they're off, and on a
profile it's marked **Unreachable**.

### Addresses

**Addresses** lists every route the type has, grouped as
Collection (its listing, later pages, and feeds), Single (its entries,
and a taxonomy's pages and feeds), Date Archives, and each
relationship's archive. Each route, such as Listing, Entry, or Year
archive, has its path after the type's prefix, which shows its default
when empty.

Click into a path, as into an upload path on
[Settings → Media](settings/media.md), for the
address it makes and its {placeholders}, which go in at the cursor (a
dot marks one it needs): an
entry's address needs `{name}` and may hold the date's parts
(`{year}` to `{second}`) and the name of a type of terms it's filed
under; later pages need
`{page}`. A path that leaves out what it needs, or holds something it
can't fill, is refused with the reason. Changing an address moves those
pages, so add [redirects](redirects.md) for the old ones.

### Fields

**Fields** lists the [library fields](fields.md) the type carries, in its
[sections](../concepts/custom-fields.md), as a tree. Each row is a
field's control, its label as this type asks for it, its key, and
**Required** when it's required to publish, or a section's heading with
its fields under it. **Add Field** picks a field from the library, or
makes a **New Field** there, on a screen of its own that comes back
with it ([Fields](fields.md)); a new type only picks. **Add Section**
adds a heading. A field goes into a section by indenting it under one
(⌥→, or drag it onto the section), and out by outdenting it (⌥←).

Open a field's row to change only how this type asks for it: its
**Label**, its **Help**, and **Required to publish**, each the field's
own until you change it (the row then says **Changed Here**); the field
itself, its key and settings, is edited in the library, which the row
links to. Open a section's row to change its heading and help. Removing
a section keeps its fields. A type from a plugin may have fields of its
own, listed above, read only.

### Deleting and resetting

**Deleting** a type is refused while a relationship names it, listing
which. Its entries stay in their folder, off the site; the list then
says where, with a way to Site Health, until it's dismissed.

**Delete This Type** removes its file. Its entries stay in its folder,
unlisted until a type claims the folder again. A type a relation names
can't be deleted until the relation is removed ("The "tag" relation
names post; remove it first.").

A type from code says where it's defined and where changes go. Its file
keeps only what differs from the code, and is removed when everything
is back at the code's values. **Reset the Type** removes the file,
going back to the plugin's definition and undoing every change made here; a type
from code can't be deleted here. If some of its fields are field
classes from code, its fields are shown but changed in code.
