/**
 * How many things each of the section panel's lists holds (`GET counts`,
 * D-371), for the count beside its link. Loaded with the shell and again
 * after each change of screen, since a screen is where things are made
 * and removed; a failed load keeps the counts it had.
 */

import { ref } from 'vue';
import { request } from './api';

export interface NavCounts {
	// Entries of each type the account edits, by type name.
	types: Record<string, number>;
	media?: number;
	accounts?: number;
	roles?: number;
	contentTypes?: number;
	fieldSets?: number;
	themes?: number;
	plugins?: number;
	iconPacks?: number;
}

export const navCounts = ref<NavCounts | null>(null);

let pending: Promise<void> | null = null;

export function loadCounts(): Promise<void> {
	pending ??= request<NavCounts>('GET', '/counts').then((counts) => {
		navCounts.value = counts;
	}, () => undefined).finally(() => {
		pending = null;
	});

	return pending;
}
