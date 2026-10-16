# Choosing relations

A field that points at other entries (terms, the authors, or any other
type, through a [relation](../../concepts/relationships.md))
is a picker rather than a list of slugs to type.

## Kinds of picker

- **Nesting terms**, such as categories, are their whole tree, each
  term with how many entries use it. Search to narrow it (a match's
  parents stay in view, dimmed) and tick the ones that apply; ticking a
  term doesn't tick its parent. A draft term says **Draft**, since the
  site doesn't list the entry under it until it's published. **New
  category** (named for the type) asks for a name and a parent and adds
  the term to your site, ticked; with nothing matching a search, it
  offers the search as the name.
- **Other terms**, such as tags, are chips. Click in the field to see
  the most used, or type to see matches, with each one's count. Enter
  takes the highlighted match; when nothing matches, **Create** adds
  what you typed as a new chip, marked with a plus, and the term is
  written when you save the entry. Backspace in the empty field removes
  the last chip. Past 12 chips, the rest fold into **+N more**.
- Adding a term as you type needs its relation to allow it (`create`)
  and your account to be able to create and publish that type's
  entries.
- **People**, such as authors or a recipe's cooks, are listed by name
  and slug, the first marked **Lead** when there are two or more and the
  order matters. Click in the search to see who was credited most
  recently, or search to add someone. When an entry must have one, the
  last can't be removed until another is added.
- **Other entries**, such as a recipe's pairings, are listed above the
  search with their image, type, and date; results show as cards, a
  chosen one ticked (choose it again to take it out). The entry you're
  editing is never offered, and at the limit the search is replaced by
  a line saying so.
- **One value**, such as a cuisine, a category's parent, or the one
  person who tested a recipe, is a value on the Document tab's first
  rows, like Status. Its list is indented to show the tree, a nested
  value shows its parent's name, and from 13 options it has a filter.
  A category's parent leaves out the category and the ones under it.

## On large sites

A field with more than 50 to choose from never lists
them all. Nesting terms show what's chosen, with each one's path
(`Mains › Pasta`), and the most used; a list of one value opens on a
search with the recently used. A search shows the best 8 matches (6
cards), those starting with what you typed first, and says how many
more there are: keep typing to narrow it.

A slug that nothing answers to is shown as written, and when one is
close enough to be a typo, the picker offers it: **Did you mean Tomato?
Replace**.

## What the relation's settings change

- **How many:** with a `max`, the field's heading shows how many of how
  many you've chosen ("2 of 3"), and it takes no more once it's full; a
  `min` says how many are needed to publish ("Needs 1"), and publishing
  waits for them. A draft saves without them.
- **Order:** when the order matters (`ordered`), drag a chip or a row by
  its grip, or focus it and press ⌥↑ or ⌥↓ (Alt with an arrow key).
- **Picker:** a relation can choose its picker with `control` (see
  [Relationships](../../concepts/relationships.md)).
- **Translations:** a translation shows what it uses from its original,
  dashed and named ("Also shown, from Pasta alla Norma (English)"): the
  original's when it has none of its own, or, for a relation that adds
  them (`translations: add`, such as credits), the original's beside its
  own.

## When the other side is full

If a relationship is full on the other side (a collection that features
12 recipes at most already has 12), publishing or updating is refused,
and a bar says which entry is full, with a button to open it in a new
tab. The field says so, and names another with room. A select lists
each choice's count against that limit ("7 of 12", "Full · 12 of 12"),
so a full one is seen before it's picked.
