# General settings

**Settings → General** has the site's name, description, email address,
language, time zone, date formats, and accounts.

## The site

- **Name:** the site's name.
- **Description:** a one-line description (for `llms.txt`, and the
  homepage and feeds when nothing more specific describes them).
- **Email address:** required; where notices for its owner are sent,
  never shown on the site. Where the site can send email, a new one is
  saved once it's confirmed with the link emailed there, the old one is
  told, and **Cancel the change** ends it.

## Language and region

The site's language and region are picked from a menu of every
language and region PHP knows, each named in its own language with
its English name beside it, and each region under its language;
search by either name or the code, or choose **Other…** to type any
code, such as `en_US`.

On a site with other languages, **Untranslated pages** sets what
another language does with an entry not translated into it (see
[Translations](../../concepts/translations.md)).

## Time zone

The time zone shows the time there now. Time zones are listed by city
under their regions, each with its common name and offset today
(Chicago: Central Time · UTC−5); search by city, name, abbreviation
(`CST`, `CDT`), offset (`-05:00`), or an old name such as `US/Central`.
The zone is still saved by its full name, such as `America/Chicago`.

## Date and time formats

Then the date and time formats themes show dates with, which the admin
uses too wherever a date reads as a date (when a file changed, when an
account last signed in, when something went to the trash), though lists
keep their short dates and times stay in your own time zone: each menu
shows how today reads in the formats the site's language defines (Full,
Long, Medium, Short) and in a few fixed ones (`2026-10-04`, `14:30`),
or choose **Custom…** to type an
[ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax),
such as `d MMMM y`, and see how it reads as you type.

## Accounts

**Sign-ups** (whether anyone can make an account, off by default) and
**Role for new accounts** (Member by default; any role but Owner and
Administrator, and locked while sign-ups are off). These are saved, but
there's no sign-up form yet, so they don't change anything.

## Shown only

Beside them, shown but not changed here: the site's address, the
environment, and detailed error pages.
