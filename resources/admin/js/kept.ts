/**
 * A copy of the editor's unsaved changes in this browser, so they
 * outlive a lost connection, a failed save, or a closed tab. One per
 * entry, with the revision it was edited from; the editor clears it once
 * the changes are saved or thrown away. Storage may be off or full, so
 * every read and write is allowed to fail: the changes are then only in
 * the open tab.
 */

import type { FormValue } from './fields';

export interface EditorState {
	title: string;
	body: string;
	date: string;
	// Missing from changes kept before renaming came to the editor.
	slug?: string;
	form: Record<string, FormValue>;
}

export interface KeptChanges {
	revision: string;
	kept: string;
	state: EditorState;
}

const PREFIX = 'blush-admin-unsaved:';

/**
 * Keeps an entry's changes; answers whether they were stored.
 */
export function keep(key: string, revision: string, state: EditorState): boolean {
	try {
		localStorage.setItem(PREFIX + key, JSON.stringify({ revision, kept: new Date().toISOString(), state } satisfies KeptChanges));

		return true;
	} catch {
		return false;
	}
}

/**
 * The changes kept for an entry, if any.
 */
export function kept(key: string): KeptChanges | null {
	try {
		const raw = localStorage.getItem(PREFIX + key);
		const data: unknown = raw === null ? null : JSON.parse(raw);

		return isKept(data) ? data : null;
	} catch {
		return null;
	}
}

export function forget(key: string): void {
	try {
		localStorage.removeItem(PREFIX + key);
	} catch {
		// Nothing was stored.
	}
}

function isKept(data: unknown): data is KeptChanges {
	if (typeof data !== 'object' || data === null || !('revision' in data) || !('kept' in data) || !('state' in data)) {
		return false;
	}

	const state = data.state;

	return typeof data.revision === 'string'
		&& typeof data.kept === 'string'
		&& typeof state === 'object' && state !== null
		&& 'title' in state && typeof state.title === 'string'
		&& 'body' in state && typeof state.body === 'string'
		&& 'date' in state && typeof state.date === 'string'
		&& 'form' in state && typeof state.form === 'object' && state.form !== null;
}
