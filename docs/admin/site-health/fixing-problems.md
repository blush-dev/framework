# Fixing problems

Each Content and Media issue in [Site Health](README.md) opens a screen
of its own, where it's fixed: **Content Files**, **Entry IDs**, **Terms
and Profiles**, **Parent Pages**, **Links Between Entries**,
**Collection Folders**, **File Names**, **Taxonomies**, and **Type
Folders**; **Media Details**, **Media IDs**, and **Image Sizes** (see
[Content and media checks](checks.md)).

## Checking again

Each screen shows the last check, with when it ran, and **Check Again**
checks every content and media file again, which updates Site Health
too. It reads the files in the background, a couple of hundred at a
time (content files, then media details), and its button shows how far
along it is; **Run a Check** on Site
Health does the same for the files, after checking the rest at once.

## Rows and groups

Each screen lists everything its check found, one row per problem.
Problems of one kind are grouped under a heading that says what the
site does about them, such as **Dates That Aren't Real** or **No ID,
or One That Isn't Valid**. A row says which entry (its title, then its
file), what was found, and what the site does because of it.

- **Fixing one row:** a row Blush can fix has a button named for what
  it writes, such as **Add an ID** or **Create Profile**. It changes
  that file at once. The row stays where it was, marked **Fixed**, until
  you check again. Problems Blush can't fix for you, such as a value
  that doesn't fit its field, have **Open in Editor**. A row's menu
  (**…**) opens the entry and copies its file's path. **Remove**, on a
  key nothing declares (**Fields Nothing Reads**), takes every such key
  out of the entry's file, so it asks first even for one row. **Reset**,
  on an entry whose status isn't in its type's workflow, or that
  nothing registers (**Statuses Outside Their Workflow**), moves it to
  the workflow's first status, such as Draft; it asks first too, since
  an entry on the site comes off it. A status a plugin that's off
  registered comes back when you turn the plugin on.
- **Fixing a group:** when every row in a group would get the same
  kind of change, its heading has a button for all of them, such as
  **Give 12 Files New IDs**. It asks first, listing the files and what
  each gets. Groups whose rows each need a choice, such as which file
  keeps a shared id, are fixed a row at a time. A group fix runs as a
  [background job](../../running/cron-and-jobs.md), a hundred
  files at a time, and its button shows how far along it is; on a large
  site, if you leave the screen, it finishes on its own (you'll find it
  under [**Tools → Jobs**](../jobs.md)).
- **Ignoring:** a warning or notice you've decided to leave can be
  ignored from its row's menu (**Ignore This**). It's ignored for
  everyone on the site, moves to the **Ignored** tab, which says who
  ignored it and when, and stops counting here and in Site Health.
  **Stop Ignoring** brings it back. Errors can't be ignored, since the
  site is already leaving something out. An ignored problem that's
  fixed some other way is forgotten, so if it comes back, it's seen
  again. Blush keeps them in Site Health's own settings,
  `user/data/settings/health.json`.
- **Finding rows:** the tabs say how many problems need attention, how
  many are notices (on screens that have them; notices change nothing
  on the site), and how many are ignored. Search titles and files
  (press `/`), narrow to errors or warnings, and switch between roomy
  and compact rows. On Content Files and Media Details, **Group by
  file** shows each file once with all its problems. A group shows 5
  rows; **Show 50 More** shows more.

Every fix changes only files you may edit. One problem is reported by
one check: a missing id is under Entry IDs, not Content Files too.
