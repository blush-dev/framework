# The media index

The admin's library lists and searches a media index
(`storage/index/media.php`) of every file in `user/media`: its type,
size, dimensions, and details.

## Keeping it current

The index only reads files that changed, so it stays quick with
thousands of files. In development it updates as you work; on a live
site, `publish` (and the admin's **Reindex content** action) updates
it, as does uploading a file or saving its details in the admin. To
update it yourself:

```sh
bin/blush media:index          # only what changed
bin/blush media:index --full   # read every file again
```

It also warns of details left in `user/data/media/` for a file that's
gone: move them with the file, or delete them.

## Large libraries

When the whole library has to be read again (the first time, or after
changing the media URL or the file types the library takes), the admin
reads a few hundred files at once and the rest as a
[background job](../../running/cron-and-jobs.md), so a large
library never makes a page time out. Meanwhile the Media screen says
it's catching up and shows how far along it is: files not read yet show
their details from before, or aren't listed yet. Publishing from the
admin or the webhook works the same way. `publish` and `media:index` on
the command line always read everything in one go.
