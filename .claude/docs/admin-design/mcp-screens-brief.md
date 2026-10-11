# MCP: a design brief for new screens

> **Status:** built from the admin's shared pieces in D-759 (see
> `departures.md`, "The MCP screens"); a design for them is welcome.

For the design team. Blush is getting an MCP server (D-751 to D-758 in
the project's decision log): AI apps such as Claude and ChatGPT connect
to a site, sign in as one of its accounts, and read and write entries and
media with that account's capabilities. Two screens are entirely new; a
handful of existing screens gain a part. This brief says what each needs,
not how it should look. Build from the existing pieces (panels, rows,
page tabs, the sign-in card, toasts) wherever they fit.

Words, as the admin uses them: an **app** is the AI client a person
connects (Claude, ChatGPT, Cursor); **connecting** an app is approving
it; an **account** is who it acts as. Buttons are Title Case.

---

## 1. Connect an App (new screen)

**Where:** `/admin/authorize`, outside the shell, like the sign-in card.
A person never navigates here; their AI app sends them here, in a
browser, while they're setting up a connector. They arrive mid-task,
expecting one decision, and leave back to the app.

**What it's for:** deciding whether this app may act as you on this
site, and with how much of your access. It's a security decision made
by people who mostly aren't technical, so it has to be plain about who
is asking and what they'd be able to do.

**What it shows:**

- **The site** it's for (title, address), so a person with several
  sites knows which one they're approving.
- **The app:** its name and its address (the domain it came from).
  Some apps prove their address and some only state it: a stated one is
  marked as unverified ("says it's from claude.ai"). No remote logos
  (they'd be fetched from the app's servers); an initial or a generic
  mark.
- **Who it will act as:** the signed-in account (name, avatar), with a
  way to switch account (sign out and back in) for someone who's signed
  in as the wrong person.
- **What it's asking for,** as one of three choices, in plain words:
  - **Read:** read what you can see, drafts included. Change nothing.
  - **Write drafts:** read, and create and edit drafts. Never publish,
    trash, or change what's live.
  - **Same as your account:** everything you can do here, publishing
    included.

  The app's request is preselected. A person may pick a narrower
  choice, never a wider one (wider choices are shown but unavailable).
  A **Choose** option opens the account's capabilities as the role
  screen draws them (sections of switches), for someone who wants to
  pick exactly; again, only narrowing.
- **What to expect:** a line or two: the app works only through this
  site's MCP server, everything it changes is listed under Tools →
  Activity with Undo, and it can be disconnected any time from Your
  Account → API Tokens.
- **Allow** and **Deny.** Allow is the primary action. Deny returns to
  the app saying no.

**States:**

- **Not signed in:** the sign-in card first, then back here.
- **Can't connect apps:** the account lacks the `api.connect`
  capability. Say so, and that an administrator can give it; only a way
  back to the app (Deny's return), no Allow.
- **Suspended account:** as the sign-in screen says it today.
- **Something's wrong with the request** (an unknown app, a return
  address that doesn't match what it registered, a malformed request):
  an error card that names the problem in a sentence and never sends
  the person on to the app's address (it may be the attacker's).
- **Approved before** with the same or a narrower choice: no screen; it
  returns to the app at once.
- **Working:** after Allow, the button's spinner while it hands back.

**Notes:** this screen is the moment of trust; restraint helps. The
destructive-looking styles are wrong for Deny (it's the safe choice).
Phones matter: the browser tab is often opened from a phone app.

---

## 2. Tools → Activity (new tab)

**Where:** a third tab on Tools, beside Actions and Log, for accounts
with the new `site.activity` capability (owners, administrators, and
editors by default). Also reached from a connected app's row in API
Tokens (filtered to that app).

**What it's for:** "what changed, who did it, and can I take it back?"
Every write to entries and media is recorded, whether it came from the
admin, an AI app, the command line, or the API. It's what makes letting
an app write feel safe.

**What each row shows, newest first:**

- **What happened, as a sentence:** "Updated the body of *Sourdough
  basics*", "Published *Spring menu*", "Uploaded *crust.jpg*",
  "Changed alt text on *crust.jpg*", "Moved *Sourdough basics* to
  Trash". The entry or file links to its editor or detail screen.
- **Who:** the account's name.
- **How:** Admin, the app's name ("Claude"), Command line, or API, as a
  quiet tag, so an app's changes are easy to pick out in a list of a
  person's own.
- **When:** relative ("3 minutes ago") with the exact time on hover.
- **Undo,** on rows that can be undone (edits, status changes, media
  edits, and trash), for anyone who may edit that entry or file.

**Filters:** by account, by how (Admin, each app, Command line, API),
and by action (created, edited, status, trashed, uploaded). A filter
from API Tokens arrives already set.

**States:**

- **Empty:** nothing has changed yet (rare; an empty state).
- **Undo isn't possible:** something changed it since ("Changed since;
  open it to edit"), its saved state has expired (kept 30 days; rows
  stay 90), or it was a creation or upload (undone by trashing or
  deleting). The row says why in place of the button, not in a toast.
- **After Undo:** the row stays, marked undone; the undo is a new row
  of its own at the top. A toast confirms.
- **Paging:** a page of rows and more on request, as other long lists
  do.

---

## Parts added to existing screens

Not new screens, but new parts a designer should see in context:

- **Settings → AI, an MCP Server group:** a switch (off by default); the
  server's address as a read-only row with **Copy** (what a person
  pastes into an app's connector settings); a line on who may connect,
  linking to Roles. Below, **Tools Offered:** a read-only list of what a
  connected app can be asked to do, grouped by who added it (Blush,
  then each plugin, as Tools groups its actions), each with its name,
  description, and the capability it needs.
- **Your Account → API Tokens, Connected Apps:** one row per app, not
  per token: its name, the choice it was given (Read, Write drafts,
  Same as your account, or Custom), when it was approved, when it last
  connected, a link to its Activity, and **Revoke**. Revoke All covers
  apps too. Administrators see the same on other accounts' screens.
- **The editor's Document tab, an Activity row** pinned to the end with
  Archive Page, Linked From, and Outline (D-608's pattern), opening a
  level down to the entry's last changes, each with Undo as on Tools.
- **The editor, a note when an app made the latest change:** "Changed by
  Claude (Jane's account) 3 minutes ago", so a person opening the entry
  knows an app was there. Quiet, not a warning; it links to the
  entry's Activity.
- **Roles:** two new capabilities, Connect Apps (`api.connect`) and
  View Activity (`site.activity`), in their sections as any other.

## Open for the designers

- Whether Connect an App should show the site's own mark (a theme's
  logo) or stay plain Blush.
- How the **How** tag tells apps apart when a site has several
  connected (by name alone, or a mark per app).
- Whether the editor's note belongs in the header or the Document tab.
