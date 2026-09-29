/**
 * The current screen's title, when it's more specific than its route's
 * (`meta.title`), such as a content type's label. The top bar and the
 * document title show it; each navigation clears it.
 */

import { ref } from 'vue';
import type { IconName } from './icons';

export const screenTitle = ref<string | null>(null);

/**
 * Whether the editor's focus mode is on: the layout drops the section
 * rail, its panel, and the top bar, leaving the writing column. Each
 * navigation turns it off.
 */
export const focusMode = ref(false);

/**
 * A screen that's planned but not built (`meta.planned`, D-241): its
 * icon, what it's for, what it will do, and where that's done until then
 * (commands and paths in backticks).
 */
export interface PlannedScreen {
	icon: IconName;
	hint: string;
	next: string;
	today: string;
}
