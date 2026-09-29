/**
 * Who's signed in, shared by every screen.
 */

import { reactive, readonly } from 'vue';
import { request, setCsrfToken, type Account, type SessionState } from './api';
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
 * Whether the signed-in account has a capability.
 */
export function can(capability: string): boolean {
	return state.account?.capabilities.includes(capability) ?? false;
}
