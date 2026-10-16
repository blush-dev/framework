# Scheduled backups

The **Schedule** tab on Tools → Backups makes [backups](backups.md) for
you, as a [background job](cron-and-jobs.md) run by cron (or by page
visits, or while the admin is open, when there's no cron).

## The schedule

- **How Often:** Off, Daily, or Weekly on a day you pick.
- **Time:** the hour it runs, in the site's time zone.
- **Keep:** how many of the schedule's backups are kept (7, 14, 30, or
  60). When a new one is made, the oldest goes. Backups you make
  yourself, ones made before a restore, and ones you upload are never
  removed by the schedule.
- **What Each Holds:** the parts each scheduled backup holds. With every
  part ticked, a part a plugin adds later is in it too.
- **Send an Email:** **Only when a backup fails** (the default: one email
  each time one fails, and one more when they work again), **After every
  backup**, or **Never**. It goes to the site's email address (Settings →
  General) unless you give another.

Choose **Save Schedule** to save it. **Next Backup** above the list says
when it runs next.

## The email

The email says whether the backup worked, when, its size and what it
holds, and links to Tools → Backups, where you sign in to see it. **It
never attaches the backup or links to a download**: a backup holds
password hashes, and email is no place for them. **Send a Test Email**
checks that the site's mail reaches you, and **Preview the Email** shows
both emails as they'd be sent. Only scheduled backups email; one you make
yourself finishes on the screen.

## Good to know

A scheduled backup due while another backup or a restore is running is
skipped until its next time. When backups are scheduled to email but
the site can't send mail, [Site Health](../admin/site-health/README.md) warns,
since a failed backup would otherwise go unnoticed.

Backups are kept on the server they back up. To keep copies somewhere
else, a plugin can copy each one as it's made (see
[Events](../features/events.md)).

The schedule is saved as a setting over `config/backups.php`; see
[Backups configuration](../reference/configuration/backups.md) to set it
in code instead.
