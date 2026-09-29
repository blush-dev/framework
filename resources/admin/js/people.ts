/**
 * The site's roles and accounts (`GET roles`, `GET accounts`, D-249), for
 * the read-only people screens.
 */

import { request } from './api';

export interface CapabilityInfo {
	name: string;
	label: string;
}

export interface RoleInfo {
	name: string;
	label: string;
	// `*` grants every capability.
	capabilities: string[];
	builtIn: boolean;
	accounts: string[];
}

export interface AccountInfo {
	username: string;
	roles: string[];
	author: string | null;
	created: number;
	lastLogin: number | null;
}

export interface RoleList {
	capabilities: CapabilityInfo[];
	roles: RoleInfo[];
	all: string;
}

export function loadRoles(): Promise<RoleList> {
	return request<RoleList>('GET', '/roles');
}

export async function loadAccounts(): Promise<AccountInfo[]> {
	return (await request<{ accounts: AccountInfo[] }>('GET', '/accounts')).accounts;
}

/**
 * Whether a role grants a capability.
 */
export function grants(role: RoleInfo, capability: string, all: string): boolean {
	return role.capabilities.includes(all) || role.capabilities.includes(capability);
}

/**
 * Capabilities in groups by their first word ("content.edit" is Content).
 */
export function capabilityGroups(capabilities: CapabilityInfo[]): { name: string; capabilities: CapabilityInfo[] }[] {
	const groups = new Map<string, CapabilityInfo[]>();

	for (const capability of capabilities) {
		const first = capability.name.split('.')[0] ?? capability.name;
		const name  = first.charAt(0).toUpperCase() + first.slice(1);

		groups.set(name, [...(groups.get(name) ?? []), capability]);
	}

	return [...groups].map(([name, list]) => ({ name, capabilities: list }));
}

/**
 * When something happened, from a Unix time, for reading.
 */
export function when(time: number | null): string {
	return time === null || time === 0
		? 'Never'
		: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(time * 1000));
}
