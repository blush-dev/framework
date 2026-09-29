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
	status: 'draft' | 'scheduled';
	published: string | null;
	updated: string;
	path: string | null;
	authors: string[];
	own: boolean;
}

export interface EntryList {
	status: 'draft' | 'scheduled';
	entries: EntrySummary[];
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
export async function request<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<T> {
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
