/**
 * What the Icon Packs screen and a pack's details screen share (D-385):
 * the installed packs and the core set (`GET icon-packs`), and turning a
 * pack on or off and deleting one, each with its question or toast.
 *
 * Turning a pack on or off saves the packs turned off in
 * `user/data/settings.json` (`PUT icon-packs/{vendor}/{name}`), over
 * `config/icons.php`. Deleting removes a folder in `user/icons`
 * (`DELETE icon-packs/{folder}`). Icons are drawn from their SVG as a
 * CSS mask, so nothing in a pack's files runs.
 */

import { ref } from 'vue';
import { ApiError, request, type IconPacks, type IconPackSummary, type PackIcon } from './api';
import { confirmAction } from './confirm';
import { loadCounts } from './counts';
import { toast } from './toast';
import { folderName } from './themes';

export function useIconPacks() {
	const answer = ref<IconPacks | null>(null);
	const error  = ref('');
	// The pack being turned on or off.
	const busy   = ref<string | null>(null);

	async function load(): Promise<void> {
		try {
			answer.value = await request<IconPacks>('GET', '/icon-packs');
			error.value  = '';
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The icon packs couldn\'t be loaded.';
		}
	}

	// Turns a pack on or off; resolves whether it was saved. With
	// `undone`, the toast offers an Undo, which turns it back and then
	// calls `undone` with the state it's back in, for the screen to show.
	async function toggle(pack: IconPackSummary, on: boolean, undone?: (on: boolean) => void): Promise<boolean> {
		busy.value = pack.name;

		try {
			await request('PUT', `/icon-packs/${pack.name}`, { enabled: on });
			toast(on ? `Turned on ${pack.label}` : `Turned off ${pack.label}; its ${pack.count === 1 ? 'icon isn\'t' : `${pack.count} icons aren't`} available now`, {
				kind: on ? 'good' : 'danger',
				undo: undone === undefined ? undefined : () => void toggle(pack, !on).then((saved) => saved && undone(!on))
			});

			return true;
		} catch (caught) {
			toast(caught instanceof ApiError ? caught.message : `${pack.label} couldn't be turned ${on ? 'on' : 'off'}`, { kind: 'warn' });

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/icons.php`'s list back in charge.
	async function useConfig(): Promise<void> {
		try {
			await request('PATCH', '/settings', { unset: ['icons.disabled'] });
			await load();
			toast('Using the icon packs config/icons.php turns on and off');
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The icon packs couldn\'t be saved.';
		}
	}

	// Asks, then deletes a pack's folder (or a broken one's); resolves
	// whether it was deleted.
	async function remove(label: string, folder: string, pack: IconPackSummary | null = null): Promise<boolean> {
		const body = [`The folder **${folder}** and everything in it is removed from the server. This can't be undone.`];

		if (pack !== null) {
			body.push(`Its ${pack.count === 1 ? 'icon' : `${pack.count} icons`} in **${pack.namespace}/** stop working, and anywhere one is used shows no icon.`);
		}

		if (!await confirmAction({ title: `Delete ${label}?`, body, confirm: `Delete ${label}`, danger: true })) {
			return false;
		}

		try {
			await request('DELETE', `/icon-packs/${encodeURIComponent(folderName(folder))}`);
			void loadCounts();
			toast(`Deleted ${label}`, { kind: 'danger' });

			return true;
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : `${label} couldn't be deleted.`;

			return false;
		}
	}

	return { answer, error, busy, load, toggle, useConfig, remove };
}

// A pack's details screen's address: `/icon-packs/{vendor}/{name}`.
export function iconPackRoute(name: string): { name: 'icon-pack'; params: { vendor: string; name: string } } {
	const [vendor = '', short = ''] = name.split('/');

	return { name: 'icon-pack', params: { vendor, name: short } };
}

// An icon drawn as a mask of its SVG, filled with the text color.
export function packIconMask(icon: PackIcon): string {
	return icon.svg === '' ? 'none' : `url("data:image/svg+xml,${encodeURIComponent(icon.svg)}")`;
}
