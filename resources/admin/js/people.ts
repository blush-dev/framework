/**
 * The site's roles and accounts (`GET roles`, `GET accounts`, D-249), and
 * changing them (D-312): accounts made with a password link, changed,
 * suspended, and removed; roles made, changed, reset, and deleted.
 */

import { ref } from 'vue';
import { request, type EntryStatus } from './api';

export interface CapabilityInfo {
	name: string;
	label: string;
}

// Where a role comes from: built in, a built-in whose capabilities were
// changed here, made here, or `config/auth.php`.
export type RoleOrigin = 'built-in' | 'changed' | 'custom' | 'config';

export interface RoleInfo {
	name: string;
	label: string;
	description: string;
	// `*` grants every capability.
	capabilities: string[];
	builtIn: boolean;
	origin: RoleOrigin;
	accounts: { username: string; displayName: string }[];
	// Whether you may give it to accounts: you can do all it allows.
	grantable: boolean;
	// Whether you may change it.
	editable: boolean;
	// A changed built-in's own capabilities.
	defaults?: string[];
}

export type AccountStatus = 'active' | 'invited' | 'suspended';

export interface AccountInfo {
	username: string;
	// Its own name, if it has one (D-322).
	name: string | null;
	// What the admin calls it: the author page's title, else the name,
	// else the username (D-329).
	displayName: string;
	roles: string[];
	author: string | null;
	// Its author page, whose title is its one name (D-329), or `null`.
	authorPage: { id: string; handle: string | null } | null;
	created: number;
	lastLogin: number | null;
	status: AccountStatus;
	// The password link it has, if any (never its token).
	link: { expires: number; expired: boolean } | null;
	// Whether you may change it (not your own, and it can't do more).
	manages: boolean;
}

export interface RoleList {
	capabilities: CapabilityInfo[];
	roles: RoleInfo[];
	all: string;
}

// A link for choosing a password, to send; shown once.
export interface PasswordLink {
	url: string;
	expires: number;
}

// One person (`GET people`, D-329): an author entry, an author credited
// without one, or an account with no author entry, by name.
export interface PersonInfo {
	name: string;
	author: string | null;
	entry: { id: string; handle: string | null; status: EntryStatus } | null;
	// Credited without an author entry.
	virtual: boolean;
	// Their account, when they have one and you manage accounts.
	account: AccountInfo | null;
	// How many published entries credit them.
	uses: number;
}

export async function loadPeople(): Promise<PersonInfo[]> {
	return (await request<{ people: PersonInfo[] }>('GET', '/people')).people;
}

export function loadRoles(): Promise<RoleList> {
	return request<RoleList>('GET', '/roles');
}

export async function loadAccounts(): Promise<AccountInfo[]> {
	return (await request<{ accounts: AccountInfo[] }>('GET', '/accounts')).accounts;
}

/**
 * The link just made, kept in memory only so the account's screen can
 * show it once after creating the account; it's never stored.
 */
export const freshLink = ref<{ username: string; link: PasswordLink } | null>(null);

export function createAccount(username: string, roles: string[], author: string | null, name: string | null): Promise<{ account: AccountInfo; link: PasswordLink }> {
	return request('POST', '/accounts', { username, roles, author, name });
}

export async function updateAccount(username: string, changes: { roles?: string[]; author?: string | null; name?: string | null; suspended?: boolean }): Promise<AccountInfo> {
	return (await request<{ account: AccountInfo }>('PATCH', `/accounts/${encodeURIComponent(username)}`, changes)).account;
}

export function makePasswordLink(username: string): Promise<{ account: AccountInfo; link: PasswordLink }> {
	return request('POST', `/accounts/${encodeURIComponent(username)}/link`);
}

export function removeAccount(username: string): Promise<void> {
	return request('DELETE', `/accounts/${encodeURIComponent(username)}`);
}

export async function createRole(role: { name: string; label: string; description: string; capabilities: string[] }): Promise<RoleInfo> {
	return (await request<{ role: RoleInfo }>('POST', '/roles', role)).role;
}

export async function updateRole(name: string, changes: { label?: string; description?: string; capabilities?: string[] }): Promise<RoleInfo> {
	return (await request<{ role: RoleInfo }>('PATCH', `/roles/${encodeURIComponent(name)}`, changes)).role;
}

/**
 * Deletes a custom role, or resets a changed built-in (and returns it).
 */
export async function deleteRole(name: string): Promise<RoleInfo | null> {
	return (await request<{ role: RoleInfo | null }>('DELETE', `/roles/${encodeURIComponent(name)}`)).role;
}

/**
 * Sets a password with a link's account and token, which signs in.
 */
export function setPassword(account: string, token: string, password: string): Promise<void> {
	return request('POST', '/set-password', { account, token, password });
}

/**
 * Whether a role grants a capability.
 */
export function grants(role: Pick<RoleInfo, 'capabilities'>, capability: string, all: string): boolean {
	return role.capabilities.includes(all) || role.capabilities.includes(capability);
}

/**
 * A role's key from its name: lowercase words joined by hyphens.
 */
export function roleKeyOf(label: string): string {
	return label.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').replace(/^[^a-z]+/, '');
}

/**
 * Where a role comes from, for reading.
 */
export function originOf(role: RoleInfo): string {
	return {
		'built-in': 'Built in',
		changed: 'Built in, changed here',
		custom: 'Made here',
		config: 'config/auth.php'
	}[role.origin];
}

/**
 * Up to two initials from a name, for an avatar: "Jane Doe" is JD.
 */
export function initials(name: string): string {
	return name.split(/\s+/).filter((part) => part !== '').slice(0, 2).map((part) => Array.from(part)[0]?.toUpperCase() ?? '').join('') || '?';
}

/**
 * An account's status as a pill: the word and its look.
 */
export function statusPill(status: AccountStatus): { label: string; kind: string } {
	return {
		active: { label: 'Active', kind: 'pill--good' },
		invited: { label: 'Invited', kind: 'pill--warn' },
		suspended: { label: 'Suspended', kind: 'pill--danger' }
	}[status];
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
