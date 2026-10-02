/**
 * What the Themes screen and a theme's details screen share (D-381,
 * D-383): the installed themes (`GET appearance`), and activating,
 * deleting, and copying, each with its question, toast, or failure.
 *
 * Activating saves `theme.active` in `user/data/settings.json` (`PATCH
 * settings`) and asks the server to compile and reindex for it; a
 * failure is kept by theme, so the screen can say the site is unchanged
 * where the button is. Deleting removes a folder in `user/themes`
 * (`DELETE themes/{folder}`).
 */

import { computed, ref } from 'vue';
import { ApiError, request, type Appearance, type ThemeSummary } from './api';
import { config } from './config';
import { confirmAction } from './confirm';
import { loadCounts } from './counts';
import { toast } from './toast';

export function useThemes() {
	const appearance = ref<Appearance | null>(null);
	const error      = ref('');
	// The theme being activated, and the one whose activation failed, with why.
	const busy       = ref<string | null>(null);
	const failed     = ref<{ name: string; reason: string } | null>(null);

	const themes = computed(() => appearance.value?.themes ?? []);
	const active = computed(() => themes.value.find((theme) => theme.active) ?? null);

	async function load(): Promise<void> {
		try {
			appearance.value = await request<Appearance>('GET', '/appearance');
			error.value      = '';
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The themes couldn\'t be loaded.';
		}
	}

	function find(name: string): ThemeSummary | null {
		return themes.value.find((theme) => theme.name === name) ?? null;
	}

	function label(name: string): string {
		return find(name)?.label ?? name;
	}

	function installed(name: string): boolean {
		return find(name) !== null;
	}

	// The themes that fall back to a theme.
	function dependents(theme: ThemeSummary): ThemeSummary[] {
		return themes.value.filter((other) => other.parent === theme.name);
	}

	// Why a theme can't be activated, saying what it means and what to do.
	// A missing parent is already said beside it, so it isn't repeated.
	function blockedMessage(theme: ThemeSummary): string {
		if (theme.parent !== null && !installed(theme.parent)) {
			return `Anything this theme doesn't define would have nowhere to come from, so it can't be activated. Install ${theme.parent}, or point this theme at another theme to fall back to.`;
		}

		return `${theme.blocked ?? ''} It can't be activated until that's fixed.`;
	}

	// Asks, then activates a theme; resolves whether it was activated.
	async function activate(theme: ThemeSummary): Promise<boolean> {
		const sure = await confirmAction({
			title: `Activate ${theme.label}?`,
			body: `Visitors will see the ${theme.label} theme as soon as it's saved. Your content, addresses, and settings don't change, and you can switch back at any time.`,
			confirm: `Activate ${theme.label}`
		});

		if (!sure) {
			return false;
		}

		busy.value   = theme.name;
		failed.value = null;

		try {
			await save({ set: { 'theme.active': theme.name } });
			await load();
			toast(`Activated ${theme.label}`);

			return true;
		} catch (caught) {
			failed.value = { name: theme.name, reason: caught instanceof ApiError ? caught.message : 'The theme couldn\'t be saved.' };

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/theme.php`'s theme back in charge.
	async function useConfig(): Promise<void> {
		try {
			await save({ unset: ['theme.active'] });
			await load();
			toast(`Activated ${active.value?.label ?? 'the theme'} from config/theme.php`);
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The theme couldn\'t be saved.';
		}
	}

	// Asks, then deletes a theme's folder; resolves whether it was deleted.
	async function remove(name: string, folder: string, falling: ThemeSummary[] = []): Promise<boolean> {
		const body = [`The folder **${folder}** and everything in it is removed from the server. This can't be undone.`];

		if (falling.length > 0) {
			const names = falling.map((theme) => theme.label).join(' and ');

			body.push(`${names} fall${falling.length === 1 ? 's' : ''} back to ${name}, and can't be activated until ${falling.length === 1 ? 'it\'s' : 'they\'re'} pointed at a theme that's installed.`);
		}

		if (!await confirmAction({ title: `Delete ${name}?`, body, confirm: `Delete ${name}`, danger: true })) {
			return false;
		}

		try {
			await request('DELETE', `/themes/${encodeURIComponent(folderName(folder))}`);
			void loadCounts();
			toast(`Deleted ${name}`, { kind: 'danger' });

			return true;
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : `${name} couldn't be deleted.`;

			return false;
		}
	}

	return { appearance, error, busy, failed, themes, active, load, find, label, installed, dependents, blockedMessage, activate, useConfig, remove };
}

// Saves the theme setting, then has the server compile and reindex for it
// when it asks.
async function save(changes: { set?: Record<string, string>; unset?: string[] }): Promise<void> {
	const answer = await request<{ refresh: boolean }>('PATCH', '/settings', changes);

	if (answer.refresh) {
		await request('POST', '/settings/refresh').catch(() => undefined);
	}
}

// A folder's last part, which `DELETE themes/{folder}` takes.
export function folderName(folder: string): string {
	return folder.slice(folder.lastIndexOf('/') + 1);
}

// The site with another theme (`?theme=`, development only).
export function previewUrl(name: string): string {
	const url = new URL(config.site.url);

	url.searchParams.set('theme', name);

	return url.toString();
}

// A theme's details screen's address: `/themes/{vendor}/{name}`.
export function themeRoute(name: string): { name: 'theme'; params: { vendor: string; name: string } } {
	const [vendor = '', short = ''] = name.split('/');

	return { name: 'theme', params: { vendor, name: short } };
}

export async function copy(text: string, what: string): Promise<void> {
	try {
		await navigator.clipboard.writeText(text);
		toast(`Copied ${what}`);
	} catch {
		toast(`The ${what} couldn't be copied`, { kind: 'warn' });
	}
}
