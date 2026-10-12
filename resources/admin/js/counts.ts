/**
 * What the section panel needs counted (`GET counts`, D-371; its lists
 * aren't counted since D-800): whether Tools has anything on it, and
 * Site Health's problems. Loaded with the shell and again after each
 * change of screen; a failed load keeps the counts it had.
 */

import { ref } from 'vue';
import { request } from './api';

export interface NavCounts {
	// The Tools screen's buttons the account may use (D-540, D-761).
	tools: number;
	// Checks needing a look in Site Health's last report (D-545).
	health?: number;
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
