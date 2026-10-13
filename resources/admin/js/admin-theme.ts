/**
 * The admin's look (D-317, D-827): an admin theme, an account preference
 * beside the color scheme, saved on the server and followed on any
 * device. An account chooses one of the themes that are on, or follows
 * the site's default (`null`), which the sign-in screen shows too. The
 * shell page sets the theme showing on `<html>` (`data-admin-theme`,
 * which its stylesheet's tokens are scoped to) and links its stylesheet
 * (`#blush-admin-theme`); changing theme swaps both. Neutral is the
 * admin's own tokens, so it sets and links nothing.
 */

import { computed, readonly, ref, watch } from 'vue';
import { request, type Preferences } from './api';
import { config, type AdminThemeChoice } from './config';

const LINK = 'blush-admin-theme';

// The themes an account may choose, Neutral first, and the site's default.
export const adminThemes: readonly AdminThemeChoice[] = config.adminThemes.offered;

const choice   = ref<string | null>(config.adminTheme);
const fallback = ref<string | null>(config.adminThemes.default);

export const defaultAdminTheme = readonly(fallback);

// The account's choice, or `null` when it follows the site's default.
export const adminThemeChoice = readonly(choice);

// The theme showing: the account's choice when it's offered, else the
// site's default, else the first (Neutral).
export const adminTheme = computed<AdminThemeChoice | null>(() => find(choice.value) ?? find(fallback.value) ?? adminThemes[0] ?? null);

function find(name: string | null): AdminThemeChoice | undefined {
	return name === null ? undefined : adminThemes.find((theme) => theme.name === name);
}

/**
 * Shows a theme: its name on `<html>` and its stylesheet linked, or
 * neither for Neutral.
 */
function show(theme: AdminThemeChoice | null): void {
	const root     = document.documentElement;
	const existing = document.getElementById(LINK);

	if (theme?.stylesheet == null) {
		delete root.dataset.adminTheme;
		existing?.remove();

		return;
	}

	const link = existing instanceof HTMLLinkElement
		? existing
		: document.head.appendChild(Object.assign(document.createElement('link'), { id: LINK, rel: 'stylesheet' }));

	if (link.getAttribute('href') !== theme.stylesheet) {
		link.href = theme.stylesheet;
	}

	root.dataset.adminTheme = theme.name;
}

// The page arrives showing its theme; what changes it is shown at once.
watch(adminTheme, show);

function use(name: string | null): void {
	choice.value = name;
}

/**
 * Follows a new site default (the Admin Themes screen's), which an
 * account without a choice of its own sees at once.
 */
export function followDefault(name: string): void {
	fallback.value = name;
}

/**
 * Follows the signed-in account's theme, as the server sent it.
 */
export function followTheme(preferences: Preferences): void {
	if (preferences.adminTheme !== choice.value) {
		use(preferences.adminTheme);
	}
}

/**
 * Changes the account's theme, by name, or to `null` to follow the
 * site's default: shown at once, then saved; if saving fails, the old one
 * comes back and the `ApiError` is thrown.
 */
export async function saveAdminTheme(name: string | null): Promise<void> {
	const before = choice.value;

	use(name);

	try {
		followTheme((await request<{ preferences: Preferences }>('PATCH', '/preferences', { adminTheme: name })).preferences);
	} catch (caught) {
		use(before);
		throw caught;
	}
}
