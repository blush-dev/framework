<script setup lang="ts">
/**
 * Your profile (D-235, D-329, D-353): one person, so one screen. With a
 * profile (your public name and bio), it's the editor for it: the bio is
 * the writing surface, its title is your name in the admin and on the
 * site, and your account's private settings (`AccountSettings`:
 * password, roles, theme, and color scheme) are the drawer's **Account**
 * tab. The address stays `/profile`.
 *
 * Without one, it's those settings as panels, with a way to create the
 * profile (a draft, titled with your name or username) when the account
 * is linked to one, which then opens here in the editor. Preferences
 * belong to the account, not the site, so they follow you to any device
 * and never change what anyone else sees.
 */

import { computed, ref, watch } from 'vue';
import { ApiError, request, type EntryDetail } from '../api';
import AccountSettings from '../components/AccountSettings.vue';
import AdminIcon from '../components/AdminIcon.vue';
import EditorView from './EditorView.vue';
import { screenBleed } from '../screen';
import { can, loadSession, session } from '../session';
import { profileType, loadTypes } from '../types';

const account = computed(() => session.account);

// The profile: loading, found, missing (no file yet), or kept by
// someone else (the account may not edit it).
const author      = ref<EntryDetail | null>(null);
const authorState = ref<'loading' | 'found' | 'missing' | 'locked' | 'failed' | 'none'>('loading');
const creating    = ref(false);
const authorError = ref('');

loadTypes().catch(() => undefined);

async function find(slug: string | null, type: string | null): Promise<void> {
	author.value = null;

	if (slug === null || type === null) {
		authorState.value = slug === null ? 'none' : 'loading';

		return;
	}

	authorState.value = 'loading';

	try {
		author.value      = await request<EntryDetail>('GET', `/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}`);
		authorState.value = 'found';
	} catch (caught) {
		authorState.value = caught instanceof ApiError && caught.status === 404 ? 'missing' : (caught instanceof ApiError && caught.status === 403 ? 'locked' : 'failed');
	}
}

watch([() => account.value?.author ?? null, profileType], ([slug, type]) => {
	void find(slug, type);
}, { immediate: true });

// The editor fills the work area; the panels don't.
const editing = computed(() => authorState.value === 'found' && author.value !== null);

watch(editing, (value) => {
	screenBleed.value = value;
}, { immediate: true });

async function createAuthor(): Promise<void> {
	const slug = account.value?.author;

	if (!slug || profileType.value === null) {
		return;
	}

	creating.value    = true;
	authorError.value = '';

	try {
		await request<EntryDetail>('POST', '/entries', { type: profileType.value, title: account.value?.name ?? slug, slug });
		await loadSession(true);
		await find(slug, profileType.value);
	} catch (caught) {
		authorError.value = caught instanceof ApiError ? caught.message : 'Your profile couldn\'t be created.';
	} finally {
		creating.value = false;
	}
}
</script>

<template>
	<EditorView v-if="editing && author" :profile="author.handle ?? `${author.type.name}/${author.slug}`">
		<template #account>
			<AccountSettings author-page />
		</template>
	</EditorView>

	<template v-else>
		<header class="page-header">
			<div class="page-header__text">
				<h1 tabindex="-1">Your Profile</h1>
				<p class="page-header__hint">Your account, and how you like the admin</p>
			</div>
		</header>

		<div v-if="account" class="profile">
			<section v-if="profileType !== null" class="panel" aria-labelledby="author-heading">
				<header class="panel__header">
					<h2 id="author-heading">Public Profile</h2>
					<p class="panel__hint">Your name and bio on the site</p>
				</header>
				<div class="panel__body profile__author">
					<template v-if="authorState === 'none'">
						<p class="field__help">Your account isn't linked to a profile, so it has no public name or entries of its own. An administrator can link one.</p>
					</template>
					<template v-else-if="authorState === 'loading'">
						<p class="field__help">Looking for your profile…</p>
					</template>
					<template v-else-if="authorState === 'missing'">
						<p>You don't have a profile yet, so bylines show you as <span class="mono">{{ account.author }}</span>. With one, this screen is where you write your bio, and its title is your name everywhere.</p>
						<p v-if="can('content.create')">
							<button type="button" class="button" :disabled="creating" @click="createAuthor"><AdminIcon name="plus" />{{ creating ? 'Creating…' : 'Create your profile' }}</button>
						</p>
						<p v-else class="field__help">Ask an editor to create it.</p>
						<p v-if="authorError" class="notice notice--error" role="alert">{{ authorError }}</p>
					</template>
					<template v-else-if="authorState === 'locked'">
						<p class="field__help">Your profile, <span class="mono">{{ account.author }}</span>, is kept by an editor.</p>
					</template>
					<template v-else>
						<p class="notice notice--error" role="alert">Your profile couldn't be loaded.</p>
					</template>
				</div>
			</section>

			<AccountSettings framed />
		</div>
	</template>
</template>

<style scoped>
.profile {
	display: grid;
	gap: 20px;
	max-width: 44rem;
}

.profile__author {
	display: grid;
	gap: 10px;
}

.profile__author > p {
	margin: 0;
}
</style>
