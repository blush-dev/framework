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
import { t } from './messages';
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
			message: (also) => on ? t('admin_themes.turned_on', 'Turned on {label}{also}; anyone can choose it now', { label: theme.label, also }) : t('admin_themes.turned_off', 'Turned off {label}{also}; anyone who chose it sees the site\'s default', { label: theme.label, also }),
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
			toast(t('admin_themes.made_default', '{label} is the site\'s admin theme now', { label: theme.label }), { kind: 'good' });

			return true;
		} catch (caught) {
			toast(errorMessage(caught, t('admin_themes.default_failed', '{label} couldn\'t be made the default', { label: theme.label })), { kind: 'warn' });

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/admin-themes.php`'s list of themes turned on back in
	// charge, or its default.
	function useConfig(): Promise<void> {
		return restoreConfig('admin-themes.enabled', () => t('admin_themes.using_config', 'Using the admin themes config/admin-themes.php turns on and off'), t('admin_themes.save_failed', 'The admin themes couldn\'t be saved.'));
	}

	function useConfigDefault(): Promise<void> {
		return restoreConfig('admin-themes.default', () => t('admin_themes.using_config_default', 'Using the default config/admin-themes.php names'), t('admin_themes.default_save_failed', 'The default couldn\'t be saved.'));
	}

	// Asks, then deletes a theme's folder (or a broken one's); resolves
	// whether it was deleted.
	function remove(label: string, folder: string, theme: AdminThemeSummary | null = null): Promise<boolean> {
		return deleteFolder(label, folder, theme === null ? [] : [t('admin_themes.delete_note', 'Anyone who chose it sees the site\'s default instead.')]);
	}

	return { answer, error, busy, load, toggle, makeDefault, useConfig, useConfigDefault, remove };
}
