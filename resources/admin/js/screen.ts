/**
 * The current screen's title, when it's more specific than its route's
 * (`meta.title`), such as a content type's label. The top bar and the
 * document title show it; each navigation clears it.
 */

import { ref } from 'vue';
import type { RouteLocationRaw } from 'vue-router';

export const screenTitle = ref<string | null>(null);

/**
 * The screens above this one in the top bar's trail, each a way back to
 * it, between the site's name and the title: the editor's content type
 * (`Posts`), which is the editor's way out (admin.md §8, The toolbar,
 * D-313). Each navigation clears it.
 */
export const screenTrail = ref<{ label: string; to: RouteLocationRaw }[]>([]);

/**
 * The trail's last crumb, when it says what you're doing rather than the
 * screen's title: the editor's "Editing" (D-317). Each navigation clears
 * it.
 */
export const screenCrumb = ref<string | null>(null);

/**
 * Whether the editor's focus mode is on: the layout drops the section
 * rail, its panel, and the top bar, leaving the writing column. Each
 * navigation turns it off.
 */
export const focusMode = ref(false);

