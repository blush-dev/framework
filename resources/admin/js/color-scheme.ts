/**
 * Light or dark (D-235): an account preference, saved on the server and
 * followed on any device. The shell page sets it on `<html>` for the
 * signed-in account (`data-color-scheme`, which the tokens read, D-231);
 * this browser remembers the last one in `localStorage`, so the sign-in
 * screen matches too. Storage may be off.
 */

import { readonly, ref } from 'vue';
import { request, type ColorScheme, type Preferences } from './api';
import { config } from './config';

const KEY = 'blush-admin-color-scheme';

function cached(): ColorScheme {
	try {
		const value = localStorage.getItem(KEY);

		return value === 'light' || value === 'dark' ? value : 'system';
	} catch {
		return 'system';
	}
}

const current = ref<ColorScheme>(config.colorScheme ?? cached());

export const colorScheme = readonly(current);

/**
 * Shows a scheme and remembers it in this browser.
 */
function use(scheme: ColorScheme): void {
	current.value = scheme;

	if (scheme === 'system') {
		delete document.documentElement.dataset.colorScheme;
	} else {
		document.documentElement.dataset.colorScheme = scheme;
	}

	try {
		if (scheme === 'system') {
			localStorage.removeItem(KEY);
		} else {
			localStorage.setItem(KEY, scheme);
		}
	} catch {
		// Not cached; the account still has it.
	}
}

use(current.value);

/**
 * Follows the signed-in account's scheme, as the server sent it.
 */
export function followAccount(preferences: Preferences): void {
	if (preferences.colorScheme !== current.value) {
		use(preferences.colorScheme);
	}
}

/**
 * Changes the account's scheme: shown at once, then saved; if saving
 * fails, the old one comes back and the `ApiError` is thrown.
 */
export async function saveColorScheme(scheme: ColorScheme): Promise<void> {
	const before = current.value;

	use(scheme);

	try {
		followAccount((await request<{ preferences: Preferences }>('PATCH', '/preferences', { colorScheme: scheme })).preferences);
	} catch (caught) {
		use(before);
		throw caught;
	}
}
