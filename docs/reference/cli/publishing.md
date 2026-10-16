# Publishing and caches

Putting changes live, compiling for speed, and clearing caches.

| Command | What it does |
|---|---|
| `publish` | Put content changes live: reindex, refresh, and clear the caches. `--pull` runs `git pull` in `user/` first; `--no-pull` skips it. See [Publishing](../../running/publishing.md). |
| `cache:compile` | Precompile config, routes, content types, themes, plugins, icon packs, and admin themes for speed |
| `cache:clear` | Clear every compiled file and cache. Flags clear just one: `--config`, `--plugins`, `--container`, `--routes`, `--types`, `--themes`, `--icon-packs`, `--store`. `--embeds` also clears the saved oEmbed answers (which nothing else clears) and the cache store, so providers are asked again. |
| `content:index` | Update the content index. `--full` rebuilds it from scratch. (`publish` does this for you.) |

The media commands, `media:index` among them, are under [Media](media.md).
