# Activity and Undo

Every change to entries and media is listed under **Tools → Activity**,
whoever made it and however: in the admin, by a connected
[AI app](README.md), on the command line (`content:new` and
`bin/blush mcp`; a command that names no account is listed as
**Someone on the command line**), or through the
[API](../api/README.md). Each row says what happened, who did
it, how (an app's changes show its name), and when, and has **Undo**
where it can be undone. Filter by who, how, and what changed.

- **Undo** puts back what the change replaced, only while nothing has
  changed since. An undo is a change of its own, listed like any other.
- A new entry is undone by moving it to the trash, and an upload by
  deleting the file.
- What a change replaced is kept for 30 days; the change stays listed
  for 90.

Choose a change to open its own screen: **What Changed** compares its
fields and body before and after, with the lines it removed marked −
and those it added +; **Details** says which entry or file, who, how,
and when; and **Other Changes** lists the rest of that entry's or
file's changes. A new entry or upload has nothing before it to compare,
and a change can't be compared once what it replaced is gone (after 30
days), or when the file was changed outside the admin since.

An entry's own changes are on its **Document** tab, in the **Activity**
row, which says when an app made the latest one. Seeing all of the
activity takes **See activity and undo changes** (`site.activity`),
which the Owner, Administrator, and Editor have; anyone may see and
undo the changes to an entry they may edit.
