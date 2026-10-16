# Logs

With `site.logs` (administrators), **Tools → Logs** shows the last 50
entries in the site's log, for when something fails on a host you
can't reach over SSH.

The log is `storage/logs/site.log` unless
[`config/log.php`](../reference/configuration/README.md) says otherwise.
Entries are newest first, one line each, with errors and warnings
marked. An entry with an exception opens to show its trace. **Refresh**
reads it again and **Download** saves the whole file. It's read only.
A site that logs to the server's error output, or not at all, has no
file to show.
