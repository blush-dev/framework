# Media blocks

Four built-in [blocks](README.md) play or offer files: audio, video, a
download, and a playlist.

## Finding files

Their files are found the same way as an image's: `/media/…` is in your
[media folder](../media/README.md) (the first `/` is optional, so
`media/song.mp3` works too). Like Markdown's own links, they become full
URLs (`https://example.com/media/song.mp3`), so they still work in feeds.

| Block | Example                                                                     | Props                                                                                                                                                                                                             |
|-----------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `audio`   | `::audio[Episode 12]{src=/media/episode.mp3}`                               | `src`. `preload`: `metadata` (default), `none`, or `auto`. `loop`. The label is the caption. It plays in Blush's player (see below). The `card` variant draws a card (see below), with `title`, `artist`, `album`, and `art`. |
| `video`   | `::video[Launch day]{src=/media/launch.mp4 poster=/media/launch.jpg}`       | `src`, `poster` (an image shown before it plays), `track` (a WebVTT captions file, in the page's language). `width` and `height`, which default to the poster's. `preload`, `loop`, `muted`. The label is the caption. |
| `file`    | `::file[The annual report]{src=/media/report.pdf}`                          | `src`. The label is the link text (the file's name without one). It shows the file's type, and its size when it's in your media folder.                                                                           |
| `playlist` | `:::playlist[Live]{variant=card}` … `:::` | `loop`, `numbers` (default on). The label is the caption. Holds `::audio` lines, one to a line (see [Playlists](#playlists)). |

Only the [file types your site allows](../media/allowed-file-types.md) are
served, so check the list before offering other kinds of files for
download.

## The player

Audio and video play in Blush's own player: a round play button, a bar
to move through it, the time, and the volume (a mute button with a
slider over it), plus captions and full screen for video. The volume is
one setting for every player on the page, and the browser remembers it
for the next visit. On phones that keep the volume to themselves
(iPhones and iPads), only the mute button shows. Its script and styles load only on pages that play
something, and until the script loads, the browser's own controls
show. Its button labels come from the `player` messages, which a theme
can reword ([Translations in a theme](../../themes/translations.md)), and a theme
restyles it with the `--player-*` properties
([Scripts and styles](../../themes/scripts-and-styles.md)).

## Audio cards

For a song or an episode, `variant=card` draws the audio as a card:
its artwork beside its title and who made it, over the player.

```markdown
::audio{src=/media/morning-song.mp3 variant=card}

::audio[Episode 12]{src=/media/episode-12.mp3 variant=card artist="The Show" art=/media/show.jpg}
```

Each part comes from a prop first, then from the file:

- The title is `title`, else the label, else the file's title in the
  [media library](../media/details.md), else the one saved in the file itself.
- Under it, `artist` and `album`, else the ones saved in the file.
- The artwork is `art`, an image, else the file's
  [artwork](../media/artwork.md) from the library, else the cover art saved
  in the file, which Blush serves at `/media-artwork/…` beside
  `/media/…`. Without any, the card has no artwork.

A video without a `poster` uses its artwork the same way, and then,
without `width` and `height`, the video's own size.

## Playlists

A playlist plays its audio one after another in one player, over a
numbered list of the tracks. Put `::audio` lines in it, one to a line;
anything else in it is left out.

```markdown
:::playlist[Live at the Basement]{variant=card}
::audio{src=/media/intro.mp3}
::audio{src=/media/river-song.mp3 title="River Song (acoustic)"}
::audio{src=/media/closing.mp3}
:::
```

The label is the playlist's caption. Each row shows the track's title,
who made it, and its length when the media library knows it, found the
same way as a card's (the title falls back to the file's name).
Choosing a row plays it, and when a track ends, the next one plays.
`loop` starts over after the last track, and `numbers=false` leaves out
the track numbers. With `variant=card`, the player is an audio card
showing the playing track's artwork, title, and who made it. Without
the player's script, the player plays the first track and each row
links to its file.
