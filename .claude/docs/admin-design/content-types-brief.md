# Content types: a design brief

> **Status:** built from the admin's shared pieces (D-311, D-349, D-386,
> D-593, D-726, D-851, D-852, D-855, D-856); a design for them is
> welcome.

For the design team. This is what goes into making and changing a
content type in the admin: the flows, what's allowed, what each choice
does, and what's fixed once chosen. It says what the screens need, not
how they should look. Build from the existing pieces (panels, the
wizard's steps, the save bar, the danger zone, toasts) wherever they
fit.

Words, as the admin uses them: a **type** is a kind of content (Posts,
Recipes, Docs); an **entry** is one item of a type; a type's **key** is
its fixed name in files and addresses (`recipe`); a **relationship**
links entries of one type to entries of another. Buttons are Title
Case.

---

## 1. What a type decides

A type defines what its entries are, where they live, and what fields
they carry:

- **What people call it:** its names, a description, an icon.
- **Where its entries are on the site:** whether it has pages at all,
  its addresses, its feed, archives by date, whether it's in the
  sitemap and `llms.txt`.
- **How its entries are kept:** file names and folders (only when the
  site keeps content in files).
- **How its entries behave:** order, nesting, the statuses they move
  through (workflow), expiry, and who they credit.
- **What its entries hold:** fields of its own, field sets added to it,
  and its relationships.
- **Where it sits in the admin:** its place in the Content menu.

Every entry of every type also has a title, a status, dates, and a
slug, and, in a type with addresses that keeps the option on, a
visibility.

## 2. The kinds

A type's kind is chosen when it's created and **never changes**.

| Kind | What it is | Examples |
|---|---|---|
| **Collection** | Entries listed together, newest first by default, with a listing page, a feed, and date archives if wanted. Files sit flat in the type's folder, or in folders by year, month, or first letter. | Posts, Recipes, Movies |
| **Taxonomy** | A collection that other types are filed under, through a classify relationship created with it. Ordered by position, then title; not dated; may nest. Not a stored kind of its own: it's a collection plus its relationship. | Categories, Genres, Topics |
| **Tree** | Entries that nest by folder, like pages. Each entry's address is its path under the type's prefix. No listing, feed, or date archives. | Docs; the built-in Pages |
| **Profiles** | The people entries credit (authors, cooks). **One per site, built in**, never created here; shown under Users, not Content. | Profiles |

## 3. Where types come from

Who defined a type sets what the admin may do to it:

| Origin | Shown as | What the admin can do |
|---|---|---|
| **The site** (`user/data/types`) | Editable | Everything: edit every setting, delete. |
| **Code** (a plugin, or the site's provider), a collection or tree | Editable through a file of changes | Change settings; only what differs from the code is saved. **Reset** throws the changes away. Can't be deleted. |
| **Core's Pages and Profiles** | Read-only facts | Only their relationships, and (Pages only) their place in the menu. |

Two site settings in config can lock things, and the screens say so
when they do:

- `dataTypes` off: no types can be created or changed here.
- `dataTypeUrls` off: types made here can't set their addresses.

**Who:** creating, changing, and deleting types needs the `site.settings`
capability.

## 4. Creating a type

A wizard of three steps, with a summary of what will be created beside
it that updates as it's filled in. Leaving with anything filled in
asks first.

### Step 1: Basics

- **Kind:** Collection, Taxonomy, or Tree.
- **Name (plural)** (required) and **Name (singular)**. The singular
  follows the plural until it's typed.
- **Key:** follows the plural made singular until it's typed. Rules:
  starts with a lowercase letter; lowercase letters, digits, and
  underscores only; not already a type (from the site or code); not
  `system`, `error`, or `drafts`. **Fixed once created.**
- **Description:** a sentence on what it's for; shown on its list while
  it's empty.
- **Icon:** from the site's icons; without one, its kind's.

### Step 2: Behavior

The settings in §6, with the kind's defaults (§6's table). For a
Taxonomy, also **which types it files** (any but Profiles; none means
it can file any type).

### Step 3: Fields

The type's own fields (§8). A collection starts with a featured image
field; a taxonomy and a tree start with none.

### What Create does

1. Saves the type, checked against every other type; if it doesn't fit,
   nothing is saved and the reason is shown.
2. If asked: writes its **index page**, an entry at its prefix titled
   with the plural name, pinned at the top of its list.
3. If it credits authors: joins the `authors` relationship, and, if
   asked, writes the page introducing its list of authors.
4. For a Taxonomy: creates the classify relationship, named after the
   type, that files the chosen types under it.
5. Rebuilds the site's addresses and index, then opens the type's
   screen with a toast.

## 5. Changing a type

The type's screen holds every setting at once, in sections (General,
In the Menu, Behavior, Archives, Relationships, Addresses, Fields), and
saves from one save bar that counts the changes. **Revert** puts them
back; leaving with changes unsaved asks first. Relationships save on
their own (they're their own records).

**Fixed after creation:** the kind and the key (files, addresses,
capabilities, and relationships name it).

**What a change doesn't do:** it never renames or moves existing files.

- **File names and folders** apply to new entries; Site Health can
  rename or move older files to the new pattern.
- **Addresses:** the pages move to their new addresses. Redirects for
  the old ones are added separately.
- **Workflow:** entries keep their status.

A save is checked against every other type before it's kept (a
relationship naming a type that's gone, say). A refusal says why, and
nothing changes.

## 6. Settings, by kind

Yes means the setting is offered for that kind. A value in a cell is
the default for a new type.

| Setting | Collection | Taxonomy | Tree | Profiles | Notes |
|---|---|---|---|---|---|
| URL prefix | key | key | key | `profiles` | Where its addresses start. Empty uses the key. |
| Visible on the site | on | on | on | on | Off: no pages at all. Sitemap and `llms.txt` are then off too. |
| Visibility (D-855) | on | on | on | on | Types with addresses only. Off: every entry is public and the editor hides it. |
| In the sitemap | on | on | on | on | |
| In `llms.txt` | on | **off** | on | off | |
| Feed (RSS, Atom, JSON) | yes | yes | no | yes | |
| Nesting under a parent | yes | yes | by folder | no | A collection's entries name a parent entry; a tree nests by folder. |
| Featured image | **on** | off | n/a | n/a | An image field. |
| Order | newest first | **by position** | n/a | n/a | Position, then title. |
| Date archives | none | none | no | no | None, or by year down to by second. Dated types give new entries a date. |
| File names | default | default | default | default | The slug; date and slug; date, time, and slug. Only when content is in files. |
| Folders | none | none | no | none | By year, by year and month, by first letter. Never part of an address. |
| Index page | optional | optional | its folder's | n/a | Given once and kept; it's an entry, edited like one. |
| Credits authors | **on** | off | yes | n/a | Only when the site has profiles. Joins the `authors` relationship. |
| Authors list page | optional | optional | no | n/a | |
| Byline | | | | | Which credit relationship is the byline, when a type has several. |
| Workflow | standard | standard | standard | standard | Offered only when the site has more than one workflow. |
| Entries can expire | off | off | off | off | Gives each entry an Expires date; past it, it's off the site. |
| Menu: sits under | top | top | top | n/a | Under one other top-level type. One level deep; a type with types under it stays top-level. |
| Menu: position | none | none | none | n/a | Lower first; without one, after those with one, by name. |

## 7. Addresses

Each type answers at a set of **route keys**, each with a path after the
prefix. Paths can be edited (unless `dataTypeUrls` is off):

- **Collection:** listing, later pages, entry, feeds, date archives
  (each level), and an archive for each relationship it files (an
  author's posts, a genre's movies).
- **Taxonomy:** the same, with each of its entries having a page and a
  feed.
- **Tree:** only its prefix; each entry is at its folder path under it.
- **Profiles:** one page per profile, `{prefix}/{slug}`.

Each path says which placeholders it needs and which it may hold
(`{slug}`, `{year}`, and so on). The server checks them on save. An
empty path is the default. Each row shows the whole address.

## 8. Fields and field sets

- **Its own fields:** each with a name, a label, a field type (text,
  Markdown, date, yes/no, number, choice, list, media, slug, group),
  required or not, and settings by field type. Fields can be grouped
  and nested in lists.
- **Reserved names:** `id`, `refs`, and the built-in fields can't be
  used.
- **Field sets:** shared groups of fields added to several types. A set
  is added to types on the set's own screen (Structure → Fields); the
  type's screen only lists the sets it has.
- A type from code whose fields are classes in code shows them
  read-only.

## 9. Relationships

Every relationship a type takes part in, from either side, as a
sentence from the type's side ("Credits **Profiles** as Cooks", "Linked
from **Posts** as Mentions"). Kinds:

- **Classify:** files entries under a taxonomy's entries (a post's
  categories). New ones can be typed in. Each taxonomy entry's page
  lists what uses it.
- **Credit:** the profiles an entry credits (authors, cooks), in order,
  the first being the lead.
- **Reference:** any other entries (a movie's actors).
- **Parent:** nesting within the type (from Nesting, §6).
- **Translation:** built in; not edited.

A relationship stored on this type (its entries carry the key) is
edited on its own screen, linked from here. One stored on another type
links to that type. One from code or a plugin shows where it's defined
and can't be edited. **Add Relationship** starts one stored on this
type.

## 10. Deleting and resetting

- **A type made by the site:** Delete removes the type. **Its entries
  stay where they are**, as files without a type. Delete is refused
  while a relationship names the type; the refusal lists the
  relationships to remove first.
- **A type from code:** no Delete. **Reset** removes the site's changes,
  so it's as the code defines it.
- **Pages and Profiles:** neither.

Both are in a danger zone and ask first.

## 11. Capabilities

Each type has its own: create, edit, publish, delete, and edit, publish,
and delete others' entries (`content.{key}.{action}`). A role can grant
an action for **every type** (`content.*.edit`), which covers types
added later. A role that names types one by one gives a new type
nothing until it's added to the role. The built-in roles use every
type.

## 12. For design to work out

- **The Taxonomy choice in the wizard.** It sits beside Collection and
  Tree but is a collection with a relationship. How much of that a
  person needs to see.
- **What a taxonomy's entries are called** in the admin's text (the
  addresses list says "Term" today).
- **Settings that don't apply.** Hidden, or shown off with a reason.
  §6 has many cells that differ by kind and origin.
- **Locks.** How a type from code, a read-only core type, and the
  config locks read, without looking broken.
- **What changing a setting won't do.** The "renames nothing, Site
  Health fixes older files" story, for file names, folders, and
  addresses.
- **Deleting a type whose entries stay.** Where those entries show up
  afterwards, and how a person finds them.
