/**
 * The admin's text (D-831): core's `admin` catalog, which the shell page
 * prints in the account's language, read by `blush/messages`, which the
 * admin bundles rather than importing by name. Scripts call `t()` from
 * here; Vue templates have it as a global (`admin.ts`):
 *
 *     t('screens.media', 'Media')
 *     t('media.count', '{count, plural, one {# file} other {# files}}', { count })
 *
 * A key the catalog lacks shows the text beside it, as in PHP.
 */

import { messages } from '../../site/js/messages';

export { format, locale } from '../../site/js/messages';
export type { Params, Translate } from '../../site/js/messages';

export const t = messages('core');

declare module 'vue' {
	interface ComponentCustomProperties {
		// The admin's text in templates (`admin.ts`).
		t: typeof t;
	}
}
