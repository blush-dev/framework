/**
 * The site's roles and accounts (`GET roles`, `GET accounts`, D-249), and
 * changing them (D-312): accounts made with a password link, changed,
 * suspended, and removed; roles made, changed, reset, and deleted.
 */

import { ref } from 'vue';
import { request, type ContentTypeSummary, type EntryStatus } from './api';
import type { ContentAction } from './session';
import { siteDateTime } from './dates';
import { formatDate } from './format';

export interface CapabilityInfo {
	name: string;
	label: string;
	// The group the role screen shows it in: Media, Site, a type's plural…
	group: string;
	// A content capability's type (`*` for every type) and action (D-359).
	type?: string;
	action?: ContentAction;
}

// The role an account holds when it holds nothing else, which never has
// a capability (D-365).
export const MEMBER = 'member';

// The role that always has everything, and that only an owner gives or
// changes the accounts of (D-500).
export const OWNER = 'owner';

// Whether you may name the site's first owner, yourself included: it has
// none, and you can do all an administrator can (D-500). Set by
// `loadAccounts()`.
export const claimable = ref(false);

// The profile picker's choice for making a new profile (D-369): a slug
// never has a ":".
export const NEW_PROFILE = ':new';

// Whether a role's screen shows each capability's key; it lasts while
// the admin's open, on every role.
export const showKeys = ref(false);

// A content type, as the role screen shows it.
export type RoleType = Pick<ContentTypeSummary, 'name' | 'kind' | 'terms' | 'icon'> & { label: string };

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
	// Its email address (D-370); `null` only for one saved before emails.
	email: string | null;
	// Its own name, if it has one (D-322).
	name: string | null;
	// What the admin calls it: its own name, else its profile's title,
	// else its username (D-370).
	displayName: string;
	roles: string[];
	// The slug of the profile it's linked to, if any.
	author: string | null;
	// That profile (D-353), or `null`.
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
	types: RoleType[];
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
	path: string;
	id: string | null;
	type: string;
	handle: string | null;
	slug: string;
	title: string;
	status: EntryStatus;
	// Its page on the site, if profiles have pages.
	url: string | null;
	// How many published entries credit it.
	uses: number;
}

// Where a profile appears: one credit relation from one type (D-602).
export interface ProfileAppearance {
	type: string;
	typeLabel: string;
	relation: string;
	label: string;
	// Published entries crediting them there.
	entries: number;
	// The archive's address, or `null` when the relation has none there.
	archive: string | null;
	// The page written for that archive, or `null` when it shows the
	// profile's own bio.
	page: { path: string; id: string | null; type: string; handle: string | null; title: string; status: EntryStatus } | null;
}

// A profile's screen (`GET profiles/{slug}`, D-353).
export interface ProfileDetail {
	profile: {
		slug: string;
		title: string;
		subtitle: string | null;
		avatar: string | null;
		status: EntryStatus;
		path: string;
		// `null` for a file without a valid id (D-477).
		id: string | null;
		type: string;
		handle: string | null;
		url: string | null;
		uses: number;
		// `false` when it's locked against linking (D-605).
		linkable: boolean;
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
	status: EntryStatus;
	account: { username: string; displayName: string } | null;
	// `false` when it's locked against linking (D-605).
	linkable: boolean;
}

export async function loadLinkable(): Promise<LinkableProfile[]> {
	return (await request<{ profiles: LinkableProfile[] }>('GET', '/profiles')).profiles;
}

export function loadProfile(slug: string): Promise<ProfileDetail> {
	return request<ProfileDetail>('GET', `/profiles/${encodeURIComponent(slug)}`);
}

// Locks a profile against being linked to an account, or unlocks it
// (D-605).
export function setLinkable(slug: string, linkable: boolean): Promise<{ linkable: boolean }> {
	return request('PATCH', `/profiles/${encodeURIComponent(slug)}`, { linkable });
}

// Writes the page for a profile's archive under a type's credit relation.
export function writeArchivePage(slug: string, type: string, relation: string): Promise<{ id: string | null; type: string; handle: string | null }> {
	return request('POST', `/profiles/${encodeURIComponent(slug)}/pages`, { type, relation });
}

// Moves that page to the trash, so the archive shows the profile's body
// again (D-370).
export function removeArchivePage(slug: string, type: string, relation: string): Promise<{ removed: string }> {
	return request('DELETE', `/profiles/${encodeURIComponent(slug)}/pages/${encodeURIComponent(type)}/${encodeURIComponent(relation)}`);
}

export function loadRoles(): Promise<RoleList> {
	return request<RoleList>('GET', '/roles');
}

export async function loadAccounts(): Promise<AccountInfo[]> {
	const answer = await request<{ accounts: AccountInfo[]; claimable: boolean }>('GET', '/accounts');

	claimable.value = answer.claimable;

	return answer.accounts;
}

/**
 * The link just made, kept in memory only so the account's screen can
 * show it once after creating the account; it's never stored.
 */
export const freshLink = ref<{ username: string; link: PasswordLink } | null>(null);

export function createAccount(account: { username: string; email: string; name: string | null; roles: string[]; author: string | null; profileTitle?: string }): Promise<{ account: AccountInfo; link: PasswordLink }> {
	return request('POST', '/accounts', account);
}

export async function updateAccount(username: string, changes: { roles?: string[]; author?: string | null; name?: string | null; email?: string; suspended?: boolean }): Promise<AccountInfo> {
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
 * Whether a capability with `*` words matches another: `content.*.edit`
 * matches `content.post.edit` (D-359).
 */
export function matches(pattern: string, capability: string): boolean {
	if (pattern === capability) {
		return true;
	}

	if (!pattern.includes('*')) {
		return false;
	}

	const parts = pattern.split('.');
	const words = capability.split('.');

	return parts.length === words.length && parts.every((part, index) => part === '*' || part === words[index]);
}

/**
 * Whether a role grants a capability.
 */
export function grants(role: Pick<RoleInfo, 'capabilities'>, capability: string, all: string): boolean {
	return role.capabilities.includes(all) || role.capabilities.some((granted) => matches(granted, capability));
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
 * When something happened, from a Unix time: in the site's formats
 * (D-693), or compactly for a list's column.
 */
export function when(time: number | null, compact = false): string {
	if (time === null || time === 0) {
		return 'Never';
	}

	return compact ? formatDate(new Date(time * 1000).toISOString()) : siteDateTime(time);
}
