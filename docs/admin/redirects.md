# Redirects

With `site.redirects` (administrators and editors), **Redirects** in
**Tools**, after **Activity**, lists the site's
[redirects](../concepts/redirects.md): old addresses that send visitors
somewhere new.

## The list

The tabs are **All**, **Permanent**, **Temporary**, and **Problems**
(while there are some), each with its count. Search the old and new
addresses and the titles of the pages they lead to, filter by where
they go (this site or another), and switch to compact rows. Each row
shows **From**, **To**, the **Type** (in words, with its code beside
it), and when it was **Added**, by which account and how: **Added**, **Renamed**,
**Moved**, or **Imported by** the account's name (linked to its account). **To** says what kind of place it is: a page by its
title, with its address under it; a path; or another site, its host in
bold with an arrow out.

**Search is also a test.** Type or paste an address (a path, or a whole
link to the site) and the list shows the redirects that handle it, with
a line at the top that follows it as a visitor would: an arrow and the
status code for each redirect (hover one to see which redirect it is),
then where it ends up, a page, another site, or **Not Found**. Not Found
offers **Add a Redirect From Here**.

## Adding and editing

**Add Redirect**, or **Edit** in a row's menu, opens a small form:

- **From:** a path on this site. `{name}` matches one part of it and
  can be used again in To. A whole link to the site is saved as its
  path.
- **To:** a path, a whole address on another site, or part of a page's
  name, which lists matching pages to pick from. A picked page shows
  in the box, and the redirect follows it if it moves; × (or
  Backspace) clears it. A typed path that's a page's address becomes
  that page.
- **Type:** **Permanent** (301) or **Temporary** (302), or one of the
  three under **For Programs**: See Other (303), and Temporary and
  Permanent, Same Method (307 and 308), each with what it does.

As you type, the form says what it will do, and what stops it is a red
line under its field: a path without its `/`, another redirect from the
same address, a placeholder in To that From doesn't have, or a redirect
that would loop. A redirect that only leads to another redirect offers
**Go Straight There**. Changes save as soon as you press Save.

Renaming or moving a live entry in the editor can add a redirect for
you (see [Changing the slug](editor/slug.md) and
[New entries and parents](new-entries.md)).

## Problems

A row with a problem has a chip beside its old address; press it for
what's wrong and its fix:

- **Does nothing:** a page answers the old address, so the redirect
  never runs. **Delete Redirect**.
- **Leads nowhere:** the page it leads to was deleted, is in the trash,
  or isn't published. **Choose Another Page**.
- **Missing page:** nothing answers where it leads. **Change Where It
  Goes**.
- **Chained:** where it leads redirects again. **Point Straight There**.
- **Overruled:** the site's code redirects the same address first.
  **Show It** lists the code's redirects, which are under the table and
  can't be changed here.

Rows with problems keep working as far as they can.

## Row actions

A row's menu also has **Test this address**, **Copy old address**, and
**Make temporary** (or permanent). Select rows to make them permanent
or temporary, or delete them, together. Every change has **Undo** in
its message.
