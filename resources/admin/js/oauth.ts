/**
 * Connecting an app (D-752): what the Connect an App screen shows for an
 * app's request, and the person's answer, which sends them back to the
 * app.
 */

import { request } from './api';
import type { CapabilityInfo, RoleType } from './people';

export type ConnectScope = 'read' | 'drafts' | 'account';

export interface ConnectRequest {
	site: { name: string; url: string };
	// `verified` when the app proved where it's from.
	client: { name: string; host: string | null; verified: boolean };
	account: { username: string; name: string };
	// Whether the account may connect apps (`api.connect`).
	mayConnect: boolean;
	asked: ConnectScope;
	// Each choice, `available` when it's within what the app asked for.
	scopes: { key: ConnectScope; label: string; available: boolean }[];
	// What a custom choice may pick from.
	capabilities: CapabilityInfo[];
	types: RoleType[];
	// Set when the app was approved before with as much: go at once.
	redirect: string | null;
}

/**
 * What the screen shows for the app's request, its query.
 */
export function describeConnect(query: string): Promise<ConnectRequest> {
	return request<ConnectRequest>('GET', `/oauth/authorize?${query}`);
}

/**
 * Answers the app: allowed, with a choice of what it may do, or denied.
 * Resolves the address to send the person back to.
 */
export async function answerConnect(params: Record<string, string>, decision: 'allow' | 'deny', scope: ConnectScope | 'custom', capabilities: string[] = []): Promise<string> {
	return (await request<{ redirect: string }>('POST', '/oauth/authorize', { params, decision, scope, capabilities })).redirect;
}
