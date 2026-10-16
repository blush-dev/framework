# Styling the player

The audio and video player (`core/player`) draws its colors from
`--player-*` properties, set to your page's own text and background
colors. Set any of them on `:root` or `.player` (and `.audio-card`, for
audio drawn as a card, or `.playlist`, for a playlist) in your
stylesheet to make it yours:

```css
.player {
	--player-accent: var(--color-accent);   /* the play button, what's played */
	--player-accent-fg: white;              /* the play button's glyph */
	--player-track: #ddd;                   /* what's left to play */
	--player-muted: #666;                   /* the time and other buttons */
	--player-font-mono: var(--font-mono);   /* the time */
	--player-radius: 8px;                   /* a video's corners */
	--player-surface: #fff;                 /* a card's background, and the volume slider's */
	--player-line: #e5e5e5;                 /* a card's border, the volume slider's, a playlist's rules */
	--player-card-radius: 12px;             /* a card's corners, and the volume slider's */
	--player-title-font: var(--font-heading); /* a card's title */
}
```

`--player-shadow`, `--player-time-size`, `--player-overlay`, and
`--player-overlay-fg` (a video's controls over the picture) are there
too, and for cards, `--player-fg`, `--player-font`,
`--player-title-size`, `--player-by-size`, `--player-card-pad`,
`--player-art-size`, `--player-art-radius`, `--player-art-shadow`
(none unless you set one), and `--player-pop-shadow` (what lifts the
volume slider off the page). A card's text and fonts
are its surroundings' unless you set them.

## Drawing the player yourself

To draw the player yourself, register your own `core/player`, or an
empty one to load nothing: `new Asset('core/player')` in your provider,
or `"core/player": {}` under `assets` (see
[Scripts and styles](scripts-and-styles.md)).
