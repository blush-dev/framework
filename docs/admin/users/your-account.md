# Your account

**Your account** is your own account's screen, at its own address
(`/admin/accounts/{your username}`; `/admin/profile` goes there). It's in
the round button at the top right, and under **Users**.

It's the same screen an administrator sees from [**Accounts**](accounts.md),
with two differences. Your
password, email address, display name, and the admin's look are yours
to change, and your roles and standing aren't (someone else who
manages accounts changes those). While the site has no
[owner](../../concepts/accounts/README.md), anyone who can do all an Administrator
can sees **Make Me the Owner** here.

## Your details and profile

It shows your username, email address, display name
(what the admin calls you), when the account was made and last signed
in, your roles, and your **Public Profile**: the
[profile](../../concepts/credits-and-profiles.md) your account is linked to,
your public name and bio on the site.

**Change Name or Email** changes
your display name (left empty, the admin uses your profile's title,
then your username) and your email address, which every account needs.
Where the site can send email, a new address is used once you confirm
it, with the link emailed there; until then, a notice says what's
waiting, with **Cancel Change** (see
[Accounts](../../concepts/accounts/README.md)).

**Open Profile**
shows the profile's screen, where **Edit Profile** opens it in the
editor like any entry; its slug can't change, since your account is
linked by it. When your account is linked to a profile with no file
yet, **Create it** starts it as a draft (publish it to show your name)
and opens it. If you can edit accounts (`accounts.edit`), you can also
unlink your profile, link an existing one, or create one, as on anyone's
account.

## Theme and color scheme

**Theme and Color Scheme** sets how the admin looks to you. The theme is
one of the [admin themes](../../admin-themes/README.md) that are on, such as
**Neutral** (cool gray with a blue accent) or **Library** (cool
parchment, a bottle-green accent, and serif titles), each drawn as a swatch of itself. The
one marked **Site default** is what you see until you choose another,
and choosing it follows the default when it changes. The color scheme is
light, dark, or your device's setting (the default). Each choice is saved with your
account, so it follows you to every device you sign in on, and it only
changes what you see: someone else on the same site keeps their own.

## Language

**Language** sets the language the admin is shown in: the site's (the
default), or one the admin has text in, each named in its own
language. Like the theme, it's saved with your account. Content is
still written in the site's languages. The admin's text is in core's
`admin` catalog (`resources/lang/admin/en.json`); Blush ships it in
English and Japanese (`ja.json` beside it), and another language
appears here once there's a catalog for it, such as your site's own
translation in `user/lang/{locale}/admin/core.json`. What the server sends the admin
(errors, and the Settings and Site Health screens' text) is in core's
main catalog under `admin.` keys (`resources/lang/en.json`, translated
in `user/lang/{locale}/core.json`), and follows the same language.

## Password

**Change Password** asks for your current password and a new one (at
least 12 characters, unless your site sets another length). You stay
signed in where you changed it, and you're signed out on every other
device. Too many wrong current passwords lock you out of changing it
for a while, as with signing in.

## API tokens

Your own API tokens and connected apps are on this screen too: see
[API tokens and connected apps](api-tokens.md).
