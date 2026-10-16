# Media

Commands for the media library: dates, ids, image sizes, the index, and
serving `user/media`.

| Command | What it does |
|---|---|
| `media:dates` | Say how many media files have no [upload date](../../concepts/media/upload-dates.md), which orders the library, or one not in UTC (`-v` lists each, with the date it would be given). `--write` gives each its likely date, from its dated folder and its file, and writes the others in UTC. |
| `media:ids` | Say how many media files are missing an [id](../../concepts/media/ids-and-image-sizes.md), and which ids files share (`-v` lists each file). `--write` gives each file missing one a new id; `--keep=<path>` (in the media folder) keeps a shared id on that file and gives the others new ones. An image's other sizes don't need one. |
| `media:sizes` | Say how many [image sizes](../../concepts/media/ids-and-image-sizes.md) aren't listed in their images' details yet (`-v` lists them). `--write` lists them, and takes out listed files that are gone. |
| `media:index` | Update the media index, which the admin's library lists and searches. `--full` rebuilds it; it also warns of metadata files whose media file is gone. (`publish` does this for you.) |
| `media:publish` | Link `user/media` into `public/` so the web server serves it. `--copy` copies instead, for hosts without symlinks. Writes an `.htaccess` there so no script runs and SVGs are sandboxed. |
