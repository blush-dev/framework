/**
 * What the Admin Themes screen and a theme's details screen share
 * (D-827): the installed admin themes (`GET admin-themes`), and turning
 * one on or off, making one the site's default, and deleting one, each
 * with its question or toast.
 *
 * Turning a theme on or off saves the list of every theme that's on
 * (`PUT admin-themes/{vendor}/{name}`), and the default is saved on its
 * own (`PUT admin-themes/default`), both over `config/admin-themes.php`.
 * Deleting removes a folder in `extensions/` (`DELETE
 * admin-themes/{vendor}/{name}`). A theme is drawn from its own colors
 * (`AdminThemeSketch`), never by loading it.
 */

import { errorMessage, request, type AdminThemes, type AdminThemeSummary } from './api';
import { followDefault } from './admin-theme';
import { useExtensionList } from './extensions';
import { toast } from './toast';

export function useAdminThemes() {
	const { answer, error, busy, load, turn, deleteFolder, restoreConfig } = useExtensionList<AdminThemes>('admin-theme');

	// Turns a theme on or off; resolves whether it was saved, for the
	// screen to load what it shows again. With `undone`, the toast offers
	// an Undo, which turns it back (with no Undo of its own) and then
	// calls `undone` with the state it's back in.
	function toggle(theme: AdminThemeSummary, on: boolean, undone?: (on: boolean) => void): Promise<boolean> {
		return turn(theme, on, {
			message: (also) => on ? `Turned on ${theme.label}${also}; anyone can choose it now` : `Turned off ${theme.label}${also}; anyone who chose it sees the site's default`,
			undo: undone === undefined ? undefined : () => void toggle(theme, !on).then((back) => back && undone(!on))
		});
	}

	// Makes a theme the site's default, which anyone who hasn't chosen
	// one sees, and the sign-in screen; resolves whether it was saved.
	async function makeDefault(theme: AdminThemeSummary): Promise<boolean> {
		busy.value = theme.name;

		try {
			await request('PUT', '/admin-themes/default', { name: theme.name });
			followDefault(theme.name);
			toast(`${theme.label} is the site's admin theme now`, { kind: 'good' });

			return true;
		} catch (caught) {
			toast(errorMessage(caught, `${theme.label} couldn't be made the default`), { kind: 'warn' });

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/admin-themes.php`'s list of themes turned on back in
	// charge, or its default.
	function useConfig(): Promise<void> {
		return restoreConfig('admin-themes.enabled', () => 'Using the admin themes config/admin-themes.php turns on and off', 'The admin themes couldn\'t be saved.');
	}

	function useConfigDefault(): Promise<void> {
		return restoreConfig('admin-themes.default', () => 'Using the default config/admin-themes.php names', 'The default couldn\'t be saved.');
	}

	// Asks, then deletes a theme's folder (or a broken one's); resolves
	// whether it was deleted.
	function remove(label: string, folder: string, theme: AdminThemeSummary | null = null): Promise<boolean> {
		return deleteFolder(label, folder, theme === null ? [] : ['Anyone who chose it sees the site\'s default instead.']);
	}

	return { answer, error, busy, load, toggle, makeDefault, useConfig, useConfigDefault, remove };
}
