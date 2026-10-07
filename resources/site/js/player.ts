/**
 * The site's audio and video players (D-573): defines
 * `<blush-audio-player>` and `<blush-video-player>` (`resources/player`)
 * for the `::audio` and `::video` directives, with their styles and the
 * site's defaults for their colors. Core registers it as the
 * `blush/player` asset, which the directives ask for.
 */

import '../css/player.css';
import { defineAudioPlayer } from '../../player/audio-player';
import { defineVideoPlayer } from '../../player/video-player';

defineAudioPlayer();
defineVideoPlayer();
