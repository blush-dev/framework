# Profiles

**Users → Profiles** is the profiles type's list, like any type's
[list](../entries.md), and each profile has a screen of where it
appears.

## The list

Each row has a
**Name** (beside its initials, dashed for a guest), its **Status**, the
**Account** linked to it (a **Guest** tag without one, **Locked** for a locked one), how many
published entries credit it (**Bylines**), and when it was updated.
Besides the usual filters, **Any account** shows only the profiles
linked to an account, or only guests. **New profile** starts one.

You see the profiles you may edit: your own, or anyone's with
`content.profile.edit.others` (or the type's name on your site).

## A profile's screen

A name opens the profile's screen:

- **All profiles** above the name goes back to the list. The header has
  its address, status, and bylines, with **View**, **Publish** (for a
  draft), **Edit Profile** (the editor, where the title, the byline
  title, the avatar, and the bio are written), and a **⋮** to unlink
  or link an account, or move the profile to the trash.
- **Identity** shows what a byline renders: the display name, slug,
  **byline title** (shown under the name), and the avatar.
- **Linked Account** shows the account (at most one), its standing and
  last sign-in, with **Open Account** and, if you manage it,
  **Unlink**. Unlinking leaves the profile and its bylines, as a guest
  profile, and the account goes by its username. A guest profile can
  be linked here to an account that has no profile. If you can link
  accounts, a guest profile also has a **Can be linked** switch:
  turning it off locks the profile (`linkable: false` in its file), so
  no account can be linked to it, and it shows as **Locked** on the
  Profiles list and can't be chosen when linking from an account.
- **Where This Profile Appears** lists the profile's own page, then each
  credit relation of each type that credits people: its archive address,
  how many entries credit them there, and where the archive's body
  comes from: **Inherited** (the profile's own) or **Written** (a page
  written for that archive). **Write One** creates that page, a draft
  titled with the profile's name, and opens it; **Edit** opens it, or
  **Move to trash** puts the archive back on the profile's body (the
  page can be restored from its type's Trash tab). A credit whose archive is off
  says so, and a page written for it shows **Unreachable**. Each row
  counts the entries crediting the profile that way.
