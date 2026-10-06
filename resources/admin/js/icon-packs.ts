/**
 * What the Icon Packs screen and a pack's details screen share (D-385):
 * the installed packs and the core set (`GET icon-packs`), and turning a
 * pack on or off and deleting one, each with its question or toast.
 *
 * Turning a pack on or off saves the local packs turned on in
 * `user/data/settings.json` (`PUT icon-packs/{vendor}/{name}`), over
 * `config/icons.php`. Deleting removes a folder in `extensions/`
 * (`DELETE icon-packs/{vendor}/{name}`). Icons are drawn from their SVG as a
 * CSS mask, so nothing in a pack's files runs.
 */

import type { IconPacks, IconPackSummary, PackIcon } from './api';
import { useExtensionList } from './extensions';

export function useIconPacks() {
	const { answer, error, busy, load, turn, deleteFolder, restoreConfig } = useExtensionList<IconPacks>('icon-pack');

	// Turns a pack on or off; resolves whether it was saved, for the
	// screen to load what it shows again. Turning one off says its icons
	// aren't available now. With `undone`, the toast offers an Undo, which
	// turns it back (with no Undo of its own) and then calls `undone`
	// with the state it's back in, for the screen to show.
	function toggle(pack: IconPackSummary, on: boolean, undone?: (on: boolean) => void): Promise<boolean> {
		return turn(pack, on, {
			message: (also) => on ? `Turned on ${pack.label}${also}` : `Turned off ${pack.label}${also}; its ${pack.count === 1 ? 'icon isn\'t' : `${pack.count} icons aren't`} available now`,
			undo: undone === undefined ? undefined : () => void toggle(pack, !on).then((back) => back && undone(!on))
		});
	}

	// Puts `config/icons.php`'s list back in charge.
	function useConfig(): Promise<void> {
		return restoreConfig('icons.enabled', () => 'Using the icon packs config/icons.php turns on and off', 'The icon packs couldn\'t be saved.');
	}

	// Asks, then deletes a pack's folder (or a broken one's); resolves
	// whether it was deleted.
	function remove(label: string, folder: string, pack: IconPackSummary | null = null): Promise<boolean> {
		return deleteFolder(label, folder, pack === null ? [] : [`Its ${pack.count === 1 ? 'icon' : `${pack.count} icons`} in **${pack.namespace}/** stop working, and anywhere one is used shows no icon.`]);
	}

	return { answer, error, busy, load, toggle, useConfig, remove };
}

// An icon drawn as a mask of its SVG, filled with the text color.
export function packIconMask(icon: PackIcon): string {
	return icon.svg === '' ? 'none' : `url("data:image/svg+xml,${encodeURIComponent(icon.svg)}")`;
}
