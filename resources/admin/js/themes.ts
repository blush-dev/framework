/**
 * What the Themes screen and a theme's details screen share (D-381,
 * D-383): the installed themes (`GET themes`), and activating and
 * deleting, each with its question, toast, or failure.
 *
 * Activating saves `theme.active` in `user/data/settings.json` (`PATCH
 * settings`) and asks the server to compile and reindex for it; a
 * failure is kept by theme, so the screen can say the site is unchanged
 * where the button is. Deleting removes a folder in `extensions/`
 * (`DELETE themes/{vendor}/{name}`).
 */

import { computed, ref } from 'vue';
import { errorMessage, saveSettings, type Themes, type ThemeSummary } from './api';
import { config } from './config';
import { confirmAction } from './confirm';
import { stopsParagraph, useExtensionList } from './extensions';
import { toast } from './toast';

export function useThemes() {
	// The theme being activated is `busy`; this is the one whose
	// activation failed, with why.
	const { answer, error, busy, load, deleteFolder, restoreConfig } = useExtensionList<Themes>('theme');
	const failed = ref<{ name: string; reason: string } | null>(null);

	const themes = computed(() => answer.value?.themes ?? []);
	const active = computed(() => themes.value.find((theme) => theme.active) ?? null);

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

	// Why the active theme doesn't run, when its chain's requirements
	// aren't met and the default theme runs in its place (D-431), or `null`.
	function fallbackMessage(theme: ThemeSummary): string | null {
		const fallback = answer.value?.fallback ?? null;

		return theme.active && fallback !== null ? `${fallback} Visitors see ${label('blush/default')} in its place until that's fixed.` : null;
	}

	// Asks, then activates a theme; resolves whether it was activated.
	async function activate(theme: ThemeSummary): Promise<boolean> {
		const sure = await confirmAction({
			title: `Activate ${theme.label}?`,
			// With what activating it stops, if anything (D-440).
			body: [`Visitors will see the ${theme.label} theme as soon as it's saved. Your content, addresses, and settings don't change, and you can switch back at any time.`, stopsParagraph(theme.stops)].filter((paragraph) => paragraph !== ''),
			confirm: `Activate ${theme.label}`
		});

		if (!sure) {
			return false;
		}

		busy.value   = theme.name;
		failed.value = null;

		try {
			await saveSettings({ set: { 'theme.active': theme.name } });
			await load();
			toast(`Activated ${theme.label}`);

			return true;
		} catch (caught) {
			failed.value = { name: theme.name, reason: errorMessage(caught, 'The theme couldn\'t be saved.') };

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/theme.php`'s theme back in charge.
	function useConfig(): Promise<void> {
		return restoreConfig('theme.active', () => `Activated ${active.value?.label ?? 'the theme'} from config/theme.php`, 'The theme couldn\'t be saved.');
	}

	// Asks, then deletes a theme's folder; resolves whether it was deleted.
	function remove(name: string, folder: string, falling: ThemeSummary[] = []): Promise<boolean> {
		const body: string[] = [];

		if (falling.length > 0) {
			const names = falling.map((theme) => theme.label).join(' and ');

			body.push(`${names} fall${falling.length === 1 ? 's' : ''} back to ${name}, and can't be activated until ${falling.length === 1 ? 'it\'s' : 'they\'re'} pointed at a theme that's installed.`);
		}

		return deleteFolder(name, folder, body);
	}

	return { answer, error, busy, failed, themes, active, load, find, label, installed, dependents, blockedMessage, fallbackMessage, activate, useConfig, remove };
}

// The site with another theme (`?theme=`, development only).
export function previewUrl(name: string): string {
	const url = new URL(config.site.url);

	url.searchParams.set('theme', name);

	return url.toString();
}
