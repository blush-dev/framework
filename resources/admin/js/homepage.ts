/**
 * Making the root page the homepage (D-420), from Pages or the editor:
 * the homepage setting saved as the page at `user/content/index.md`
 * (`PATCH settings`, `content.home` null), over a collection, after a
 * question, since the site's front page changes.
 */

import { errorMessage, saveSettings } from './api';
import { confirmAction } from './confirm';
import { t } from './messages';
import { toast } from './toast';

// Asks, then saves the setting and has the server compile and reindex
// for it when it asks. Resolves whether it was saved; a failure is
// toasted.
export async function makeHomepage(title: string, instead: string | null): Promise<boolean> {
	const name = title || t('homepage.untitled', 'Untitled');
	const body = instead === null
		? t('homepage.confirm_body_else', 'The homepage shows something else now. It will show “{name}” instead. You can change it back on Settings › Reading.', { name })
		: t('homepage.confirm_body', 'The homepage shows {instead} now. It will show “{name}” instead. You can change it back on Settings › Reading.', { instead: instead.toLowerCase(), name });

	if (!await confirmAction({ title: t('homepage.confirm_title', 'Make “{name}” the homepage?', { name }), body, confirm: t('homepage.make_homepage', 'Make Homepage') })) {
		return false;
	}

	try {
		await saveSettings({ set: { 'content.home': null } });
		toast(t('homepage.done', '“{name}” is the homepage', { name }));

		return true;
	} catch (caught) {
		toast(errorMessage(caught, t('homepage.failed', 'The homepage couldn\'t be changed.')), { kind: 'warn' });

		return false;
	}
}
