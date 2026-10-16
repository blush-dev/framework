# Markdown pages and llms.txt

A Markdown copy of every page, `/llms.txt`, and `/llms-full.txt`, for
agents and AI tools.

`config/llms.php` · `Blush\Llms\LlmsConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Serve a Markdown version of every page and `/llms.txt` |
| `full` | `false` | Also serve `/llms-full.txt`: every page `llms.txt` lists, in full, in one file |

All of these can also be changed in the admin, on **Settings → AI**.

## Markdown pages

Agents and AI tools read Markdown more easily than HTML, so every
published page also has a Markdown version at its address plus `.md`:
`/archives/hello` is `/archives/hello.md`, `/about/` is `/about.md`, and
the homepage is `/index.md`. It's the entry as you wrote it, with its
title, address, dates, and summary at the top. Blocks such as
`:::figure` stay as written. Links and images get full addresses
(`/about` becomes `https://example.com/about`, and media points at its
media URL, as on the HTML page), in Markdown and in blocks' URL
options, so the copy still works once it's read elsewhere. Code and
HTML are left exactly as written. Unlisted entries have one; drafts,
scheduled entries, and hidden entries don't. Each page links to its
Markdown version in its `<head>`.

## llms.txt

`/llms.txt` follows [llmstxt.org](https://llmstxt.org): your site's
name, its [description](app.md), then a section for each content type
linking every public entry's Markdown version, newest first for dated
types and by title for terms and profiles. Collections and trees are
listed unless they say [`llms: false`](../../concepts/content-types.md), and profiles
are left out unless they say `llms: true` (only profiles with their own
files are listed). A [type of terms](../../concepts/relationships.md),
such as tags, is a collection: it's listed unless it says `llms: false`,
which the admin's new Terms types and the taxonomy migration add. Every page keeps its
Markdown copy either way.

## llms-full.txt

`/llms-full.txt` has the same heading, then the Markdown copy of every
page `llms.txt` lists, in the same order, so a tool can read the whole
site in one request. It's off by default because it's large: about
3.6 MB for a site of a thousand posts. Blush builds it on each request
(it's too big for the page cache).
