# Entries

Each content type in **Content** opens a list of its entries you can
edit, newest changes first. Click a title to edit the entry.

## Tabs

The tabs above a list show all of them, or only the entries of one
status of the type's [workflow](../features/workflows.md)
(published ones first, then drafts and any others, such as In
Review), or scheduled ones, or, for a type with expiry, expired ones,
with a count on each. For a type that credits
authors, when your account has a profile, **Mine** beside **All** shows
the entries crediting you. You see your own
entries if you're an author or contributor, and everyone's if you're an
editor; in Profiles, your own profile is marked "You". The **Trash**
tab is on its own page: see [Trash](trash.md).

## Order and the tree

On the **All** tab, a collection's entries come newest first by date,
pages and the entries of a collection ordered by position or nesting
(such as terms) by their **Position** and then by title (those without
one after the rest), and profiles by name; the date column shows the
date the list goes by. Drafts come most recently changed first,
published entries newest first, and scheduled entries in the order
they'll go live.

Pages, and the entries of a
[nesting collection](../concepts/relationships.md) (such as
categories), list as a tree on the **All** tab when you aren't searching: each one
followed by the ones under it, indented, in the same order (Books, then
Book Reviews indented under it, then Film). The triangle
beside an entry with others under it collapses or expands that branch;
the admin remembers which until you close it. When a later page starts
partway through a branch, the entries above it are shown again at the
top, marked **Continued**. On another tab, in a search, with a filter,
or sorted by a column, the tree is flattened, and a note above the list
says how to get it back.

Under each title is the entry's address on your
site (for a draft, the address it will have). In other tabs and in
search results, a page or an entry of a nesting collection has the
titles of the entries above it before its own (such as "Web › Web
design › CSS").

A type of terms' list (such as Categories) holds its **terms**. Instead
of authors, it shows how many published entries use each term.

## Filters

Beside the tabs is a row of filters:

- **Search** matches titles and slugs. Press `/` anywhere on the
  list to start typing in it.
- **Author** shows only the entries crediting one author (not on a
  list of terms, since terms aren't credited).
- One select for each type of terms the type is filed under, such as
  **Any topic**, shows only the entries filed under one term.
- **Updated** shows only the entries changed in the last 7, 30, or 90
  days.

The Author and term selects offer only the authors and terms this
type's entries use (drafts included), so a choice never comes up empty.
A filter with nothing to offer isn't shown.

**Clear Filters** turns them all off, and the tabs' counts follow the
filters. The Trash tab takes only the search. The two buttons at the end
of the row switch between roomy rows and compact ones, which leave out
the address line; your browser remembers the choice.

## Sorting and paging

Click the **Title**, **Status**, **Authors**, or date (**Published** or
**Updated**) header to sort by that column, and again to turn the order
around. Titles, statuses, and authors start from A, and dates from the
newest. Below a list longer
than 10 entries, choose how many show on a page (20 unless you change it).
The filters, the sort, and the page size are part of the page's address,
so going back or sharing the link keeps them.

## Changing several entries

To change several entries at once, tick their checkboxes (the box in the
header ticks every entry on the page). A bar appears at the bottom with
how many you've chosen and what you can do to them: **Publish** (if your
account can publish), **Move to Draft**, and **Move to Trash** (if it can
delete). **Clear** unticks them all, and so does moving to another tab,
filter, sort, or page. Publishing dates an undated entry now, as it does
in the editor, and leaves out any entry with a required field empty. A
message at the bottom right says how many changed, and a notice above
the list names any that didn't, with why. The index page can't be ticked: its pin sits where the
checkbox would be.

## A row's menu

The **⋯** button at the end of each row has **Edit**, then **View** and
**Copy link** once the entry is live (**View archive** for a term),
**Duplicate**, and **Move to trash** if your account can delete it.

**Duplicate** copies the entry beside the original as a draft titled
"… (Copy)", with the original's slug plus `-copy` (then `-copy-2`, and so
on, if that's taken). Everything else is copied as it is: the body, the
authors, and the other front matter, except its own `slug` (the copy is
named by its file); a dated entry is dated today, and
an entry in its own folder is copied with its media. A message at the
bottom right says so; the copy is listed with the drafts. Terms and index pages can't
be duplicated.

## An empty type

A type with no entries yet skips the tabs and search: it says what the
type is for (its `description`, if it has one) and offers to create the first one. A site that hasn't
published anything shows a [setup path](dashboard.md#the-setup-path) on
the dashboard.

To start an entry, see [New entries and parents](new-entries.md).
