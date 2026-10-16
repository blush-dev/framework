# Accounts

**Users → Accounts** lists the people who can sign in, and makes new
accounts. Each account has a screen of its own.

## The list

Accounts are listed by name, with tabs for
their standing (All, Active, Invited, Suspended) and, below them, a
search, a role, and whether they have a profile. Your own account,
marked **You**, is pinned at the top when it's in the list. Each row shows the
display name and username (an account with no profile is in a dashed
circle), the email address (**No email** for one made before they were
asked for), roles, its **Profile** (its name, with its status when
it isn't published yet, or the slug when it's linked to a profile with
no file), and the last sign-in; its **⋮** opens the account or its
profile, copies the email address, or makes a password link.
Token-only accounts are marked **Token-only** in the list.

**New Account** makes one; [**Roles**](roles.md) lists each role with its
description, the content types it reaches (every type, some, or none)
and how many site capabilities it has, and how many accounts hold it.

## A new account

**New Account** asks for a username, an email address (required, and
no other account's), an optional display name, its roles (**Member**
is ticked to start, and stays the only choice if you can't give
roles), and its profile: none, **Create a new profile…** (a display name and a slug,
made as a draft and linked), or one of the profiles no other account
has. A note says what the account will be called.

Blush doesn't send email, so instead of a password the
account gets a **password link**: copy it from the account's screen
and send it however you like. It's shown only that once, and it
works once, for a week. Until it's used, the account is **Invited**.

Choose **Token-only** instead for an app, script, or agent: a
[token-only account](../../concepts/accounts/README.md) never signs in,
has no profile or password link, and needs no email address; if you
can edit accounts, its first API token is made with it (a name, how
long it lasts, and what it can do) and shown once on its screen.

## An account's screen

- An account's screen has **All accounts** above its name, then its
  username, standing, roles, and last sign-in. **Edit Details** changes
  its display name and email address (a new address changes at once,
  and the old one is told). Tick or untick its **roles**,
  then **Save Roles** (or **Discard**); unticking the last one leaves
  the account a **Member**. An account with no email address says so,
  with **Add an Email Address**.
- **Public Profile** shows its profile: linked
  (**Open Profile**, **Unlink**), linked but not yet public
  (**Publish**), or none. With none, **Link an Existing One** opens a list of the
  profiles no other account has (a profile belongs to one account) to
  pick from, and **Create One** asks for a display name and slug, makes
  a draft, links it, and
  opens it.
- **Actions** has **Make a password link**, for a forgotten password:
  the person chooses a new one with it. Their old password keeps
  working until the link is used, and a new link replaces the old one.
  It also suspends an account (it's signed out and can't sign in until
  you **Reinstate** it) or deletes it. Deleting an account leaves its
  profile (as a guest profile) and the entries crediting it alone.
- **API Tokens** and **Connected Apps**: see
  [API tokens and connected apps](api-tokens.md).

What you may change here has limits: see [What you can't do](limits.md).
