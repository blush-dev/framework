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
	// What the admin calls it: its profile's title, else the name, else
	// the username (D-329).
	displayName: string;
	roles: string[];
	// The slug of the profile it's linked to, if any.
	author: string | null;
	// That profile, when it has a file (D-353), or `null`.
	profile: AccountProfile | null;
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

// An account's profile: its public side (D-353).
export interface AccountProfile {
	id: string;
	handle: string | null;
	slug: string;
	title: string;
	status: EntryStatus;
	// Its page on the site, if profiles have pages.
	url: string | null;
	// How many published entries credit it.
	uses: number;
}

// Where a profile appears: one people field of one type (D-353).
export interface ProfileAppearance {
	type: string;
	typeLabel: string;
	field: string;
	label: string;
	// Published entries crediting them there.
	entries: number;
	// The archive's address, or `null` when the field has none.
	archive: string | null;
	// The page written for that archive, or `null` when it shows the
	// profile's own bio.
	page: { id: string; handle: string | null; title: string; status: EntryStatus } | null;
}

// A profile's screen (`GET profiles/{slug}`, D-353).
export interface ProfileDetail {
	profile: {
		slug: string;
		title: string;
		subtitle: string | null;
		avatar: string | null;
		// `null` for a profile credited without a file.
		status: EntryStatus | null;
		virtual: boolean;
		id: string | null;
		handle: string | null;
		url: string | null;
		uses: number;
	};
	appears: ProfileAppearance[];
	// Whether an account is linked to it, and which, when you manage
	// accounts.
	linked: boolean;
	account: AccountInfo | null;
}

// A profile an account can be linked to (`GET profiles`, D-356), and the
// account already linked to it, if any.
export interface LinkableProfile {
	slug: string;
	title: string;
	// `null` for one credited without a file.
	status: EntryStatus | null;
	account: { username: string; displayName: string } | null;
}

export async function loadLinkable(): Promise<LinkableProfile[]> {
	return (await request<{ profiles: LinkableProfile[] }>('GET', '/profiles')).profiles;
}

export function loadProfile(slug: string): Promise<ProfileDetail> {
	return request<ProfileDetail>('GET', `/profiles/${encodeURIComponent(slug)}`);
}

// Writes the page for a profile's archive under a type's people field.
export function writeArchivePage(slug: string, type: string, field: string): Promise<{ id: string; handle: string | null }> {
	return request('POST', `/profiles/${encodeURIComponent(slug)}/pages`, { type, field });
}

// Moves that page to the trash, so the archive shows the bio again.
export function removeArchivePage(slug: string, type: string, field: string): Promise<{ removed: string }> {
	return request('DELETE', `/profiles/${encodeURIComponent(slug)}/pages/${encodeURIComponent(type)}/${encodeURIComponent(field)}`);
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
