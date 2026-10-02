/**
 * Who's signed in, shared by every screen.
 */

import { reactive, readonly } from 'vue';
import { request, setCsrfToken, type Account, type SessionState } from './api';
import { followTheme } from './admin-theme';
import { followAccount } from './color-scheme';

const state = reactive<{ account: Account | null; loaded: boolean }>({
	account: null,
	loaded: false
});

export const session = readonly(state);

function apply(data: SessionState): void {
	state.account = data.account;
	state.loaded  = true;
	setCsrfToken(data.csrfToken ?? null);

	// The account's own look, on any device (D-235).
	if (data.account !== null) {
		followAccount(data.account.preferences);
		followTheme(data.account.preferences);
	}
}

/**
 * Asks the server who's signed in, once per page load unless forced.
 */
export async function loadSession(force = false): Promise<void> {
	if (!state.loaded || force) {
		apply(await request<SessionState>('GET', '/session'));
	}
}

/**
 * Signs in; throws an `ApiError` when the server refuses.
 */
export async function signIn(username: string, password: string): Promise<void> {
	apply(await request<SessionState>('POST', '/login', { username, password }));
}

/**
 * Signs out.
 */
export async function signOut(): Promise<void> {
	await request<void>('POST', '/logout');
	apply({ account: null });
}

/**
 * Sets the signed-in account's own name, or takes it away with an empty
 * one (D-322); throws an `ApiError` when the server refuses.
 */
export async function saveName(name: string): Promise<void> {
	const answer = await request<{ name: string | null; displayName: string }>('PATCH', '/profile', { name });

	if (state.account !== null) {
		state.account.name        = answer.name;
		state.account.displayName = answer.displayName;
	}
}

/**
 * Whether the signed-in account has a capability.
 */
export function can(capability: string): boolean {
	return state.account?.capabilities.includes(capability) ?? false;
}

// What a role may do to a content type's entries (D-359).
export type ContentAction = 'create' | 'edit' | 'edit.others' | 'publish' | 'publish.others' | 'delete' | 'delete.others';

/**
 * Whether the signed-in account may do something to a content type's
 * entries (`content.{type}.{action}`). The server lists each type's
 * capabilities the account has, so every type's are already spread.
 */
export function canType(type: string, action: ContentAction): boolean {
	return can(`content.${type}.${action}`);
}

/**
 * Whether the signed-in account may do something to some content type's
 * entries.
 */
export function canAnyType(action: ContentAction): boolean {
	const suffix = `.${action}`;

	return state.account?.capabilities.some((name) => name.startsWith('content.') && !name.startsWith('content.*.') && name.endsWith(suffix) && name.slice(8, -suffix.length).split('.').length === 1) ?? false;
}
