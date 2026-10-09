/**
 * The Redirects screen's API and words (D-686): the list, the form's
 * checks, and the writes, each named by a row's old path (`from`).
 */

import { request } from './api';

export type RedirectStatus = 301 | 302 | 303 | 307 | 308;

// A line of a message: plain, a path in code type, or a title in bold.
export type MessagePart = string | { code: string } | { strong: string };

export interface RedirectMessage {
	kind: 'bad' | 'warn' | 'say';
	parts: MessagePart[];
	// The one fix it offers, an action the screen knows.
	fix: { label: string; action: string; value?: unknown } | null;
}

// A row as the table keeps it, sent back for Undo.
export interface StoredRedirect {
	from: string;
	to?: string;
	entry?: string;
	status?: number;
	added?: string;
	by?: string;
	via?: string;
}

export interface RedirectRow {
	from: string;
	to: string | null;
	// The entry it leads to, by id, and how it stands now.
	entry: { id: string; title: string; url: string | null; type: string | null; state: 'live' | 'trash' | 'draft' | 'scheduled' | 'hidden' | 'deleted' } | null;
	status: RedirectStatus;
	added: string | null;
	// The account that added it; a removed account has no name.
	by: { name: string | null; username: string | null; you: boolean } | null;
	via: 'rename' | 'move' | 'import' | null;
	problem: { kind: 'code' | 'live' | 'gone' | 'chain' | 'missing'; label: string; message: RedirectMessage; final: { entry?: string; to?: string } | null } | null;
	stored: StoredRedirect;
}

export type TraceHop =
	| { t: 'page'; path: string; title: string | null }
	| { t: 'redirect'; path: string; code: boolean; from: string; status: RedirectStatus; target: { kind: 'entry' | 'gone' | 'path' | 'url'; title: string | null; path: string | null; url: string | null } }
	| { t: 'away'; url: string; host: string }
	| { t: 'gone'; title: string | null }
	| { t: 'missing'; path: string }
	| { t: 'loop'; path: string };

export interface RedirectList {
	redirects: RedirectRow[];
	total: number;
	page: number;
	pages: number;
	per: number;
	counts: { all: number; permanent: number; temporary: number; problems: number };
	// The site's code's redirects, which come first.
	code: { from: string; to: string; status: RedirectStatus }[];
	// Where the searched address goes, when the search is one.
	trace: { path: string | null; other: string | null; hops: TraceHop[]; overruled?: 'page' | 'redirect' | null } | null;
}

export interface RedirectDraft {
	from: string;
	to: string;
	entry: string | null;
	status: RedirectStatus;
	// The old path of the row being changed.
	was: string | null;
}

export interface RedirectCheck {
	from: RedirectMessage[];
	to: RedirectMessage[];
	status: RedirectMessage[];
	row: StoredRedirect | null;
}

/**
 * Each type in words first, its code second (the sketch's R5): what it
 * does, in a sentence, for the form.
 */
export const TYPES: Record<RedirectStatus, { name: string; short: string; say: string }> = {
	301: { name: 'Permanent', short: 'Permanent', say: 'The new address replaces the old one for good. Search engines move the old address\'s standing to it, and browsers may remember the move.' },
	302: { name: 'Temporary', short: 'Temporary', say: 'For a while. Search engines keep the old address, and nothing is remembered.' },
	303: { name: 'See Other', short: 'See Other', say: 'For a form or a program, sending it to another address to read the result.' },
	307: { name: 'Temporary, Same Method', short: 'Temporary', say: 'Temporary, and a form sent here is sent on as it was. For programs more than people.' },
	308: { name: 'Permanent, Same Method', short: 'Permanent', say: 'Permanent, and a form sent here is sent on as it was. For programs more than people.' }
};

export const isPermanent = (status: number): boolean => status === 301 || status === 308;

export function loadRedirects(params: URLSearchParams): Promise<RedirectList> {
	return request<RedirectList>('GET', `/redirects?${params.toString()}`);
}

export function checkRedirect(draft: RedirectDraft): Promise<RedirectCheck> {
	return request<RedirectCheck>('POST', '/redirects/check', { ...draft });
}

export function saveRedirect(draft: RedirectDraft): Promise<{ redirect: RedirectRow; was: StoredRedirect | null }> {
	return request('POST', '/redirects', { ...draft });
}

export function deleteRedirects(from: string[]): Promise<{ deleted: StoredRedirect[] }> {
	return request('POST', '/redirects/delete', { from });
}

export function retypeRedirects(from: string[], status: RedirectStatus): Promise<{ changed: StoredRedirect[] }> {
	return request('POST', '/redirects/status', { from, status });
}

/**
 * Puts rows back as they were kept, after removing the ones `remove`
 * names: every Undo on the screen.
 */
export function restoreRedirects(rows: StoredRedirect[], remove: string[] = []): Promise<{ restored: number }> {
	return request('POST', '/redirects/restore', { rows, remove });
}

/**
 * What was done to add a row, for "{verb} by {account}".
 */
export function addedVerb(row: RedirectRow): string {
	switch (row.via) {
		case 'rename':
			return 'Renamed';
		case 'move':
			return 'Moved';
		case 'import':
			return 'Imported';
		default:
			return 'Added';
	}
}
