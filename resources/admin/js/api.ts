/**
 * Talks to the admin's JSON API (D-220). Requests send the session cookie
 * and, once signed in, the CSRF token in `X-CSRF-Token`. A failed request
 * throws an `ApiError` with the server's message.
 */

import { config } from './config';

export interface Account {
	username: string;
	author: string | null;
	roles: string[];
	capabilities: string[];
	lastLogin: number | null;
	preferences: Preferences;
}

export type ColorScheme = 'system' | 'light' | 'dark';

export interface Preferences {
	colorScheme: ColorScheme;
}

export interface SessionState {
	account: Account | null;
	csrfToken?: string | null;
}

export interface ActionDescription {
	name: string;
	label: string;
	description: string;
	confirm: string | null;
}

export interface ActionResult {
	successful: boolean;
	message: string;
	details: string[];
}

export interface Dashboard {
	site: { name: string; url: string; environment: string; version: string };
	content: { total: number; published: number; draft: number; scheduled: number };
	actions: ActionDescription[];
}

export interface EntrySummary {
	id: string;
	title: string;
	type: string;
	status: EntryStatus;
	published: string | null;
	updated: string;
	path: string | null;
	authors: string[];
	own: boolean;
	// For a term, how many published entries use it; else `null` (D-236).
	uses: number | null;
}

export type EntryStatus = 'draft' | 'scheduled' | 'published';

/**
 * An entry in the trash (`GET trash`, D-237).
 */
export interface TrashedSummary {
	id: string;
	entry: string;
	title: string;
	type: string | null;
	bundle: boolean;
	trashed: string;
	authors: string[];
	own: boolean;
}

export interface EntryList {
	status: EntryStatus | 'any';
	type: string | null;
	search: string;
	total: number;
	page: number;
	pages: number;
	per: number;
	entries: EntrySummary[];
}

export interface ContentTypeSummary {
	name: string;
	label: string;
	singular: string;
	kind: 'collection' | 'taxonomy' | 'pages';
	dated: boolean;
}

/**
 * A schema field as the server describes it (`Field::toArray()`); the
 * type's own settings (`options`, `item`, `to`, …) sit beside the shared
 * ones.
 */
export interface FieldDescription {
	name: string;
	type: string;
	aliases?: string[];
	required?: boolean;
	default?: unknown;
	label?: string;
	description?: string;
	options?: string[];
	item?: FieldDescription;
	to?: string;
	multiple?: boolean;
	integer?: boolean;
	min?: number;
	max?: number;
	[setting: string]: unknown;
}

/**
 * An entry for editing (`GET entries/{id}`, D-229).
 */
export interface EntryDetail {
	id: string;
	revision: string;
	title: string;
	status: EntryStatus;
	own: boolean;
	url: string | null;
	type: {
		name: string;
		kind: ContentTypeSummary['kind'];
		dated: boolean;
		fields: FieldDescription[];
	};
	values: Record<string, unknown>;
	extra: Record<string, unknown>;
	body: string;
	can: { edit: boolean; publish: boolean; delete: boolean };
	violations: Violation[];
}

export interface Violation {
	field: string;
	message: string;
	severity: 'error' | 'warning' | 'notice';
}

export interface Health {
	checked: number;
	strict: boolean;
	counts: { error: number; warning: number; notice: number | null };
	files: { path: string; violations: Violation[] }[];
}

export interface PreviewLink {
	url: string;
	expires: string;
}

export class ApiError extends Error {
	constructor(message: string, public readonly status: number) {
		super(message);
	}
}

/**
 * The API path of an entry, from its id (its source path).
 */
export function entryPath(id: string): string {
	return `/entries/${id.split('/').map(encodeURIComponent).join('/')}`;
}

let csrfToken: string | null = null;

/**
 * Sets the token later requests send.
 */
export function setCsrfToken(token: string | null): void {
	csrfToken = token;
}

/**
 * Sends a request and returns the decoded answer (`undefined` for a 204).
 */
export async function request<T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', path: string, body?: unknown): Promise<T> {
	const headers: Record<string, string> = { Accept: 'application/json' };

	if (body !== undefined) {
		headers['Content-Type'] = 'application/json';
	}

	if (csrfToken !== null && method !== 'GET') {
		headers['X-CSRF-Token'] = csrfToken;
	}

	let response: Response;

	try {
		response = await fetch(config.api + path, {
			method,
			headers,
			credentials: 'same-origin',
			body: body === undefined ? undefined : JSON.stringify(body)
		});
	} catch {
		throw new ApiError('The site couldn\'t be reached. Check your connection and try again.', 0);
	}

	if (response.status === 204) {
		return undefined as T;
	}

	const data: unknown = await response.json().catch(() => null);

	if (!response.ok) {
		const message = typeof data === 'object' && data !== null && 'error' in data && typeof data.error === 'string'
			? data.error
			: `The request failed (${response.status}).`;

		throw new ApiError(message, response.status);
	}

	return data as T;
}
