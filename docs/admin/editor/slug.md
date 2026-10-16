# Changing the slug

The **Slug** field on the Document tab is the last part of the entry's
address, such as `hello-world` in `/archives/2026/09/30/hello-world`.

Change it and save to rename the entry: its file is renamed (a dated
file keeps its date), or, if the file sets its own `slug` in its front
matter, that is changed instead. Slugs are lowercase letters, numbers,
and hyphens, and another entry of the same type can't already have it;
if the name is refused, nothing is saved and the field says why.

For a published entry, the field shows the new address, and **Redirect
the old address here** (on by default) adds a
[redirect](../redirects.md) from the old address to the entry, so
links to it keep working, wherever it moves later. Renaming a page's
slug does the same for the pages under it.

Landing pages (the homepage, and a folder's `index.md`) take their
folder's name, so they have no Slug field.
