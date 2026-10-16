# Upload dates

The library lists files newest first, by when each was added to it.

## How a file is dated

Uploading a file writes the date in its [details](details.md), as
`uploaded`, a date and time in UTC (the admin shows it in your site's
timezone):

```json
{
    "alt": "The sun going down over the lake",
    "uploaded": "2026-10-10 19:03:00 +00:00",
    "id": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74"
}
```

A file's modified time isn't used for this, since copying, syncing, or
checking out files changes it. A file without a date (one you added by
hand, or brought from another system) is listed by its likely one:

- In a dated folder (`2006/04/`, `2006/04/15/`, or `2006/`), its
  modified time when that falls in the folder's month (or day, or
  year), else the date the file says it was made when that does, else
  the folder's first day.
- Otherwise, its modified time.

## Writing the dates

To write those dates into the files' details, so they're kept:

```sh
bin/blush media:dates            # say how many files have no upload date, or one not in UTC
bin/blush media:dates -v         # and list each, with the date it'd be given
bin/blush media:dates --write    # give each one its likely date, and write the others in UTC
```

Change a date by hand in the file's details when the guess is wrong.
Write it in UTC, since dates are sorted as written; one with another
offset is listed in the right place but `media:dates --write` rewrites
it in UTC. `content:lint` reports one that isn't a date and time with
its offset.
