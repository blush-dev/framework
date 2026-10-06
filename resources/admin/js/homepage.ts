/**
 * Making the root page the homepage (D-420), from Pages or the editor:
 * the homepage setting saved as the page at `user/content/index.md`
 * (`PATCH settings`, `content.home` null), over a collection, after a
 * question, since the site's front page changes.
 */

import { errorMessage, saveSettings } from './api';
import { confirmAction } from './confirm';
import { toast } from './toast';

// Asks, then saves the setting and has the server compile and reindex
// for it when it asks. Resolves whether it was saved; a failure is
// toasted.
export async function makeHomepage(title: string, instead: string | null): Promise<boolean> {
	const name = title || 'Untitled';
	const body = `The homepage shows ${instead === null ? 'something else' : instead.toLowerCase()} now. It will show “${name}” instead. You can change it back on Settings › Reading.`;

	if (!await confirmAction({ title: `Make “${name}” the homepage?`, body, confirm: 'Make homepage' })) {
		return false;
	}

	try {
		await saveSettings({ set: { 'content.home': null } });
		toast(`“${name}” is the homepage`);

		return true;
	} catch (caught) {
		toast(errorMessage(caught, 'The homepage couldn\'t be changed.'), { kind: 'warn' });

		return false;
	}
}
