<script setup lang="ts">
/**
 * Your profile (D-235): who you're signed in as, your author page, and
 * how you like the admin. Preferences belong to the account, not the
 * site, so they follow you to any device and never change what anyone
 * else sees. (The site's own look is its theme, which is something
 * else.)
 *
 * The author page is the account's public side (D-259): the entry of
 * the author type the account is linked to, with its name and bio. It's
 * the account's own, so it's edited in the editor like any entry, and
 * made from here when it doesn't exist yet.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type ColorScheme, type EntryDetail } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import { colorScheme, saveColorScheme } from '../color-scheme';
import { formatDate } from '../format';
import type { IconName } from '../icons';
import { can, session } from '../session';
import { authorType, loadTypes } from '../types';

const schemes: { value: ColorScheme; label: string; hint: string; icon: IconName }[] = [
	{ value: 'system', label: 'System', hint: 'Match your device\'s setting', icon: 'monitor' },
	{ value: 'light', label: 'Light', hint: 'Always light', icon: 'sun' },
	{ value: 'dark', label: 'Dark', hint: 'Always dark', icon: 'moon' }
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
			<h1 tabindex="-1">Your profile</h1>
			<p class="page-header__hint">Your account, and how you like the admin</p>
		</div>
	</header>

	<div v-if="account" class="profile">
		<section class="panel" aria-labelledby="account-heading">
			<header class="panel__header">
				<h2 id="account-heading">Account</h2>
			</header>
			<dl class="panel__body profile__facts">
				<div>
					<dt>Username</dt>
					<dd class="mono">{{ account.username }}</dd>
				</div>
				<div>
					<dt>Roles</dt>
					<dd>{{ account.roles.join(', ') || '—' }}</dd>
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
		</section>

		<section v-if="authorType !== null" class="panel" aria-labelledby="author-heading">
			<header class="panel__header">
				<h2 id="author-heading">Author page</h2>
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
				<h2 id="display-heading">Color scheme</h2>
				<p class="panel__hint">Just for you, on any device</p>
			</header>
			<div class="panel__body">
				<fieldset class="schemes" :disabled="saving">
					<legend class="visually-hidden">Color scheme</legend>
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
