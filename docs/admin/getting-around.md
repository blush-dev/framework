# Getting around the admin

The admin is a rail of sections, a panel listing the section you're in,
and a top bar that says where you are.

## The rail and the panel

The rail at the far left has seven sections: **Home** (the dashboard,
Site Health, and your shortcuts), **Content** (each content type's entries, with
the types of terms that file only it under it, the terms several types
share, and media), **Structure** (content types, relationships, fields,
and menus), **Extend** (themes, plugins, and icon packs), **Users**
(your profile, accounts, profiles, and roles), **Tools** (tools,
jobs, logs, activity, redirects, and backups), and **Settings** (the
settings screens). The panel
beside it lists the section you're in. Choosing a section changes the
panel and nothing else, so you never leave the screen you're on (an
entry you're writing stays open); choose a link in the panel to go
there. Choosing the section that's already showing hides the panel,
leaving just the rail (your browser remembers it), and choosing it
again brings the panel back. You only see what your account can use.
A link with others under it, such as a content type with the types
that sit under it, has a chevron at its end that shows or hides them.
Your browser remembers which you've closed, for your account, and
going to a link under a closed one opens it again. Site Health shows
how many of its checks need a look.

**Content** has every type, terms too, alphabetically by name until you
[place them](../concepts/content-types.md) (**In the Menu** on each
type's screen), with any types you've put under another type nested
under it, then **Media**. **Structure** has **Content Types**,
**Relationships**, **Fields**, and **Menus**. Profiles are in
**Users**, with accounts, since they're the public side of accounts.
Types are named from their `labels` setting, and shown with their
`icon` (see [Content types](../concepts/content-types.md)).

**Users** has Your Account, Accounts, Profiles, and Roles.
**Settings** has General, Reading, Writing, Media, Email, Addresses and Search,
AI, API, and System, and **Extend** has Themes, Plugins, and Icon
Packs. You only see the
screens your roles allow: Media needs one of the media capabilities
(see [Accounts](../concepts/accounts/README.md)), Accounts and
Roles `accounts.view` (and New Account `accounts.create`, New Role
`roles.manage`), Profiles editing profiles, each content type
editing its entries,
Content types and Settings `site.settings`, and Themes, Plugins, Icon
Packs, and Admin Themes seeing that kind (`extensions.themes.view`, and so on; see
[Capabilities](../concepts/accounts/README.md)). On those screens, activating
a theme, turning a plugin, pack, or admin theme on and off, or choosing
the site's admin theme needs the kind's `activate`, and deleting its
`delete`; without them, the buttons aren't shown.

## Shortcuts

**Shortcuts**, under Home's own screens, are the screens you pin there,
in your order: Your Account until you choose your own.
Choose **Edit** to move them up or down, remove them, or **Add a
Shortcut** from any screen the panel shows you, then **Done** to save
your changes. They're kept with your account, so they follow
you to any browser.

## The top bar

The top bar says where you are: the section, then the screens above
this one, then this one, such as *Content / Posts / Editing* or *Config
/ Content Types / Pages*. The section's name shows its panel, or hides
the panel when it's already showing, like the rail; the screens before
the last go back to them.

**Search or jump to…** in the top bar (or ⌘K, Ctrl+K on Windows and
Linux) opens the command palette: type to find a screen, a command such
as **New Post**, or an entry by its title, then press Enter. In the
editor, the editor's own commands come first, such as **Focus mode**
and **Insert media**.

The editor hides the panel while you write and puts it back as it was
when you leave. On a narrow screen, the button at the top left opens
the rail and panel as a menu instead. **View Site** opens your site in a new tab, and
the round button at the top right has **Your account** and **Sign out**.
