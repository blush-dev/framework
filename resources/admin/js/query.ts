/**
 * A list screen's filters in its address (D-505), so each view is a URL:
 * `text()` reads one, and `set()` changes some and keeps the rest, leaving
 * out any that's empty or the default (`status=any`).
 */

import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router';

export function useQueryState() {
	const route  = useRoute();
	const router = useRouter();

	function text(name: string): string {
		const value = route.query[name];

		return typeof value === 'string' ? value : '';
	}

	function set(changes: LocationQueryRaw): void {
		const next: LocationQueryRaw = { ...route.query, ...changes };

		for (const [name, value] of Object.entries(next)) {
			if (value === undefined || value === null || value === '' || (name === 'status' && value === 'any')) {
				delete next[name];
			}
		}

		void router.replace({ query: next });
	}

	return { text, set };
}
