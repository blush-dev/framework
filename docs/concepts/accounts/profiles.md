# Profiles

An account is someone who can sign in. Their public side is a separate
thing, a **profile** (see [Content types](../content-types.md)): the
name in bylines, a bio, and a page on the site. An account can be
linked to one: `--author=jane` links it to
`user/content/_profile/jane.md`. The link is kept by the profile's
id, so renaming the profile keeps it. A profile belongs to one account:
linking one that another account has is refused (unlink it there
first). A profile with no account is a guest profile, and an account
with no profile doesn't appear on the site.

A profile no one should sign in as, such as an organization's byline
or someone who has died, can be **locked**: `linkable: false` in its
front matter, or the **Can be linked** switch on its screen in the
admin. Linking an account to a locked profile is refused, from the
admin and the CLI alike, until it's unlocked. An account already
linked to it keeps it.

The account itself (username, password, roles) stays private. When
there's no profile at the slug yet, `account:add` and `account:author`
make one, a draft, and ask for its public name; publish it in the admin
when it's ready. In the admin, an account's **Public Profile** panel
links, unlinks, creates, or publishes its profile, and **Your account**
links to your own (see [The admin](../../admin/README.md)). Unlinking
leaves the profile and its bylines in place.

Entries that credit an account's profile are its **own** (through the
type's [byline](../credits-and-profiles.md)), and so is the profile
itself. [Roles](roles.md) decide what an account can do with its own
entries and with everyone else's. An account with no profile owns
nothing, which suits someone who only runs the site.
