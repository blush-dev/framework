# Embeds

Which sites' links become embedded players, and how their answers are
kept.

`config/embed.php` · `Blush\Embed\EmbedConfig`

The [`embed` block](../../concepts/blocks/README.md) asks each
video's site for its size and title (over [oEmbed](https://oembed.com)),
once a month per URL. Built in are YouTube, Vimeo, TED, CodePen,
Spotify, SoundCloud, Flickr, Twitch, and TikTok (CodePen and Twitch
aren't asked; they're framed from the link). A player the site gives a
width of `100%` (an audio player) fills the column at the height it
answers with, and a site that answers with a photo shows the image. Add other sites here:

```php
<?php

declare(strict_types=1);

use Blush\Embed\EmbedConfig;
use Blush\Embed\OEmbedProvider;

return new EmbedConfig(
	providers: [
		new OEmbedProvider(
			'streamable',
			'Streamable',
			['https://streamable.com/*'],
			'https://api.streamable.com/oembed.json'
		)
	],
	enabled: ['streamable', 'youtube']
);
```

Every provider, built in or added, is off until it's named in
`enabled` (or turned on under Settings → Writing), so a new site embeds
nothing and asks no other site anything.

A provider has a name, a label, the URLs it embeds (`*` matches
anything), and its oEmbed address, which must be `https://`. Find these
on the site's developer pages or in [oembed.com's list](https://oembed.com/providers.json).
Only URLs a provider matches are embedded; others are links. Embeds
that need the site's own script to work (such as posts on X or
Instagram) show as links for now. A plugin can add providers too; see
[Embed providers](../../features/embed-providers.md).

| Option | Default | What it does |
|---|---|---|
| `providers` | `[]` | oEmbed providers to add; one named like a built-in (`youtube`, `vimeo`, `ted`, `codepen`, `spotify`, `soundcloud`, `flickr`, `twitch`, `tiktok`, `x`, `reddit`) replaces it |
| `enabled` | `[]` | Names of providers turned on, built in or added; every other provider's links stay links. Settings → Writing's switches save over it |
| `fetch` | `true` | Ask providers for sizes and titles; `false` never does (the built-ins still embed, at 16:9) |
| `timeout` | `3` | Seconds to wait for a provider |
| `ttl` | `2592000` | Seconds to keep an answer (30 days) |
| `failureTtl` | `3600` | Seconds before asking again after a provider didn't answer |

Answers are kept in `storage/cache/store/embeds`, which publishing and
`cache:clear` leave alone. Run `bin/blush cache:clear --embeds` to ask
every provider again.
