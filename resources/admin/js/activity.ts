/**
 * The activity log (D-758): every change to entries and media, made in
 * the admin, by an app through the MCP server, on the command line, or
 * through the API, with Undo for what can be put back.
 */

import { request } from './api';
import { t } from './messages';

export type ActivityVia = 'admin' | 'mcp' | 'cli' | 'api';

export interface ActivityChange {
	id: string;
	time: string;
	action: string;
	summary: string;
	title: string;
	kind: 'entry' | 'media';
	subject: string;
	// The admin screen it's changed on, or `null` once it's gone.
	link: string | null;
	account: { username: string | null; name: string };
	via: ActivityVia;
	// The app's or token's name.
	client: string | null;
	undone: boolean;
	// Whether it undid another change.
	undoes: boolean;
	undo: { available: boolean; reason: string | null };
}

export interface ActivityPage {
	changes: ActivityChange[];
	total: number;
	page: number;
	pages: number;
	accounts: { username: string; name: string }[];
	clients: string[];
	actions: { value: string; label: string }[];
	via: { value: ActivityVia; label: string }[];
}

export interface ActivityQuery {
	account?: string;
	via?: string;
	client?: string;
	action?: string;
	subject?: string;
	page?: number;
}

const VIA: Record<ActivityVia, string> = { admin: t('activity.via_admin', 'Admin'), mcp: 'MCP', cli: t('activity.via_cli', 'Command line'), api: 'API' };

/**
 * How a change came: an app's or token's name, else the way.
 */
export function changedHow(change: ActivityChange): string {
	return change.client ?? VIA[change.via];
}

export function loadActivity(query: ActivityQuery = {}): Promise<ActivityPage> {
	const params = new URLSearchParams(Object.entries(query).filter((entry) => entry[1] !== undefined && entry[1] !== '').map(([key, value]) => [key, String(value)]));
	const search = params.toString();

	return request<ActivityPage>('GET', `/activity${search === '' ? '' : `?${search}`}`);
}

/**
 * Undoes a change, and resolves it as it is now.
 */
export async function undoChange(id: string): Promise<ActivityChange> {
	return (await request<{ change: ActivityChange }>('POST', `/activity/${encodeURIComponent(id)}/undo`)).change;
}

// Whether a change's before and after can be shown (D-902).
export type DiffState = 'shown' | 'new' | 'expired' | 'not-kept' | 'unknown';

// What a change changed: its fields, before and after (`null` where it
// had none), its body's, and its key when it moved.
export interface ChangeDiff {
	state: DiffState;
	fields: { name: string; before: unknown; after: unknown }[];
	body: { before: string; after: string } | null;
	moved: { from: string; to: string } | null;
}

// One change, for its screen.
export interface ChangeDetail {
	change: ActivityChange;
	actionLabel: string;
	// The change it undid, and the one that undid it, by id.
	undoes: string | null;
	undoneBy: string | null;
	diff: ChangeDiff;
}

export function loadChange(id: string): Promise<ChangeDetail> {
	return request<ChangeDetail>('GET', `/activity/${encodeURIComponent(id)}`);
}

/**
 * A field's value as the comparison shows it: text as it is, a list of
 * plain values joined, anything else as JSON, and nothing as empty.
 */
export function shownValue(value: unknown): string {
	if (value === null || value === undefined) {
		return '';
	}

	if (typeof value === 'string') {
		return value;
	}

	if (typeof value === 'number' || typeof value === 'boolean') {
		return String(value);
	}

	if (Array.isArray(value) && value.every((item) => typeof item === 'string' || typeof item === 'number')) {
		return value.join(', ');
	}

	return JSON.stringify(value, null, 2);
}
