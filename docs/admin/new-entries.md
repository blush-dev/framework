# New entries and parents

**New Post** (named for the type you're looking at) starts an entry. A
page, or an entry of any tree, can go under another, and move later.

## Starting an entry

**New Post** opens the editor on
a new post, with the cursor in the title. Nothing is written until you
save: the first save creates the file, named for the title (or the slug,
if you give one in the settings), as a draft unless you publish or
schedule it. A post needs a title to be saved. It's credited to your
account's author. If you leave before saving, nothing is created.

## Choosing a parent

A new page (or an entry of any [tree](../concepts/trees.md)) can go
under another: choose its **Parent** on the Document tab before the
first save, or leave it at **None, at the top level**. The page is
saved in its parent's folder (`about/team.md`), and its address follows
(`/about/team`). If the parent is a single file (`about.md`), it moves
into the folder first, as `about/index.md`; its address stays the same.

**Position** (under Parent, for pages and for collections ordered by
position or nesting, such as terms) sets its place among
its siblings: lower numbers first, and those without one after them, by
title. A type's **All** tab and the Parent list follow it; term
filters and pickers stay alphabetical.

## Moving a page

To move a page later, choose another **Parent** (or **None, at the
top level**) and save. The pages under it move with it, and a parent
kept as a single file moves into its folder the same way. A page can't
go under itself or a page under it, or where another page has its slug.
If the page is live, its address changes, so **Redirect the old
address here** is offered, as for a [new slug](editor/slug.md); with it on, the old
address of every live page that moved (the page and those under it)
redirects to the new one. Renaming a page's slug does the same for the
pages under it.
