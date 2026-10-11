/**
 * The activity log (D-758): every change to entries and media, made in
 * the admin, by an app through the MCP server, on the command line, or
 * through the API, with Undo for what can be put back.
 */

import { request } from './api';

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

const VIA: Record<ActivityVia, string> = { admin: 'Admin', mcp: 'MCP', cli: 'Command line', api: 'API' };

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
