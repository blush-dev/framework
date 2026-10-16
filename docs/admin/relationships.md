# Relationships

With `site.settings`, **Structure → Relationships** lists every
[relation](../concepts/relationships.md) on the site: the
place they're made and edited, since a type's screen shows only its
side.

## The list

Tabs narrow it by purpose (**Credits**, **Files Under Terms**,
**Links**), beside a search over names and keys, a type filter that
matches either end (everything that points at Profiles, and everything
Recipes points at), and a source filter. Each row has its name (its
key under it), what it **Connects** (the type that stores it, then the
one it points at), its kind, its **Source**, and how many entries have
a value in it. A relation from a plugin is listed, but changed where it's defined; one still written as a
taxonomy is marked, with **Migrate** going to
[Site Health](site-health/checks.md#taxonomies). **New Relationship** opens a new one.

## A relationship's screen

A relationship's screen is rows of settings in panels, as the Settings
screens are. The sentence under its name says it whole ("Recipes
credit Profiles as Cooks."), and changes as you change it.

- **Purpose:** **Files Entries Under Terms**, **Links Entries to Other
  Entries**, or **Credits People**. It's chosen when it's made.
- **Endpoints:** **Stored On**, the types whose files carry its key
  (for terms and credits, none chosen means every type), and what it
  points at: the **Taxonomy** (one nothing files under yet), a type
  of entries, or, for credits, the profiles.
- **Names:** the **Front Matter Key** (`authors` for a new credit
  unless you change it), what it's **Called** in the editor, such as
  "Cast", and what **One Is Called** ("Add a cook").
- **Options:** whether each entry takes **Several** or **Exactly One**;
  whether their **Order Matters** (the first is the lead); whether
  they're **Created as Typed**; and, for a link to its own type,
  whether a link counts **Both Ways**.
- **Limits:** how many an entry needs to publish (**At Least**), the
  most it takes (**At Most**), and the most entries that may point at
  one target (an episode in one season), restated as a sentence under
  them. They're checked when an entry is published; a draft always
  saves.
- **Editing:** the **Picker** (chips, cards, or, for terms that nest, a
  tree; see [Choosing relations](editor/relation-pickers.md)) and which
  links a translation uses: **Fall Back** to its
  original's, **Add to the Original**, or **Keep Their Own**.
- **What Links to It:** what the list is called on the other side, such
  as "Acted in"; whether it's on the target's **Own Page**; and whether
  it has an **Archive**, under a word (see
  [Relationships](../concepts/relationships.md)).

Opened from a type's screen, a new one is stored on that type, and
saving goes back there. It's saved in
`user/data/relations/{name}.json`. A relation can't take the name of
one defined in code. **Remove Relationship**, at the foot, removes it.

## Changing a relation in use

Changing a relation entries already use says what it does before it
does it, with the default the choice that changes no files:

- It can't **point at another type** while entries have values in it,
  or go from **several to one** while an entry has more than one. It
  says so, lists the entries in the way (most first, up to 8), and
  offers to keep the setting as it was; **Show All** opens the type's
  list with only those entries.
- A **new key** keeps the old one working as another name for it, so no
  file changes, unless you check **Rewrite All Files Now**.
- **No longer filing a type** keeps those entries' values in their
  files, unread, unless you check to remove them.
- **Tighter limits** list the entries they put over, which keep their
  values and can't be published again until they're within them.
  Nothing in any file changes.
- **Remove** says how many entries have values, and offers to strip
  them from their files, unchecked at first: kept, adding the
  relationship again brings the links back.
