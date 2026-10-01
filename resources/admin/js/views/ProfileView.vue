<script setup lang="ts">
/**
 * Your profile (D-235): who you're signed in as, your name (D-322: what
 * the admin calls you; your author page's title is your public name),
 * your author page, and how you like the admin. Preferences belong to the account, not the
 * site, so they follow you to any device and never change what anyone
 * else sees. (The site's own look is its theme, which is something
 * else.)
 *
 * The author page is the account's public side (D-259): the entry of
 * the author type the account is linked to, with its name and bio. It's
 * the account's own, so it's edited in the editor like any entry, and
 * made from here when it doesn't exist yet.
 *
 * Changing the password (D-273) asks for the current one, and signs out
 * the account's other sessions; this one stays signed in.
 */

import { computed, nextTick, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type AdminTheme, type ColorScheme, type EntryDetail } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import { adminTheme, saveAdminTheme } from '../admin-theme';
import { colorScheme, saveColorScheme } from '../color-scheme';
import { formatDate } from '../format';
import type { IconName } from '../icons';
import { can, saveName, session } from '../session';
import { toast } from '../toast';
import { authorType, loadTypes } from '../types';

const schemes: { value: ColorScheme; label: string; hint: string; icon: IconName }[] = [
	{ value: 'system', label: 'System', hint: 'Match your device\'s setting', icon: 'monitor' },
	{ value: 'light', label: 'Light', hint: 'Always light', icon: 'sun' },
	{ value: 'dark', label: 'Dark', hint: 'Always dark', icon: 'moon' }
];

// The admin's two looks (D-317): a per-account choice, like the scheme.
const themes: { value: AdminTheme; label: string; hint: string; icon: IconName }[] = [
	{ value: 'neutral', label: 'Neutral', hint: 'Cool gray, a cobalt accent', icon: 'layout-dashboard' },
	{ value: 'editorial', label: 'Editorial', hint: 'Warm paper, a teal accent, serif titles', icon: 'book-open' }
];

const saving  = ref(false);
const message = ref('');
const error   = ref('');

const account   = computed(() => session.account);
const lastLogin = computed(() => account.value?.lastLogin ? formatDate(new Date(account.value.lastLogin * 1000).toISOString()) : 'Never');

const router = useRouter();

// The author page: loading, found, missing (no file yet), or kept by
// someone else (the account may not edit it).
const author       = ref<EntryDetail | null>(null);
const authorState  = ref<'loading' | 'found' | 'missing' | 'locked' | 'failed'>('loading');
const creating     = ref(false);
const authorError  = ref('');

loadTypes().catch(() => undefined);

watch([() => account.value?.author ?? null, authorType], async ([slug, type]) => {
	author.value = null;

	if (slug === null || type === null) {
		return;
	}

	authorState.value = 'loading';

	try {
		author.value      = await request<EntryDetail>('GET', `/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}`);
		authorState.value = 'found';
	} catch (caught) {
		authorState.value = caught instanceof ApiError && caught.status === 404 ? 'missing' : (caught instanceof ApiError && caught.status === 403 ? 'locked' : 'failed');
	}
}, { immediate: true });

async function createAuthor(): Promise<void> {
	const slug = account.value?.author;

	if (!slug || authorType.value === null) {
		return;
	}

	creating.value    = true;
	authorError.value = '';

	try {
		const entry = await request<EntryDetail>('POST', '/entries', { type: authorType.value, title: slug, slug });

		await router.push({ ...entryRoute(entry), query: { created: '1' } });
	} catch (caught) {
		authorError.value = caught instanceof ApiError ? caught.message : 'Your author page couldn\'t be created.';
		creating.value    = false;
	}
}

// Your name: typed, then saved; an empty one takes it away.
const name      = ref(session.account?.name ?? '');
const nameBusy  = ref(false);
const nameError = ref('');

watch(() => account.value?.name, (value) => {
	name.value = value ?? '';
});

const nameChanged = computed(() => name.value.trim() !== (account.value?.name ?? ''));

async function submitName(): Promise<void> {
	nameBusy.value  = true;
	nameError.value = '';

	try {
		await saveName(name.value);
		name.value = account.value?.name ?? '';
		toast(account.value?.name ? `Saved. The admin calls you ${account.value.name}.` : `Removed your name. The admin calls you ${account.value?.displayName ?? ''}.`);
	} catch (caught) {
		nameError.value = caught instanceof ApiError ? caught.message : 'Your name couldn\'t be saved.';
	} finally {
		nameBusy.value = false;
	}
}

// Changing the password: the form is shown on request.
const changing        = ref(false);
const currentPassword = ref('');
const newPassword     = ref('');
const passwordBusy    = ref(false);
const passwordError   = ref('');
const passwordField   = ref<'current' | 'password' | null>(null);
const currentInput    = ref<HTMLInputElement | null>(null);
const newInput        = ref<HTMLInputElement | null>(null);
const changeButton    = ref<HTMLButtonElement | null>(null);

async function startPasswordChange(): Promise<void> {
	changing.value = true;
	await nextTick();
	currentInput.value?.focus();
}

async function stopPasswordChange(): Promise<void> {
	changing.value        = false;
	currentPassword.value = '';
	newPassword.value     = '';
	passwordError.value   = '';
	passwordField.value   = null;
	await nextTick();
	changeButton.value?.focus();
}

async function changePassword(): Promise<void> {
	passwordBusy.value  = true;
	passwordError.value = '';
	passwordField.value = null;

	try {
		await request<void>('POST', '/password', { current: currentPassword.value, password: newPassword.value });
		await stopPasswordChange();
		toast('Password changed. You\'re signed out everywhere else.');
	} catch (caught) {
		passwordError.value = caught instanceof ApiError ? caught.message : 'Your password couldn\'t be changed.';
		passwordField.value = caught instanceof ApiError && (caught.field === 'current' || caught.field === 'password') ? caught.field : null;
		(passwordField.value === 'password' ? newInput : currentInput).value?.focus();
	} finally {
		passwordBusy.value = false;
	}
}

async function chooseTheme(theme: AdminTheme): Promise<void> {
	saving.value  = true;
	message.value = '';
	error.value   = '';

	try {
		await saveAdminTheme(theme);
		message.value = `Saved. The admin is ${theme === 'neutral' ? 'Neutral' : 'Editorial'} on every device you sign in on.`;
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Your theme couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

async function choose(scheme: ColorScheme): Promise<void> {
	saving.value  = true;
	message.value = '';
	error.value   = '';

	try {
		await saveColorScheme(scheme);
		message.value = `Saved. The admin is ${scheme === 'system' ? 'following your device' : scheme} on every device you sign in on.`;
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Your color scheme couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Your Profile</h1>
			<p class="page-header__hint">Your account, and how you like the admin</p>
		</div>
	</header>

	<div v-if="account" class="profile">
		<section class="panel" aria-labelledby="account-heading">
			<header class="panel__header">
				<h2 id="account-heading">Account</h2>
			</header>
			<form class="panel__body field profile__name" :aria-busy="nameBusy" @submit.prevent="submitName">
				<label for="profile-name">Name</label>
				<div class="profile__name-row">
					<input id="profile-name" v-model="name" class="input" autocomplete="name" maxlength="100" :placeholder="account.displayName" :aria-invalid="nameError !== '' || undefined" aria-describedby="profile-name-help">
					<button v-if="nameChanged" type="submit" class="button button--small" :disabled="nameBusy">{{ nameBusy ? 'Saving…' : 'Save' }}</button>
				</div>
				<p v-if="nameError" id="profile-name-help" class="field__error" role="alert">{{ nameError }}</p>
				<p v-else id="profile-name-help" class="field__help">What the admin calls you. Only people who sign in see it; your name on the site is your author page's.</p>
			</form>
			<dl class="panel__body profile__facts">
				<div>
					<dt>Username</dt>
					<dd class="mono">{{ account.username }}</dd>
				</div>
				<div>
					<dt>Roles</dt>
					<dd>{{ account.roles.map((role) => role.label).join(', ') || '—' }}</dd>
				</div>
				<div>
					<dt>Author</dt>
					<dd :class="{ mono: account.author }">{{ account.author ?? 'Not linked' }}</dd>
				</div>
				<div>
					<dt>Signed in</dt>
					<dd>{{ lastLogin }}</dd>
				</div>
			</dl>
			<div class="panel__body profile__password">
				<p v-if="!changing">
					<button ref="changeButton" type="button" class="button button--small" @click="startPasswordChange"><AdminIcon name="key-round" />Change password</button>
				</p>
				<form v-else class="profile__password-form" :aria-busy="passwordBusy" @submit.prevent="changePassword" @keydown.esc="stopPasswordChange">
					<input type="text" class="visually-hidden" name="username" :value="account.username" autocomplete="username" tabindex="-1" aria-hidden="true" readonly>
					<p class="field">
						<label for="current-password">Current password</label>
						<input id="current-password" ref="currentInput" v-model="currentPassword" type="password" autocomplete="current-password" required :aria-invalid="passwordField === 'current' || undefined" :aria-describedby="passwordField === 'current' ? 'password-error' : undefined">
					</p>
					<p class="field">
						<label for="new-password">New password</label>
						<input id="new-password" ref="newInput" v-model="newPassword" type="password" autocomplete="new-password" required :aria-invalid="passwordField === 'password' || undefined" :aria-describedby="passwordField === 'password' ? 'password-error' : 'new-password-help'">
						<span id="new-password-help" class="field__help">You'll stay signed in here and be signed out on every other device.</span>
					</p>
					<p v-if="passwordError" id="password-error" class="notice notice--error" role="alert">{{ passwordError }}</p>
					<p class="profile__password-actions">
						<button type="submit" class="button button--primary button--small" :disabled="passwordBusy">{{ passwordBusy ? 'Changing…' : 'Change password' }}</button>
						<button type="button" class="button button--ghost button--small" :disabled="passwordBusy" @click="stopPasswordChange">Cancel</button>
					</p>
				</form>
			</div>
		</section>

		<section v-if="authorType !== null" class="panel" aria-labelledby="author-heading">
			<header class="panel__header">
				<h2 id="author-heading">Author Page</h2>
				<p class="panel__hint">Your name and bio on the site</p>
			</header>
			<div class="panel__body profile__author">
				<template v-if="!account.author">
					<p class="field__help">Your account isn't linked to an author, so it has no public name or entries of its own. An administrator can link one.</p>
				</template>
				<template v-else-if="authorState === 'loading'">
					<p class="field__help">Looking for your author page…</p>
				</template>
				<template v-else-if="authorState === 'found' && author">
					<p>Bylines show you as <strong>{{ author.title || account.author }}</strong>{{ author.status === 'published' ? '' : ', once your author page is published' }}. Your bio and picture are on the same page.</p>
					<p><RouterLink class="button" :to="entryRoute(author)"><AdminIcon name="pen-line" />Edit your author page</RouterLink></p>
				</template>
				<template v-else-if="authorState === 'missing'">
					<p>You don't have an author page yet, so bylines show you as <span class="mono">{{ account.author }}</span>.</p>
					<p v-if="can('content.create')">
						<button type="button" class="button" :disabled="creating" @click="createAuthor"><AdminIcon name="plus" />{{ creating ? 'Creating…' : 'Create your author page' }}</button>
					</p>
					<p v-else class="field__help">Ask an editor to create it.</p>
					<p v-if="authorError" class="notice notice--error" role="alert">{{ authorError }}</p>
				</template>
				<template v-else-if="authorState === 'locked'">
					<p class="field__help">Your author page, <span class="mono">{{ account.author }}</span>, is kept by an editor.</p>
				</template>
				<template v-else>
					<p class="notice notice--error" role="alert">Your author page couldn't be loaded.</p>
				</template>
			</div>
		</section>

		<section class="panel" aria-labelledby="display-heading">
			<header class="panel__header">
				<h2 id="display-heading">Theme and Color Scheme</h2>
				<p class="panel__hint">Just for you, on any device</p>
			</header>
			<div class="panel__body">
				<fieldset class="schemes" :disabled="saving">
					<legend class="schemes__legend">Theme</legend>
					<label v-for="theme in themes" :key="theme.value" class="scheme">
						<input class="visually-hidden" type="radio" name="admin-theme" :value="theme.value" :checked="adminTheme === theme.value" @change="chooseTheme(theme.value)">
						<AdminIcon :name="theme.icon" />
						<span class="scheme__text">
							<span class="scheme__label">{{ theme.label }}</span>
							<span class="scheme__hint">{{ theme.hint }}</span>
						</span>
					</label>
				</fieldset>
				<fieldset class="schemes" :disabled="saving">
					<legend class="schemes__legend">Color scheme</legend>
					<label v-for="scheme in schemes" :key="scheme.value" class="scheme">
						<input class="visually-hidden" type="radio" name="color-scheme" :value="scheme.value" :checked="colorScheme === scheme.value" @change="choose(scheme.value)">
						<AdminIcon :name="scheme.icon" />
						<span class="scheme__text">
							<span class="scheme__label">{{ scheme.label }}</span>
							<span class="scheme__hint">{{ scheme.hint }}</span>
						</span>
					</label>
				</fieldset>
				<p class="profile__status" aria-live="polite">{{ message }}</p>
				<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			</div>
		</section>
	</div>
</template>

<style scoped>
.profile {
	display: grid;
	gap: 20px;
	max-width: 44rem;
}

.profile__author > p {
	margin: 0;
}

.profile__author {
	display: grid;
	gap: 10px;
}

.profile__password {
	border-top: 1px solid var(--border);
}

.profile__name {
	border-bottom: 1px solid var(--border);
}

.profile__name > * {
	margin: 0;
}

.profile__name-row {
	display: flex;
	align-items: center;
	gap: 8px;
	max-width: 24rem;
}

.profile__name-row .input {
	flex: 1;
	min-width: 0;
}

.profile__password p {
	margin: 0;
}

.profile__password-form {
	display: grid;
	gap: 14px;
	max-width: 24rem;
}

.profile__password-actions {
	display: flex;
	gap: 8px;
}

.profile__facts {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
	gap: 14px 20px;
	margin: 0;
}

.profile__facts > div {
	margin: 0;
}

.profile__facts dt {
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 500;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.profile__facts dd {
	margin: 2px 0 0;
}

.schemes {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
	gap: 10px;
	margin: 0;
	padding: 0;
	border: 0;
}

.schemes + .schemes {
	margin-top: var(--s-4);
}

/* Each choice is named, so the two rows of cards read apart. */
.schemes__legend {
	margin-bottom: var(--s-2);
	padding: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
}

.scheme {
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg-2);
	cursor: pointer;
}

.scheme:hover {
	border-color: var(--fg-3);
}

.scheme:has(:checked) {
	border-color: var(--accent);
	background: var(--accent-soft);
	color: var(--accent);
}

.scheme:has(:focus-visible) {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.schemes:disabled .scheme {
	cursor: progress;
}

.scheme__text {
	display: grid;
}

.scheme__label {
	color: var(--fg);
	font-weight: 500;
}

.scheme__hint {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.profile__status:empty {
	display: none;
}

.profile__status {
	color: var(--fg-2);
	font-size: var(--text-sm);
}
</style>
