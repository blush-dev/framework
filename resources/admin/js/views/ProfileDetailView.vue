<script setup lang="ts">
/**
 * One profile (D-353; the profiles sketch's profile screen): a public
 * identity, what its bylines render, where it appears, and the account
 * linked to it.
 *
 * - **Identity**: the display name, slug, the line under the name (its
 *   `subtitle`), and avatar. The bio is the body, written in the editor;
 *   **Edit profile** opens it.
 * - **Where This Profile Appears**: the profile's own page, then one row
 *   per people field of each type that credits people, with its archive
 *   (or none) and what introduces it: the profile's bio (inherited), or
 *   a page written for that archive. **Write one** creates that page, a
 *   draft, and opens it; **Use the profile's** moves it to the trash.
 * - **Linked Account**: read here, changed on the account's screen. A
 *   profile with no account is a guest profile.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import StatusPill from '../components/StatusPill.vue';
import { ApiError, entryRoute } from '../api';
import { config } from '../config';
import { plural } from '../format';
import { initials, loadProfile, removeArchivePage, updateAccount, when, writeArchivePage, type ProfileAppearance, type ProfileDetail } from '../people';
import { screenTitle } from '../screen';
import { can, canType } from '../session';
import { toast } from '../toast';
import { profileType } from '../types';

const route   = useRoute();
const router  = useRouter();
const detail  = ref<ProfileDetail | null>(null);
const error   = ref('');
const busy    = ref('');
const failure = ref('');

const slug = computed(() => String(route.params.slug ?? ''));

async function load(): Promise<void> {
	error.value = '';

	try {
		detail.value = await loadProfile(slug.value);
	} catch (caught) {
		detail.value = null;
		error.value  = caught instanceof ApiError ? caught.message : 'The profile couldn\'t be loaded.';
	}
}

watch(slug, load, { immediate: true });

watch(detail, (value) => {
	screenTitle.value = value ? value.profile.title || value.profile.slug : null;
}, { immediate: true });

const profile = computed(() => detail.value?.profile ?? null);
const editRoute = computed(() => profile.value?.id ? entryRoute({ id: profile.value.id, handle: profile.value.handle }) : null);
const liveUrl = computed(() => profile.value?.status === 'published' && profile.value.url ? new URL(profile.value.url, config.site.url).href : null);
const archives = computed(() => (detail.value?.appears ?? []).filter((row) => row.archive !== null).length);

// Writes the page for one archive, then opens it.
async function write(row: ProfileAppearance): Promise<void> {
	busy.value    = `${row.type}.${row.field}`;
	failure.value = '';

	try {
		const page = await writeArchivePage(slug.value, row.type, row.field);

		await router.push(entryRoute(page));
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The page couldn\'t be written.';
	} finally {
		busy.value = '';
	}
}

// Moves an archive's page to the trash, so it shows the bio again.
async function useProfiles(row: ProfileAppearance): Promise<void> {
	if (!window.confirm(`Use the profile's bio for ${row.label.toLowerCase()} under ${row.typeLabel}? The page written for it moves to the trash.`)) {
		return;
	}

	busy.value    = `${row.type}.${row.field}`;
	failure.value = '';

	try {
		await removeArchivePage(slug.value, row.type, row.field);
		toast('The archive uses the profile\'s bio');
		await load();
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The page couldn\'t be moved to the trash.';
	} finally {
		busy.value = '';
	}
}

// Unlinking leaves the profile and its bylines; it becomes a guest's.
async function unlink(): Promise<void> {
	const account = detail.value?.account;

	if (!account || !window.confirm(`Unlink ${account.displayName}? This profile and its bylines stay, as a guest profile; the account isn't changed otherwise.`)) {
		return;
	}

	busy.value    = 'unlink';
	failure.value = '';

	try {
		await updateAccount(account.username, { author: null });
		toast(`Unlinked ${account.displayName}`);
		await load();
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be unlinked.';
	} finally {
		busy.value = '';
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text profile-head">
			<span v-if="profile" class="profile-head__avatar" :class="{ 'profile-head__avatar--guest': !detail?.linked }" aria-hidden="true">{{ initials(profile.title || profile.slug) }}</span>
			<span>
				<h1 tabindex="-1">{{ profile ? profile.title || profile.slug : 'Profile' }}</h1>
				<p v-if="profile" class="page-header__hint">
					<template v-if="profile.url"><span class="mono">{{ profile.url }}</span> · </template>
					<template v-if="profile.virtual">Credited without a profile file</template>
					<StatusPill v-else-if="profile.status" :status="profile.status" />
					· {{ plural(profile.uses, 'byline', 'bylines') }}
					<template v-if="detail?.account"> · linked to {{ detail.account.displayName }}</template>
					<template v-else-if="!detail?.linked"> · guest profile</template>
				</p>
			</span>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="profileType" class="button" :to="{ name: 'type', params: { type: profileType } }"><AdminIcon name="arrow-left" />All profiles</RouterLink>
			<a v-if="liveUrl" class="button" :href="liveUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />View<span class="visually-hidden"> (new tab)</span></a>
			<RouterLink v-if="editRoute" class="button button--primary" :to="editRoute"><AdminIcon name="pen-line" />Edit profile</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>

	<template v-if="detail && profile">
		<p v-if="profile.virtual" class="notice notice--warn">
			<span>Entries credit <span class="mono">{{ profile.slug }}</span>, but there's no profile file, so bylines show the name as it's written and there's no bio.
				<RouterLink v-if="profileType && canType(profileType, 'create')" :to="{ name: 'entry-new', query: { type: profileType } }">Create a profile</RouterLink> with this slug to give it one.</span>
		</p>

		<div class="detail">
			<section class="panel" aria-labelledby="identity-heading">
				<header class="panel__header">
					<h2 id="identity-heading">Identity</h2>
					<p class="panel__hint">What a byline renders, wherever it appears</p>
				</header>
				<div class="panel__body">
					<dl class="facts">
						<div><dt>Display name</dt><dd>{{ profile.title || '—' }}</dd></div>
						<div><dt>Slug</dt><dd class="mono">{{ profile.slug }}</dd></div>
						<div><dt>Under the name</dt><dd>{{ profile.subtitle ?? '—' }}</dd></div>
						<div><dt>Avatar</dt><dd :class="{ mono: profile.avatar }">{{ profile.avatar ?? 'Initials' }}</dd></div>
					</dl>
					<p class="field__help">The bio is the profile's body, written in the editor with the rest of these. <RouterLink v-if="editRoute" :to="editRoute">Edit profile</RouterLink></p>
				</div>
			</section>

			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header">
					<h2 id="account-heading">Linked Account</h2>
					<p class="panel__hint">Changed on the account's screen</p>
				</header>
				<div class="panel__body">
					<template v-if="detail.account">
						<div class="who">
							<span class="who__avatar" aria-hidden="true">{{ initials(detail.account.displayName) }}</span>
							<span class="who__text">
								<strong>{{ detail.account.displayName }}</strong>
								<span class="mono who__meta">{{ detail.account.username }}</span>
								<span class="who__meta">Last signed in: {{ when(detail.account.lastLogin) }}</span>
							</span>
						</div>
						<div class="buttons">
							<RouterLink class="button button--small" :to="{ name: 'account', params: { username: detail.account.username } }">Open account</RouterLink>
							<button v-if="detail.account.manages" type="button" class="button button--small" :disabled="busy === 'unlink'" @click="unlink">Unlink</button>
						</div>
						<p class="field__help">Unlinking leaves this profile and its {{ plural(profile.uses, 'byline', 'bylines') }} in place, as a guest profile.</p>
					</template>
					<p v-else-if="detail.linked">An account is linked to this profile.</p>
					<template v-else>
						<p>No account is linked, so this is a <strong>guest profile</strong>: credited on the site, but no one signs in as it.</p>
						<p v-if="can('accounts.edit')" class="field__help">Link it from an account's screen, under <RouterLink :to="{ name: 'accounts' }">Accounts</RouterLink>.</p>
					</template>
				</div>
			</section>
		</div>

		<section class="panel" aria-labelledby="appears-heading">
			<header class="panel__header">
				<h2 id="appears-heading">Where This Profile Appears</h2>
				<p class="panel__hint">{{ plural(archives, 'archive', 'archives') }} under the types that credit people</p>
			</header>
			<div class="table-wrap">
				<table class="table" aria-labelledby="appears-heading">
					<thead>
						<tr>
							<th scope="col">Field</th>
							<th scope="col">Archive</th>
							<th scope="col">Introduced by</th>
							<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">The profile</span>
									<span class="row-note">Its own page, and every archive's default</span>
								</span>
							</th>
							<td><span v-if="profile.url" class="mono">{{ profile.url }}</span><template v-else>—</template></td>
							<td>{{ profile.virtual ? 'Its name only' : 'Its bio' }}</td>
							<td class="table__actions">
								<RouterLink v-if="editRoute" class="button button--small" :to="editRoute">Edit</RouterLink>
							</td>
						</tr>
						<tr v-for="row in detail.appears" :key="`${row.type}.${row.field}`">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">{{ row.label }}</span>
									<span class="row-note">{{ row.typeLabel }} · {{ plural(row.entries, 'entry', 'entries') }}</span>
								</span>
							</th>
							<td><span v-if="row.archive" class="mono">{{ row.archive }}</span><template v-else>No archive</template></td>
							<td>
								<template v-if="!row.archive">—</template>
								<template v-else-if="row.page">Its own page{{ ' ' }}<StatusPill :status="row.page.status" /></template>
								<template v-else>The profile's bio</template>
							</td>
							<td class="table__actions">
								<template v-if="row.archive && row.page">
									<RouterLink class="button button--small" :to="entryRoute(row.page)">Edit</RouterLink>
									<button v-if="canType(row.type, 'delete')" type="button" class="button button--ghost button--small" :disabled="busy === `${row.type}.${row.field}`" @click="useProfiles(row)">Use the profile's</button>
								</template>
								<button v-else-if="row.archive && canType(row.type, 'create')" type="button" class="button button--small" :disabled="busy === `${row.type}.${row.field}`" @click="write(row)">Write one</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p class="panel__note">An archive shows the profile's bio unless a page is written for it: an ordinary entry, with its own status, kept for that archive alone.</p>
		</section>
	</template>
</template>

<style scoped>
.profile-head {
	display: flex;
	align-items: center;
	gap: var(--s-3);
}

.profile-head__avatar,
.who__avatar {
	display: grid;
	flex: none;
	place-items: center;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-weight: 600;
}

.profile-head__avatar {
	width: 44px;
	height: 44px;
}

.profile-head__avatar--guest {
	border: 1px dashed var(--border-strong);
	background: transparent;
	color: var(--fg-3);
}

.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

@media (max-width: 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}

.panel__body {
	display: grid;
	gap: var(--s-3);
}

.panel__body > * {
	margin: 0;
}

.facts {
	display: grid;
	gap: var(--s-2);
	margin: 0;
}

.facts div {
	display: grid;
	grid-template-columns: 9rem minmax(0, 1fr);
	gap: var(--s-3);
}

.facts dt {
	color: var(--fg-3);
}

.facts dd {
	margin: 0;
	overflow-wrap: anywhere;
}

.who {
	display: flex;
	align-items: center;
	gap: var(--s-3);
}

.who__avatar {
	width: 36px;
	height: 36px;
}

.who__text {
	display: grid;
	min-width: 0;
}

.who__meta {
	color: var(--fg-3);
}

.row-note {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.table__actions {
	white-space: nowrap;
}

.table__actions .button + .button {
	margin-left: var(--s-2);
}

.panel__note {
	margin: 0;
	padding: var(--s-3) var(--pad-x);
	border-top: 1px solid var(--border);
	background: var(--surface-2);
	color: var(--fg-2);
}
</style>
