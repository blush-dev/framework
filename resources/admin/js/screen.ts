/**
 * The current screen's title, when it's more specific than its route's
 * (`meta.title`), such as a content type's label. The top bar and the
 * document title show it; each navigation clears it.
 */

import { ref } from 'vue';

export const screenTitle = ref<string | null>(null);

/**
 * Whether the editor's focus mode is on: the layout drops the section
 * rail, its panel, and the top bar, leaving the writing column. Each
 * navigation turns it off.
 */
export const focusMode = ref(false);

