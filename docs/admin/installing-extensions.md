# Installing extensions

The Extend screens install themes, plugins, icon packs, and admin themes
from a `.zip`, and an extension's details list what it depends on and
what it says about others.

## Installing from a zip

**Install Theme** takes a `.zip` of a theme's folder: drop it anywhere
on the modal, or choose it. The modal shows it being sent, then what was
installed, with **Activate** as the next step; a theme that's already
installed is offered for replacing, with both versions named; and a zip
that can't be installed says why, with nothing written. A zip of a
plugin or icon pack offers that screen instead. See
[Installing extensions](../concepts/installing-extensions.md) for what's
checked. **Install Plugin** and **Install Icon Pack** work the same way,
with **Turn On** as the next step.

After a replace, the details screen
of a theme, plugin, or pack has the version that was kept, with **Roll
Back** and **Discard** (see
[Installing extensions](../concepts/installing-extensions.md)).

## What an extension's details list

- An [abandoned](../plugins/creating-a-plugin.md) theme, plugin, or pack is marked
  **Abandoned** on its card, and its details say its author no longer
  maintains it, naming the package to use instead (linked, when it's
  installed); it still works, and installing one says so too.
- What an extension [suggests](../plugins/creating-a-plugin.md) is listed on its details,
  under **Suggests**, and installing one lists it too.
- Its [conflicts](../plugins/conflicts.md) are listed under **Conflicts**, and
  what it [replaces](../plugins/replacing-extensions.md) under
  **Replaces**, each checked against what's on, and what it
  [provides](../plugins/composer-packages.md) under **Provides**.
- Its details also name the extensions that conflict with it, replace it, or
  also provide it ([the other side](../plugins/replacing-extensions.md)),
  and turning one on (or activating a theme) asks first when it would stop
  others, naming them.
