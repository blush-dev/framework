<script setup lang="ts">
/**
 * One account (D-249, D-312; the prototype's account screen): who it is,
 * its name (D-322), its public profile (D-353), its password link, and
 * its roles, which save as they're ticked. A Danger Zone suspends or reinstates it and removes it.
 *
 * Blush sends no email, so a password is never set here: **Make a
 * password link** gives a link to copy and send, for a new account or a
 * forgotten password. A link just made (here, or by New Account) is
 * shown once; only a hash of it is kept.
 *
 * Your own account, and an account that can do things you can't, are
 * shown without the controls (`PeopleRules`); your password is on Your
 * profile.
 */

import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AuthorField from '../components/AuthorField.vue';
import StatusPill from '../components/StatusPill.vue';
import RoleChecks from '../components/RoleChecks.vue';
import { ApiError, entryPath, entryRoute, request, type EntryDetail } from '../api';
import { plural } from '../format';
import { slugOf } from '../references';
import { freshLink, initials, loadAccounts, loadRoles, makePasswordLink, removeAccount, statusPill, updateAccount, when, type AccountInfo, type PasswordLink, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { can, session } from '../session';
import { profileType } from '../types';
import { toast } from '../toast';

const route    = useRoute();
const router   = useRouter();
const accounts = ref<AccountInfo[] | null>(null);
const roles    = ref<RoleList | null>(null);
const error    = ref('');

Promise.all([loadAccounts(), loadRoles()]).then(([list, answer]) => {
	accounts.value = list;
	roles.value    = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be loaded.';
});

const account = computed(() => accounts.value?.find((item) => item.username === route.params.username));
const yours   = computed(() => account.value?.username === session.account?.username);
const label   = (name: string): string => roles.value?.roles.find((role) => role.name === name)?.label ?? name;

watch(account, (value) => {
	screenTitle.value = value ? value.displayName : null;
}, { immediate: true });

function replace(changed: AccountInfo): void {
	accounts.value = accounts.value?.map((item) => item.username === changed.username ? changed : item) ?? null;
}

// Roles save as they're ticked; a refusal puts them back.
const held       = ref<string[]>([]);
const rolesError = ref('');

watch(account, (value) => {
	held.value = value ? [...value.roles] : [];
}, { immediate: true });

watch(held, async (value, old) => {
	const current = account.value;

	if (current === undefined || value.join() === current.roles.join()) {
		return;
	}

	rolesError.value = '';

	try {
		const changed = await updateAccount(current.username, { roles: value });

		replace(changed);
		toast(`${changed.displayName} is now ${changed.roles.map(label).join(' and ')}`);
	} catch (caught) {
		rolesError.value = caught instanceof ApiError ? caught.message : 'The roles couldn\'t be saved.';
		held.value       = old;
	}
});

// The name: typed, then saved; an empty one takes it away.
const name      = ref('');
const nameBusy  = ref(false);
const nameError = ref('');

watch(account, (value) => {
	name.value = value?.name ?? '';
}, { immediate: true });

const nameChanged = computed(() => name.value.trim() !== (account.value?.name ?? ''));

async function saveName(): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	nameBusy.value  = true;
	nameError.value = '';

	try {
		const changed = await updateAccount(current.username, { name: name.value.trim() === '' ? null : name.value });

		replace(changed);
		toast(changed.name === null ? `Removed ${current.username}'s name` : `Renamed ${current.username} to ${changed.name}`);
	} catch (caught) {
		nameError.value = caught instanceof ApiError ? caught.message : 'The name couldn\'t be saved.';
	} finally {
		nameBusy.value = false;
	}
}

// The public profile (D-353): linked, linked but not yet public, or not
// linked, with what each allows. Linking picks an existing profile;
// creating makes a draft named for the account, links it, and opens it.
const profileMode  = ref<'' | 'link' | 'create'>('');
const pick         = ref('');
const newName      = ref('');
const profileBusy  = ref(false);
const profileError = ref('');

watch(account, (value) => {
	profileMode.value = '';
	pick.value        = value?.author ?? '';
	newName.value     = value?.name ?? '';
}, { immediate: true });

const newSlug = computed(() => slugOf(newName.value));

async function changeLink(author: string | null, done: string): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	profileBusy.value  = true;
	profileError.value = '';

	try {
		replace(await updateAccount(current.username, { author }));
		profileMode.value = '';
		toast(done);
	} catch (caught) {
		profileError.value = caught instanceof ApiError ? caught.message : 'The profile couldn\'t be linked.';
	} finally {
		profileBusy.value = false;
	}
}

function linkProfile(): Promise<void> {
	return changeLink(pick.value === '' ? null : pick.value, `Linked ${account.value?.displayName ?? ''} to ${pick.value}`);
}

function unlinkProfile(): Promise<void> {
	const current = account.value;

	if (current === undefined || !window.confirm(`Unlink ${current.displayName}'s profile? The profile and its bylines stay, as a guest profile.`)) {
		return Promise.resolve();
	}

	return changeLink(null, `Unlinked ${current.displayName}'s profile`);
}

async function createProfile(): Promise<void> {
	const current = account.value;

	if (current === undefined || profileType.value === null || newSlug.value === '') {
		return;
	}

	profileBusy.value  = true;
	profileError.value = '';

	try {
		const created = await request<EntryDetail>('POST', '/entries', { type: profileType.value, title: newName.value.trim(), slug: newSlug.value, status: 'draft' });

		replace(await updateAccount(current.username, { author: created.slug }));
		toast(`Created a profile for ${current.displayName}`);
		await router.push(entryRoute(created));
	} catch (caught) {
		profileError.value = caught instanceof ApiError ? caught.message : 'The profile couldn\'t be created.';
	} finally {
		profileBusy.value = false;
	}
}

// Gives the profile an account links to its file: a draft, titled with
// the account's name, opened to write the bio.
async function createLinked(): Promise<void> {
	const current = account.value;

	if (current === undefined || !current.author || profileType.value === null) {
		return;
	}

	profileBusy.value  = true;
	profileError.value = '';

	try {
		const created = await request<EntryDetail>('POST', '/entries', { type: profileType.value, title: current.name ?? current.displayName, slug: current.author });

		await router.push(entryRoute(created));
	} catch (caught) {
		profileError.value = caught instanceof ApiError ? caught.message : 'The profile couldn\'t be created.';
	} finally {
		profileBusy.value = false;
	}
}

// Publishes a draft profile, so its page and bylines go live.
async function publishProfile(): Promise<void> {
	const current = account.value;
	const page    = current?.profile;

	if (current === undefined || !page) {
		return;
	}

	profileBusy.value  = true;
	profileError.value = '';

	try {
		const loaded = await request<EntryDetail>('GET', entryPath(page.id));

		await request<EntryDetail>('PATCH', entryPath(page.id), { revision: loaded.revision, status: 'published' });
		accounts.value = await loadAccounts();
		toast(`Published ${page.title || page.slug}`);
	} catch (caught) {
		profileError.value = caught instanceof ApiError ? caught.message : 'The profile couldn\'t be published.';
	} finally {
		profileBusy.value = false;
	}
}

// The password link: the one just made, shown once.
const link      = ref<PasswordLink | null>(null);
const linkBusy  = ref(false);
const linkError = ref('');
const copied    = ref('');

watch(() => route.params.username, (username) => {
	const fresh = freshLink.value;

	link.value      = fresh !== null && fresh.username === username ? fresh.link : null;
	freshLink.value = null;
}, { immediate: true });

onBeforeUnmount(() => {
	freshLink.value = null;
});

async function makeLink(): Promise<void> {
	const current = account.value;

	if (current === undefined || (current.link !== null && !current.link.expired && !window.confirm(`Make a new link for ${current.displayName}? The one they have stops working.`))) {
		return;
	}

	linkBusy.value  = true;
	linkError.value = '';
	copied.value    = '';

	try {
		const answer = await makePasswordLink(current.username);

		replace(answer.account);
		link.value = answer.link;
	} catch (caught) {
		linkError.value = caught instanceof ApiError ? caught.message : 'The link couldn\'t be made.';
	} finally {
		linkBusy.value = false;
	}
}

async function copyLink(): Promise<void> {
	if (link.value === null) {
		return;
	}

	try {
		await navigator.clipboard.writeText(link.value.url);
		copied.value = 'Copied the link.';
		toast('Copied the link');
	} catch {
		copied.value = 'Copying failed; select the link and copy it instead.';
	}
}

function selectAll(event: Event): void {
	(event.target as HTMLInputElement).select();
}

// The Danger Zone.
const dangerBusy  = ref(false);
const dangerError = ref('');

async function setSuspended(suspended: boolean): Promise<void> {
	const current = account.value;

	if (current === undefined || (suspended && !window.confirm(`Suspend ${current.displayName}? They're signed out and can't sign in until you reinstate them.`))) {
		return;
	}

	dangerBusy.value  = true;
	dangerError.value = '';

	try {
		replace(await updateAccount(current.username, { suspended }));
		link.value = null;
		toast(suspended ? `Suspended ${current.displayName}` : `Reinstated ${current.displayName}`);
	} catch (caught) {
		dangerError.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be changed.';
	} finally {
		dangerBusy.value = false;
	}
}

async function remove(): Promise<void> {
	const current = account.value;

	if (current === undefined || !window.confirm(`Remove ${current.displayName}? They're signed out and can't sign in again. Their profile and the entries crediting it stay.`)) {
		return;
	}

	dangerBusy.value  = true;
	dangerError.value = '';

	try {
		await removeAccount(current.username);
		toast(`Removed ${current.displayName}`);
		await router.push({ name: 'accounts' });
	} catch (caught) {
		dangerError.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be removed.';
		dangerBusy.value  = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ account ? account.displayName : 'Account' }}</h1>
			<p v-if="account" class="page-header__hint">
				{{ account.roles.map(label).join(' and ') || 'No roles' }} · last signed in {{ account.lastLogin ? when(account.lastLogin) : 'never' }}
			</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="yours" class="button" :to="{ name: 'profile' }"><AdminIcon name="users" />Your Profile</RouterLink>
			<RouterLink class="button" :to="{ name: 'accounts' }"><AdminIcon name="arrow-left" />All accounts</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="accounts && !account" class="notice notice--error" role="alert">There's no “{{ route.params.username }}” account.</p>

	<p v-if="account && yours" class="notice notice--warn"><span>This is your account, so its roles and standing are changed by someone else, or with <code>bin/blush</code>. Your password is on <RouterLink :to="{ name: 'profile' }">Your profile</RouterLink>.</span></p>
	<p v-else-if="account && !account.manages" class="notice notice--warn"><span>{{ account.displayName }} can do things you can't, so you can't change it.</span></p>

	<div v-if="account" class="detail">
		<div class="detail__side">
			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header account-head">
					<span class="account-head__avatar" aria-hidden="true">{{ initials(account.displayName) }}</span>
					<span class="account-head__who">
						<h2 id="account-heading">{{ account.displayName }}</h2>
						<span v-if="account.displayName !== account.username" class="account-head__username mono">{{ account.username }}</span>
					</span>
					<span class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
				</header>
				<div class="panel__body account-body">
					<dl class="facts">
						<div><dt>Created</dt><dd>{{ when(account.created) }}</dd></div>
						<div><dt>Last signed in</dt><dd>{{ when(account.lastLogin) }}</dd></div>
					</dl>

					<p v-if="account.profile" class="field__help">Their name is their profile's title, everywhere: {{ account.displayName }}.</p>

					<form v-if="account.manages && !account.profile" class="field" @submit.prevent="saveName">
						<label for="account-name">Name</label>
						<div class="inline-save">
							<input id="account-name" v-model="name" class="input account-name" autocomplete="off" maxlength="100" :placeholder="account.displayName" :aria-invalid="nameError !== '' || undefined" aria-describedby="account-name-help">
							<button v-if="nameChanged" type="submit" class="button button--small" :disabled="nameBusy">{{ nameBusy ? 'Saving…' : 'Save' }}</button>
						</div>
						<p v-if="nameError" id="account-name-help" class="field__error" role="alert">{{ nameError }}</p>
						<p v-else id="account-name-help" class="field__help">What the admin calls them until they have a profile, whose title is then their name. Without one, it's their username.</p>
					</form>

					<div v-if="account.manages" class="password-link">
						<h3>Password</h3>
						<template v-if="link">
							<p class="field__help">Send this link to {{ account.displayName }} however you like. It's shown once, works once, and lasts until {{ when(link.expires) }}.</p>
							<div class="inline-save">
								<input class="input mono password-link__url" :value="link.url" readonly aria-label="Password link" @focus="selectAll">
								<button type="button" class="button button--small" @click="copyLink"><AdminIcon name="copy" />Copy</button>
							</div>
							<span class="visually-hidden" aria-live="polite">{{ copied }}</span>
						</template>
						<p v-else-if="account.link && account.status === 'invited'" class="field__help">
							{{ account.link.expired ? `Their link expired ${when(account.link.expires)}. Make a new one to send.` : `Invited: their link works until ${when(account.link.expires)}.` }}
						</p>
						<p v-else class="field__help">For a forgotten password: a link to choose a new one. Their password keeps working until the link is used.</p>
						<button v-if="account.status !== 'suspended'" type="button" class="button button--small" :disabled="linkBusy" @click="makeLink"><AdminIcon name="key-round" />{{ linkBusy ? 'Making…' : (account.link ? 'Make a new link' : 'Make a password link') }}</button>
						<p v-if="linkError" class="field__error" role="alert">{{ linkError }}</p>
					</div>
				</div>
			</section>

			<section v-if="profileType" class="panel" aria-labelledby="public-heading">
				<header class="panel__header">
					<h2 id="public-heading">Public Profile</h2>
					<p class="panel__hint">How they appear on the site</p>
				</header>
				<div class="panel__body account-body">
					<template v-if="account.profile">
						<div class="public">
							<span class="account-head__avatar" aria-hidden="true">{{ initials(account.profile.title || account.profile.slug) }}</span>
							<span class="public__who">
								<RouterLink :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
								<span v-if="account.profile.url" class="mono public__meta">{{ account.profile.url }}</span>
								<span class="public__meta">
									<StatusPill :status="account.profile.status" />
									{{ ' ' }}{{ account.profile.status === 'published' ? `· ${plural(account.profile.uses, 'byline', 'bylines')}` : '· nothing answers at this address yet' }}
								</span>
							</span>
						</div>
						<div class="public__buttons">
							<RouterLink class="button button--small" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">Open profile</RouterLink>
							<button v-if="account.profile.status === 'draft' && can('content.publish')" type="button" class="button button--small" :disabled="profileBusy" @click="publishProfile">Publish</button>
							<button v-if="account.manages" type="button" class="button button--small" :disabled="profileBusy" @click="unlinkProfile">Unlink</button>
						</div>
					</template>
					<template v-else-if="account.author">
						<p>Linked to <span class="mono">{{ account.author }}</span>, which has no profile file yet, so bylines show the slug and there's no bio.</p>
						<div class="public__buttons">
							<button v-if="can('content.create')" type="button" class="button button--small" :disabled="profileBusy" @click="createLinked">{{ profileBusy ? 'Creating…' : 'Create it' }}</button>
							<button v-if="account.manages" type="button" class="button button--small" :disabled="profileBusy" @click="unlinkProfile">Unlink</button>
						</div>
					</template>
					<template v-else>
						<p><strong>No public profile.</strong> {{ account.displayName }} doesn't appear on the site, and entries they write show no byline until a profile is linked.</p>
						<div v-if="account.manages && profileMode === ''" class="public__buttons">
							<button type="button" class="button button--small" @click="profileMode = 'link'">Link an existing one</button>
							<button v-if="can('content.create')" type="button" class="button button--small" @click="profileMode = 'create'">Create one</button>
						</div>
						<form v-if="profileMode === 'link'" class="field" @submit.prevent="linkProfile">
							<label for="account-profile">Profile</label>
							<div class="inline-save">
								<AuthorField id="account-profile" v-model="pick" described-by="account-profile-help" :invalid="profileError !== ''" />
								<button type="submit" class="button button--small" :disabled="profileBusy || pick === ''">Link</button>
								<button type="button" class="button button--ghost button--small" @click="profileMode = ''">Cancel</button>
							</div>
							<p id="account-profile-help" class="field__help">A profile's slug. Entries crediting it become theirs.</p>
						</form>
						<form v-if="profileMode === 'create'" class="field" @submit.prevent="createProfile">
							<label for="account-profile-name">Name on the site</label>
							<div class="inline-save">
								<input id="account-profile-name" v-model="newName" class="input" autocomplete="off" maxlength="100" aria-describedby="account-profile-name-help">
								<button type="submit" class="button button--small" :disabled="profileBusy || newSlug === ''">{{ profileBusy ? 'Creating…' : 'Create' }}</button>
								<button type="button" class="button button--ghost button--small" @click="profileMode = ''">Cancel</button>
							</div>
							<p id="account-profile-name-help" class="field__help">A draft profile at <span class="mono">{{ newSlug || '…' }}</span>, linked to this account, opened to write the bio.</p>
						</form>
					</template>
					<p v-if="profileError" class="field__error" role="alert">{{ profileError }}</p>
				</div>
			</section>

			<section v-if="account.manages" class="panel" aria-labelledby="danger-heading">
				<header class="panel__header">
					<h2 id="danger-heading">Danger Zone</h2>
				</header>
				<div class="panel__body danger">
					<div class="danger__buttons">
						<button v-if="account.status === 'suspended'" type="button" class="button button--small" :disabled="dangerBusy" @click="setSuspended(false)">Reinstate</button>
						<button v-else type="button" class="button button--small" :disabled="dangerBusy" @click="setSuspended(true)">Suspend</button>
						<button type="button" class="button button--danger button--small" :disabled="dangerBusy" @click="remove"><AdminIcon name="x" />Remove account</button>
					</div>
					<p v-if="dangerError" class="field__error" role="alert">{{ dangerError }}</p>
					<p class="field__help">Suspending signs them out until you reinstate them. Removing deletes the account; their profile and the entries crediting it stay.</p>
				</div>
			</section>
		</div>

		<section class="panel" aria-labelledby="roles-heading">
			<header class="panel__header">
				<h2 id="roles-heading">Roles</h2>
				<p class="panel__hint">An account can hold more than one</p>
			</header>
			<div class="panel__body">
				<RoleChecks v-model="held" :roles="roles?.roles ?? []" id-prefix="role-" :disabled="!account.manages" :described-by="rolesError ? 'roles-error' : undefined" />
				<p v-if="rolesError" id="roles-error" class="field__error" role="alert">{{ rolesError }}</p>
				<p class="field__help roles-note">What each role allows is on <RouterLink :to="{ name: 'roles' }">Roles</RouterLink>.</p>
			</div>
		</section>
	</div>
</template>

<style scoped>
.public {
	display: flex;
	align-items: center;
	gap: var(--s-3);
}

.public__who {
	display: grid;
	min-width: 0;
}

.public__meta {
	color: var(--fg-3);
}

.public__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.detail__side {
	display: grid;
	gap: var(--s-4);
}

.account-head {
	gap: var(--s-3);
}

.account-head__avatar {
	display: grid;
	flex: none;
	place-items: center;
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 600;
	text-transform: uppercase;
}

.account-head__who {
	display: grid;
	flex: 1;
	min-width: 0;
}

.account-head__username {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.account-body {
	display: grid;
	gap: var(--s-4);
}

.account-body > * + * {
	margin-top: 0;
}

.facts {
	display: grid;
	gap: 8px;
	margin: 0;
}

.facts > * + * {
	margin-top: 0;
}

.facts div {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}

.facts dt {
	color: var(--fg-2);
}

.facts dd {
	margin: 0;
}

.inline-save {
	display: flex;
	align-items: center;
	gap: var(--s-2);
}

.account-name {
	flex: 1;
	min-width: 0;
}

.password-link {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
	padding-top: var(--s-4);
	border-top: 1px solid var(--border);
}

.password-link > * + * {
	margin-top: 0;
}

.password-link h3 {
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
}

.password-link .inline-save {
	justify-self: stretch;
}

.password-link__url {
	flex: 1;
	min-width: 0;
	font-size: var(--text-xs);
}

.danger {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
}

.danger > * + * {
	margin-top: 0;
}

.danger__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.roles-note {
	margin-top: var(--s-3);
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
