/**
 * The site's audio and video players (D-573): defines
 * `<blush-audio-player>`, `<blush-video-player>`, and `<blush-playlist>`
 * (`resources/player`) for the `::audio`, `::video`, and `:::playlist`
 * blocks (D-708), with their styles and the
 * site's defaults for their colors. Core registers it as the
 * `core/player` asset, which the blocks ask for.
 */

import '../css/player.css';
import { defineAudioPlayer } from '../../player/audio-player';
import { definePlaylist } from '../../player/playlist';
import { defineVideoPlayer } from '../../player/video-player';

defineAudioPlayer();
definePlaylist();
defineVideoPlayer();
