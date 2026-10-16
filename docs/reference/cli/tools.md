# Tools

Listing and running tools from the command line.

| Command | What it does |
|---|---|
| `tool` | List the [tools](../../features/tools.md) you can run: Blush's own (reading and changing entries and media, publishing, reindexing, clearing caches) and your plugins'. |
| `tool <key> [name=value …]` | Run a tool, such as `tool core/reindex`. Give its input as `name=value`, read by what the tool takes: numbers, `true` or `false`, a list by naming it again (`type=post type=page`, or as JSON), and an object as JSON. Run without input, a tool that needs some says what it takes. It runs as the command line, with every permission; `--account=` runs it as an account instead, with its permissions. `--json` writes only what it answers, as JSON. A tool that runs in the background from the admin runs straight away here. |
