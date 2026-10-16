# Sitemap and robots.txt

The sitemap at `/sitemap`, and what `robots.txt` asks of crawlers.

`config/sitemap.php` · `Blush\Sitemap\SitemapConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Serve `/sitemap` and `/robots.txt` |
| `disallow` | `[]` | Paths `robots.txt` asks search engines to skip |
| `robots` | `null` | Your own `robots.txt`, served exactly as written |
| `blockAi` | `[]` | Kinds of AI crawler the generated `robots.txt` asks to stay away: `'training'`, `'search'`, `'fetchers'` |
| `perSitemap` | `50000` | The most addresses in one sitemap, up to 50,000. A type with more is split into pages, `/sitemap/post`, `/sitemap/post/2`, and so on, each listed in `/sitemap` |

## AI crawlers

`blockAi` takes three kinds of AI crawler, each a list of known bots kept
up to date with Blush:

- `training`: crawlers that collect pages to train models (GPTBot,
  ClaudeBot, CCBot, Bytespider, meta-externalagent), plus
  Google-Extended and Applebot-Extended, which keep pages out of Google's
  and Apple's AI training while their search crawlers still visit.
- `search`: crawlers that index pages so AI answers can cite and link
  them (OAI-SearchBot, Claude-SearchBot, PerplexityBot).
- `fetchers`: tools that fetch a page when a person asks their assistant
  about it (ChatGPT-User, Claude-User, Perplexity-User). Blocking these
  blocks your readers' own tools.

`robots.txt` is a request: well-behaved crawlers follow it, others may
not. Outside production, it already asks every crawler to stay away, and
your own `robots` replaces the generated file and these rules with it.
