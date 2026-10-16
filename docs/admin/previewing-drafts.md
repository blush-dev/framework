# Previewing drafts

A preview link is a private URL that shows an entry that isn't live yet,
with your theme, as it will look once it's live.

In the editor, an entry that isn't live yet has a **Get Link** button
under Publishing. It makes a preview link. **Open** it in a new tab, or
**Copy** it to send to someone. Anyone with the link can see the entry,
without an account, until the link expires a week later.

You can also make one from the command line:

```sh
bin/blush content:preview post my-new-post
bin/blush content:preview post my-new-post --hours=2
```

A link names the entry by its [id](../concepts/ids-and-links.md), not its file, so it
keeps working if the entry is renamed or moved, and it doesn't show
your folders. Links are signed with the `APP_SECRET` in your `.env` (`bin/blush init`
adds one), so they can't be changed to show another entry or to last
longer. Changing `APP_SECRET` ends every link you've given out.
Previews are never cached or indexed by search engines.
