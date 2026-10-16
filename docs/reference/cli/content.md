# Content

Commands for making, listing, checking, and fixing entries.

| Command | What it does |
|---|---|
| `content:new [<type>] ["<title>"]` | Create an entry. In a terminal it asks for what you leave out: the type, the title, the slug, the parent page (for pages), and whether it's published or a draft. `--slug=` sets its URL name; `--draft` makes it a draft; `--parent=` puts a page under another, by its path (`--parent=about`). It gets today's date, and a file name by its type's [pattern](../../concepts/file-names-and-urls.md), and ends with where to edit it in the admin. It's listed under [Tools → Activity](../../features/mcp/activity-and-undo.md) as made on the command line; `--account=` makes it as an account instead, with its permissions and its byline (`--account=jane`). |
| `content:list` | List every entry. `--type=post` and `--status=draft` (or `published`, `scheduled`, `trash`, or a status a plugin adds) narrow it down. |
| `content:preview <type> <name>` | Print a [preview link](../../admin/previewing-drafts.md) to an entry, even a draft. `--hours=` sets how long it works. |
| `content:lint` | Check front matter, and media details in `user/data/media/`, for problems, and list files in `user/content/` that aren't `.md`, so aren't read. It reports terms and profiles entries name with no file as errors (`content:terms` writes them). `--strict` also reports unknown keys and 1.x names. See [Checking content](../../concepts/checking-content.md). |
| `content:filenames` | List entries whose files aren't named by their type's own [pattern](../../concepts/file-names-and-urls.md) (types that set `filename`), and what they'd be renamed to. `--write` renames them; `--type=<type>` limits it to one. No address changes. |
| `content:folders` | List collection entries not in the folders their [collection](../../concepts/file-names-and-urls.md) keeps them in (entries kept as folders, and files outside its folder pattern's folders), and where they belong. `--write` moves them and removes folders left empty. No address changes. |
| `content:terms` | List the terms and profiles your entries name that have no file, which the site leaves out. `--write` writes each, published, titled as the entries name it. |
| `content:type-folders` | List types in `user/data/types/` that still name their own folder (`folder`, or 1.x's `path`), and fail while any are left. `--write` [moves](../../concepts/content-types.md) each type's files into `_` and its name, and writes it without the folder, with the prefix its URLs came from. |
| `content:taxonomies` | List types in `user/data/types/` still written as taxonomies, which Blush no longer has, and fail while any are left. `--write` [migrates](../../concepts/relationships.md) each to a collection and a classify relation in `user/data/relations/`. |
| `content:relation <name>` | Say how many entries have values in a relation. `--key=<key>` gives it a new front matter key, keeping the old one as an alias unless `--rewrite` moves the values; `--remove` deletes its file in `user/data/relations`; `--strip` removes its values, with their ids, from entries' files. |
| `content:media-ids` | List the media fields still written as a path, such as `image: /media/2026/10/photo.jpg`. `--write` rewrites each to its [file's id](../../concepts/custom-fields.md) (a path to an image's size becomes the image's id); a path that names no file in the library is left and listed. Give files ids first with `media:ids`. |
| `content:refs` | List content files whose [links to other entries](../../concepts/ids-and-links.md) aren't filed with their ids under `refs`. `--write` files them, writing each value as the slug its entry has now. |
| `content:parents` | List the folders of pages that have no page of their own, which leaves the pages in them at the top of the tree. `--write` writes each as a draft, titled by its folder. |
| `content:ids` | List content files missing an [id](../../concepts/ids-and-links.md), and ids files share. `--write` gives each file missing one a new id; `--keep=<path>` keeps a shared id on that file and gives the others new ones (repeat it for more). |

`content:filenames`, `content:folders`, `content:terms`, `content:parents`,
`content:refs`, and `content:ids` fix things only content files have, so they're there
while your content is kept as files, as it is by default.

`content:index` is under [Publishing and caches](publishing.md).
