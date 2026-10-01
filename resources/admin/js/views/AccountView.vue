<script setup lang="ts">
/**
 * One account (D-249, D-312; the prototype's account screen): who it is,
 * its name (D-322), its author, its password link, and its roles, which save as they're
 * ticked. A Danger Zone suspends or reinstates it and removes it.
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
import RoleChecks from '../components/RoleChecks.vue';
import { ApiError } from '../api';
import { freshLink, initials, loadAccounts, loadRoles, makePasswordLink, removeAccount, statusPill, updateAccount, when, type AccountInfo, type PasswordLink, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { session } from '../session';
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

// The author: typed or picked, then saved.
const author      = ref('');
const authorBusy  = ref(false);
const authorError = ref('');

watch(account, (value) => {
	author.value = value?.author ?? '';
}, { immediate: true });

const authorChanged = computed(() => author.value !== (account.value?.author ?? ''));

async function saveAuthor(): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	authorBusy.value  = true;
	authorError.value = '';

	try {
		replace(await updateAccount(current.username, { author: author.value === '' ? null : author.value }));
		toast(author.value === '' ? `Unlinked ${current.displayName}'s author` : `Linked ${current.displayName} to ${author.value}`);
	} catch (caught) {
		authorError.value = caught instanceof ApiError ? caught.message : 'The author couldn\'t be saved.';
	} finally {
		authorBusy.value = false;
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

	if (current === undefined || !window.confirm(`Remove ${current.displayName}? They're signed out and can't sign in again. Their author page and the entries crediting it stay.`)) {
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
						<div v-if="!account.manages"><dt>Author</dt><dd :class="{ mono: account.author }">{{ account.author ?? 'None' }}</dd></div>
					</dl>

					<form v-if="account.manages" class="field" @submit.prevent="saveName">
						<label for="account-name">Name</label>
						<div class="inline-save">
							<input id="account-name" v-model="name" class="input account-name" autocomplete="off" maxlength="100" :placeholder="account.displayName" :aria-invalid="nameError !== '' || undefined" aria-describedby="account-name-help">
							<button v-if="nameChanged" type="submit" class="button button--small" :disabled="nameBusy">{{ nameBusy ? 'Saving…' : 'Save' }}</button>
						</div>
						<p v-if="nameError" id="account-name-help" class="field__error" role="alert">{{ nameError }}</p>
						<p v-else id="account-name-help" class="field__help">What the admin calls them. Without one, it's their author page's title, or else their username.</p>
					</form>

					<form v-if="account.manages" class="field" @submit.prevent="saveAuthor">
						<label for="account-author">Author</label>
						<div class="inline-save">
							<AuthorField id="account-author" v-model="author" described-by="account-author-help" :invalid="authorError !== ''" />
							<button v-if="authorChanged" type="submit" class="button button--small" :disabled="authorBusy">{{ authorBusy ? 'Saving…' : 'Save' }}</button>
						</div>
						<p v-if="authorError" id="account-author-help" class="field__error" role="alert">{{ authorError }}</p>
						<p v-else id="account-author-help" class="field__help">The author entry that's their public name; entries crediting it are theirs.</p>
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
					<p class="field__help">Suspending signs them out until you reinstate them. Removing deletes the account; their author page and the entries crediting it stay.</p>
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
