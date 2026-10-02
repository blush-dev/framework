<script setup lang="ts">
/**
 * Your Account (D-235, D-355, D-358; the address stays `/profile`): your account, and how you like the admin.
 * Its settings (`AccountSettings`: your name, password, roles, the
 * admin's theme, and color scheme) are panels here, never inside the
 * editor. Preferences belong to the account, not the site, so they
 * follow you to any device and never change what anyone else sees.
 *
 * **Public Profile** shows the profile your account is linked to, your
 * public name and bio on the site (D-351), with a link to edit it in
 * the editor like any entry. Linked to one with no file yet, it can be
 * created (a draft titled with your name), which then opens in the
 * editor.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type EntryDetail } from '../api';
import AccountSettings from '../components/AccountSettings.vue';
import AdminIcon from '../components/AdminIcon.vue';
import StatusPill from '../components/StatusPill.vue';
import { config } from '../config';
import { initials } from '../people';
import { can, loadSession, session } from '../session';
import { profileType, loadTypes } from '../types';

const router  = useRouter();
const account = computed(() => session.account);

// The profile: loading, found, missing (no file yet), or kept by
// someone else (the account may not edit it).
const profile      = ref<EntryDetail | null>(null);
const profileState = ref<'loading' | 'found' | 'missing' | 'locked' | 'failed' | 'none'>('loading');
const creating     = ref(false);
const profileError = ref('');

loadTypes().catch(() => undefined);

async function find(slug: string | null, type: string | null): Promise<void> {
	profile.value = null;

	if (slug === null || type === null) {
		profileState.value = slug === null ? 'none' : 'loading';

		return;
	}

	profileState.value = 'loading';

	try {
		profile.value      = await request<EntryDetail>('GET', `/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}`);
		profileState.value = 'found';
	} catch (caught) {
		profileState.value = caught instanceof ApiError && caught.status === 404 ? 'missing' : (caught instanceof ApiError && caught.status === 403 ? 'locked' : 'failed');
	}
}

watch([() => account.value?.author ?? null, profileType], ([slug, type]) => {
	void find(slug, type);
}, { immediate: true });

const liveUrl = computed(() => profile.value?.status === 'published' && profile.value.url ? new URL(profile.value.url, config.site.url).href : null);

async function createProfile(): Promise<void> {
	const slug = account.value?.author;

	if (!slug || profileType.value === null) {
		return;
	}

	creating.value     = true;
	profileError.value = '';

	try {
		const created = await request<EntryDetail>('POST', '/entries', { type: profileType.value, title: account.value?.name ?? slug, slug });

		await loadSession(true);
		await router.push(entryRoute(created));
	} catch (caught) {
		profileError.value = caught instanceof ApiError ? caught.message : 'Your profile couldn\'t be created.';
	} finally {
		creating.value = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Your Account</h1>
			<p class="page-header__hint">Your account, and how you like the admin</p>
		</div>
	</header>

	<div v-if="account" class="profile">
		<section v-if="profileType !== null" class="panel" aria-labelledby="public-heading">
			<header class="panel__header">
				<h2 id="public-heading">Public Profile</h2>
				<p class="panel__hint">Your name and bio on the site</p>
			</header>
			<div class="panel__body profile__public">
				<template v-if="profileState === 'found' && profile">
					<div class="profile__who">
						<span class="profile__avatar" aria-hidden="true">{{ initials(profile.title || profile.slug) }}</span>
						<span class="profile__name">
							<strong>{{ profile.title || profile.slug }}</strong>
							<span v-if="profile.url" class="mono profile__meta">{{ profile.url }}</span>
						</span>
						<StatusPill :status="profile.status" />
					</div>
					<p class="field__help">Its title is your name everywhere, and its body is your bio. Edit it like any entry.</p>
					<div class="profile__buttons">
						<RouterLink class="button button--primary button--small" :to="entryRoute(profile)"><AdminIcon name="pen-line" />Edit your profile</RouterLink>
						<a v-if="liveUrl" class="button button--small" :href="liveUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />View<span class="visually-hidden"> (new tab)</span></a>
					</div>
				</template>
				<template v-else-if="profileState === 'none'">
					<p class="field__help">Your account isn't linked to a profile, so it has no public name or entries of its own. An administrator can link one.</p>
				</template>
				<template v-else-if="profileState === 'loading'">
					<p class="field__help">Looking for your profile…</p>
				</template>
				<template v-else-if="profileState === 'missing'">
					<p>You don't have a profile yet, so bylines show you as <span class="mono">{{ account.author }}</span>. With one, its title is your name everywhere, and its body is your bio.</p>
					<p v-if="can('content.create')">
						<button type="button" class="button" :disabled="creating" @click="createProfile"><AdminIcon name="plus" />{{ creating ? 'Creating…' : 'Create your profile' }}</button>
					</p>
					<p v-else class="field__help">Ask an editor to create it.</p>
					<p v-if="profileError" class="notice notice--error" role="alert">{{ profileError }}</p>
				</template>
				<template v-else-if="profileState === 'locked'">
					<p class="field__help">Your profile, <span class="mono">{{ account.author }}</span>, is kept by an editor.</p>
				</template>
				<template v-else>
					<p class="notice notice--error" role="alert">Your profile couldn't be loaded.</p>
				</template>
			</div>
		</section>

		<AccountSettings :author-page="profileState === 'found'" />
	</div>
</template>

<style scoped>
.profile {
	display: grid;
	gap: 20px;
	max-width: 44rem;
}

.profile__public {
	display: grid;
	gap: var(--s-3);
}

.profile__public > p {
	margin: 0;
}

.profile__who {
	display: flex;
	align-items: center;
	gap: var(--s-3);
}

.profile__avatar {
	display: grid;
	flex: none;
	place-items: center;
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-weight: 600;
}

.profile__name {
	display: grid;
	flex: 1;
	min-width: 0;
}

.profile__meta {
	color: var(--fg-3);
}

.profile__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}
</style>
