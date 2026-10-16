# Checking your content

Blush's `content:` commands find problems in your files before your
readers do, and fix the ones that can be fixed for you.

```sh
bin/blush content:lint           # report problems in front matter
bin/blush content:lint --strict  # also unknown keys and old 1.x names
bin/blush content:ids --write    # give files without an id one
bin/blush content:parents --write  # write the pages folders of pages need
bin/blush content:list           # everything Blush has found
bin/blush content:new page "Contact me"   # create a new entry
bin/blush content:new                     # asks for its type, title, and more
```

`content:lint` is worth running before you publish: it catches bad dates
(including ones that aren't on the calendar, such as a placeholder
`2019-00-00`, which would quietly be read as 2018-11-30), misspelled values, two files claiming the same URL, a page a content
type's URLs hide (such as `blog/2026.md` when the blog has yearly
archives at `/blog/2026`), term parents that are missing or loop, and
ids that are missing, not valid, or shared.

Site Health in the admin runs the same checks, with a fix beside each
problem (see [the admin](../admin/README.md)).

## Commands that fix files

Each lists what it would change, and changes it with `--write`:

| Command | What it fixes | More |
|---|---|---|
| `content:ids` | Files without an id, and ids files share | [Ids](ids-and-links.md#ids) |
| `content:refs` | Links not filed with their ids | [Links between entries](ids-and-links.md#links-between-entries) |
| `content:parents` | Folders of pages without a page of their own | [Content files](content-files.md) |
| `content:terms` | Terms and profiles named with no file | [Relationships](relationships.md#filing-entries-under-terms) |
| `content:media-ids` | Media fields that name a file by its path | [Custom fields](custom-fields.md) |
| `content:filenames` | Files not named by their type's pattern | [Naming new files](file-names-and-urls.md#naming-new-files) |
| `content:folders` | Collection entries kept as folders, or outside their folders | [Collections are flat](file-names-and-urls.md#collections-are-flat) |
| `content:type-folders` | Types that still name their own folder | [Moving a type into its folder](content-types.md#moving-a-type-into-its-folder) |
| `content:taxonomies` | Types still written as taxonomies | [Moving from taxonomies](relationships.md#moving-from-taxonomies) |

See the [command line reference](../reference/cli/README.md) for every
command.
