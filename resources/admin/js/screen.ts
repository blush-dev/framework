/**
 * The current screen's title, when it's more specific than its route's
 * (`meta.title`), such as a content type's label. The top bar and the
 * document title show it; each navigation clears it.
 */

import { ref } from 'vue';

export const screenTitle = ref<string | null>(null);
