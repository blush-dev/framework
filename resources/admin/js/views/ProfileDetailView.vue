<script setup lang="ts">
/**
 * One profile (D-353; the profiles sketch's profile screen, D-369): a
 * public identity, what its bylines render, where it appears, and the
 * account linked to it.
 *
 * - The header: **All profiles** above the title, the name, its address,
 *   status, and bylines; **View**, **Publish** (a draft), **Edit
 *   profile**, and a ⋮ with linking and **Move to trash**.
 * - **Identity**: the display name, slug, byline title (its `subtitle`),
 *   and avatar. The bio is the body, written in the editor.
 * - **Linked Account**: at most one, and optional. Without one, it's a
 *   guest profile, and an account with no profile can be linked here.
 * - **Where This Profile Appears**: the profile's own page, then one row
 *   per profile field of each type that credits people, with its archive
 *   and where its body comes from, as a pill: **Written** (a page written
 *   for that archive) or **Inherited** (the profile's own). **Write one**
 *   creates that page, a draft, and opens it; **Move to trash** puts the
 *   archive back on the profile's body, and the page can be restored from
 *   its type's Trash tab (D-370). A field whose archive is off keeps a
 *   page written for it, marked **Unreachable**. A type with no profile
 *   field is listed last, so it's clear why it isn't anywhere above.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import MenuButton from '../components/MenuButton.vue';
import PickModal, { type PickItem } from '../components/PickModal.vue';
import StatusPill from '../components/StatusPill.vue';
import { entryRoute, errorMessage, patchEntry, trashEntry } from '../api';
import { config } from '../config';
import { plural } from '../format';
import { initials, loadAccounts, loadProfile, removeArchivePage, statusPill, updateAccount, when, writeArchivePage, type AccountInfo, type ProfileAppearance, type ProfileDetail } from '../people';
import { screenTitle, screenTrail } from '../screen';
import { confirmAction } from '../confirm';
import { can, canType, session } from '../session';
import { toast } from '../toast';
import { labelsOf, profileType, types } from '../types';

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
		error.value  = errorMessage(caught, 'The profile couldn\'t be loaded.');
	}
}

watch(slug, load, { immediate: true });

watch(detail, (value) => {
	screenTitle.value = value ? value.profile.title || value.profile.slug : null;
}, { immediate: true });

// The trail names the collection: Users / Profiles / the profile.
watch([profileType, types], ([name]) => {
	screenTrail.value = name === null ? [] : [{ label: labelsOf(name).plural, to: { name: 'type', params: { type: name } } }];
}, { immediate: true });

const profile   = computed(() => detail.value?.profile ?? null);
const name      = computed(() => profile.value ? profile.value.title || profile.value.slug : 'Profile');
const editRoute = computed(() => profile.value?.id ? entryRoute({ id: profile.value.id, type: profile.value.type }) : null);
const liveUrl   = computed(() => profile.value?.status === 'published' && profile.value.url ? new URL(profile.value.url, config.site.url).href : null);
const yours     = computed(() => session.account?.author === slug.value);

// Types that credit no one, so a reader sees why they aren't above.
const uncredited = computed(() => types.value.filter((type) => (type.kind === 'collection' || type.kind === 'tree') && !type.authors));

// Publishes a draft profile, so its page and bylines go live.
async function publish(): Promise<void> {
	const id = profile.value?.id;

	if (!id) {
		return;
	}

	busy.value    = 'publish';
	failure.value = '';

	try {
		await patchEntry(id, { status: 'published' });
		toast(`Published ${name.value}`);
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, 'The profile couldn\'t be published.');
	} finally {
		busy.value = '';
	}
}

async function trash(): Promise<void> {
	const current = profile.value;

	if (!current?.id) {
		return;
	}

	const credited = current.uses > 0 ? `**${plural(current.uses, 'byline', 'bylines')}** still name this profile. Those entries stay published, with nothing to link to.` : 'Nothing credits it, so no entry changes.';

	if (!await confirmAction({
		title: `Move ${name.value} to the Trash?`,
		body: [`The profile stops answering at **${current.url ?? 'its address'}**, and every archive that falls back to it shows no bio.`, credited, 'Trash is reversible: restoring brings it back as a draft.'],
		confirm: 'Move to Trash',
		danger: true
	})) {
		return;
	}

	busy.value    = 'trash';
	failure.value = '';

	const back  = route.fullPath;
	const label = name.value;

	try {
		const undo = await trashEntry(current.id);

		// Its Undo puts it back and opens it again (D-525).
		toast(`Moved ${label} to the trash`, {
			kind: 'danger',
			undo: () => void undo().then(
				() => router.push(back),
				(caught: unknown) => toast(errorMessage(caught, `${label} couldn't be restored.`), { kind: 'warn' })
			)
		});
		await router.push(profileType.value ? { name: 'type', params: { type: profileType.value } } : { name: 'dashboard' });
	} catch (caught) {
		failure.value = errorMessage(caught, 'The profile couldn\'t be moved to the trash.');
		busy.value    = '';
	}
}

// Writes the page for one archive, then opens it.
async function write(row: ProfileAppearance): Promise<void> {
	if (!await confirmAction({
		title: `Write a ${row.label} Page?`,
		body: [`**${row.archive ?? 'The archive'}** currently shows this profile's own body. Writing a page gives that archive its own content for ${row.typeLabel} only.`, 'It\'s an ordinary entry with its own status, created as a draft. Moving it to the trash puts the archive back on the profile\'s body.'],
		confirm: 'Create the Page'
	})) {
		return;
	}

	busy.value    = `${row.type}.${row.relation}`;
	failure.value = '';

	try {
		const page = await writeArchivePage(slug.value, row.type, row.relation);

		await router.push(entryRoute(page));
	} catch (caught) {
		failure.value = errorMessage(caught, 'The page couldn\'t be written.');
	} finally {
		busy.value = '';
	}
}

// Moves an archive's page to the trash, so it shows the profile's body.
async function deletePage(row: ProfileAppearance): Promise<void> {
	if (!await confirmAction({
		title: `Move the ${row.label} Page to the Trash?`,
		body: [row.archive ? `**${row.archive}** falls back to this profile's own body, the way it did before the page was written.` : 'Its archive is off, so nothing shows it now.', `You can restore it from the ${row.typeLabel} Trash tab.`],
		confirm: 'Move to Trash',
		danger: true
	})) {
		return;
	}

	busy.value    = `${row.type}.${row.relation}`;
	failure.value = '';

	try {
		await removeArchivePage(slug.value, row.type, row.relation);
		toast(`Moved the ${row.label} page to the trash`, { kind: 'danger' });
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, 'The page couldn\'t be moved to the trash.');
	} finally {
		busy.value = '';
	}
}

// Unlinking leaves the profile and its bylines; it becomes a guest's.
async function unlink(): Promise<void> {
	const account = detail.value?.account;

	if (!account || !await confirmAction({
		title: `Unlink ${name.value}?`,
		body: [`The profile stays exactly as it is, with its **${plural(profile.value?.uses ?? 0, 'byline', 'bylines')}** and every archive it serves. It becomes a **guest profile**: credited on the site, unable to sign in.`, `The account **${account.username}** keeps its roles and its sign-in.`],
		confirm: 'Unlink the Profile'
	})) {
		return;
	}

	busy.value    = 'unlink';
	failure.value = '';

	try {
		await updateAccount(account.username, { author: null });
		toast(`Unlinked ${name.value}`, { kind: 'danger' });
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, 'The account couldn\'t be unlinked.');
	} finally {
		busy.value = '';
	}
}

// Linking a guest profile to an account that has none.
const linking  = ref(false);
const free     = ref<AccountInfo[] | null>(null);
const linkItems = computed<PickItem[] | null>(() => free.value?.map((account) => ({
	key: account.username,
	initials: initials(account.displayName),
	name: account.displayName,
	meta: account.email ? `${account.username} · ${account.email}` : account.username,
	aside: account.roles.join(', '),
	guest: true
})) ?? null);
const pick     = ref('');
const canLink  = computed(() => can('accounts.view') && can('accounts.edit') && detail.value !== null && !detail.value.linked && profile.value !== null);
// Anyone's link you manage, and your own (D-373).
const canUnlink = computed(() => detail.value?.account !== null && detail.value?.account !== undefined && can('accounts.edit') && (detail.value.account.manages || detail.value.account.username === session.account?.username));

async function startLink(): Promise<void> {
	linking.value = true;
	pick.value    = '';

	try {
		free.value = (await loadAccounts()).filter((account) => account.author === null && (account.manages || account.username === session.account?.username));
	} catch (caught) {
		failure.value = errorMessage(caught, 'The accounts couldn\'t be loaded.');
		linking.value = false;
	}
}

async function link(): Promise<void> {
	if (pick.value === '') {
		return;
	}

	busy.value    = 'link';
	failure.value = '';

	try {
		await updateAccount(pick.value, { author: slug.value });
		toast(`Linked ${free.value?.find((account) => account.username === pick.value)?.displayName ?? pick.value}`);
		linking.value = false;
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, 'The account couldn\'t be linked.');
	} finally {
		busy.value = '';
	}
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<RouterLink v-if="profileType" class="page-back" :to="{ name: 'type', params: { type: profileType } }"><AdminIcon name="chevron-left" />All profiles</RouterLink>
			<div class="page-header__id">
				<span v-if="profile" class="avatar avatar--large" :class="{ 'avatar--guest': !detail?.linked }" aria-hidden="true">{{ initials(name) }}</span>
				<div class="page-header__text">
					<h1 tabindex="-1">{{ name }}</h1>
					<p v-if="profile" class="page-header__hint">
						<template v-if="profile.url"><span class="mono">{{ profile.url }}</span><span class="page-header__sep" aria-hidden="true">·</span></template>
						<StatusPill :status="profile.status" />
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>{{ plural(profile.uses, 'byline', 'bylines') }}</span>
						<span v-if="yours" class="tag--you">You</span>
					</p>
				</div>
			</div>
			<div v-if="profile" class="page-header__actions">
				<a v-if="liveUrl" class="button" :href="liveUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />View<span class="visually-hidden"> (new tab)</span></a>
				<button v-if="profile.status === 'draft' && profileType && canType(profileType, 'publish')" type="button" class="button" :disabled="busy === 'publish'" @click="publish">Publish</button>
				<RouterLink v-if="editRoute" class="button button--primary" :to="editRoute"><AdminIcon name="pen-line" />Edit Profile</RouterLink>
				<MenuButton v-if="canUnlink || canLink || (profile.id && profileType && canType(profileType, 'delete'))" button-class="button button--icon" label="More actions" align="end">
					<template #button><AdminIcon name="ellipsis-vertical" /></template>
					<button v-if="canUnlink" type="button" class="menu-item" @click="unlink"><AdminIcon name="unlink" />Unlink the account</button>
					<button v-else-if="canLink" type="button" class="menu-item" @click="startLink"><AdminIcon name="link" />Link to an account</button>
					<template v-if="profile.id && profileType && canType(profileType, 'delete')">
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" :disabled="busy === 'trash'" @click="trash"><AdminIcon name="trash-2" />Move to trash</button>
					</template>
				</MenuButton>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>

		<div v-if="detail && profile" class="pair">
			<section class="panel" aria-labelledby="identity-heading">
				<header class="panel__header">
					<h2 id="identity-heading">Identity</h2>
					<p class="panel__hint">What a byline renders, wherever it appears</p>
				</header>
				<div class="panel__body">
					<dl class="fact-rows">
						<div><dt>Display name</dt><dd>{{ profile.title || '—' }}</dd></div>
						<div><dt>Slug</dt><dd class="mono">{{ profile.slug }}</dd></div>
						<div><dt>Byline title</dt><dd :class="{ muted: !profile.subtitle }">{{ profile.subtitle ?? 'Not set' }}</dd></div>
						<div><dt>Avatar</dt><dd :class="{ mono: profile.avatar, muted: !profile.avatar }">{{ profile.avatar ?? 'Initials' }}</dd></div>
					</dl>
				</div>
				<p class="panel__note">The bio is this profile's body, written in the editor along with the rest of these. <strong>Edit profile</strong> opens it.</p>
			</section>

			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header">
					<h2 id="account-heading">Linked Account</h2>
					<p class="panel__hint">At most one, and optional</p>
				</header>
				<template v-if="detail.account">
					<div class="panel__body linked__who">
						<div class="who">
							<span class="avatar avatar--large" aria-hidden="true">{{ initials(detail.account.displayName) }}</span>
							<span class="who__text">
								<span class="who__name">{{ detail.account.displayName }}</span>
								<span class="who__meta">{{ detail.account.username }}</span>
							</span>
						</div>
					</div>
					<div class="panel__body">
						<dl class="fact-rows">
							<div><dt>Email</dt><dd :class="{ muted: !detail.account.email }">{{ detail.account.email ?? 'None yet' }}</dd></div>
							<div><dt>Standing</dt><dd>{{ statusPill(detail.account.status).label }}</dd></div>
							<div><dt>Last signed in</dt><dd>{{ when(detail.account.lastLogin) }}</dd></div>
						</dl>
						<div class="submit-row submit-row--tight">
							<RouterLink class="button button--small" :to="{ name: 'account', params: { username: detail.account.username } }">Open Account</RouterLink>
							<button v-if="canUnlink" type="button" class="button button--small" :disabled="busy === 'unlink'" @click="unlink"><AdminIcon name="unlink" />Unlink</button>
						</div>
					</div>
					<p class="panel__note">Unlinking leaves this profile and its <strong>{{ plural(profile.uses, 'byline', 'bylines') }}</strong> in place, as a guest profile. It doesn't change <strong>{{ detail.account.username }}</strong> otherwise.</p>
				</template>
				<div v-else-if="detail.linked" class="panel__body"><p>An account is linked to this profile.</p></div>
				<template v-else>
					<div class="panel__body">
						<div class="link-box link-box--blank">
							<span class="avatar avatar--large avatar--guest" aria-hidden="true"><AdminIcon name="key-round" /></span>
							<span class="link-box__text">
								<span class="link-box__name">Guest profile</span>
								<span class="link-box__meta">{{ name }} is credited on the site and has archives, but cannot sign in. Linking an account gives that person the admin.</span>
							</span>
							<span v-if="canLink" class="link-box__buttons">
								<button type="button" class="button button--small" @click="startLink"><AdminIcon name="link" />Link an Account</button>
							</span>
						</div>
					</div>
					<p class="panel__note">A guest profile is the normal state for anyone who writes for the site without working in it.</p>
				</template>
			</section>
		</div>

		<section v-if="detail && profile" class="panel" aria-labelledby="appears-heading">
			<header class="panel__header">
				<h2 id="appears-heading">Where This Profile Appears</h2>
				<p class="panel__hint">Its own page, plus one row per profile field that has an archive</p>
			</header>
			<div class="table-wrap">
				<table class="table res" aria-labelledby="appears-heading">
					<colgroup><col class="res__field"><col><col class="res__content"><col class="res__actions"></colgroup>
					<thead>
						<tr>
							<th scope="col">Field</th>
							<th scope="col">Archive</th>
							<th scope="col">Content</th>
							<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<tr class="res__default">
							<th scope="row">
								<span class="res__name">The profile</span>
								<span class="res__about">Its own page, and every archive's default</span>
							</th>
							<td><span v-if="profile.url" class="res__path">{{ profile.url }}</span><span v-else class="muted">—</span></td>
							<td><span class="pill pill--written">Written</span></td>
							<td class="table__actions">
								<RouterLink v-if="editRoute" class="button button--small" :to="editRoute">Edit</RouterLink>
							</td>
						</tr>
						<tr v-for="row in detail.appears" :key="`${row.type}.${row.relation}`" :class="{ 'res__off': !row.archive }">
							<th scope="row">
								<span class="res__name">{{ row.label }}</span>
								<span class="res__about">{{ row.typeLabel }} · {{ row.archive ? plural(row.entries, 'entry', 'entries') : 'archive is off' }}</span>
							</th>
							<td><span v-if="row.archive" class="res__path">{{ row.archive }}</span><span v-else class="muted">—</span></td>
							<td>
								<template v-if="!row.archive">
									<span v-if="row.page" class="pill" title="Kept, but nothing routes to it while the archive is off">Unreachable</span>
									<span v-else class="muted">—</span>
								</template>
								<span v-else-if="row.page" class="pill pill--written">Written</span>
								<span v-else class="pill pill--inherited">Inherited</span>
							</td>
							<td class="table__actions">
								<MenuButton v-if="row.page" button-class="button button--small" align="end" floating>
									<template #button>Edit<AdminIcon name="chevron-down" /></template>
									<RouterLink class="menu-item" :to="entryRoute(row.page)"><AdminIcon name="pen-line" />Edit the page</RouterLink>
									<template v-if="canType(row.type, 'delete')">
										<div class="menu-divider" />
										<button type="button" class="menu-item menu-item--danger" :disabled="busy === `${row.type}.${row.relation}`" @click="deletePage(row)"><AdminIcon name="trash-2" />Move to trash</button>
									</template>
								</MenuButton>
								<button v-else-if="row.archive && canType(row.type, 'create')" type="button" class="button button--small" :disabled="busy === `${row.type}.${row.relation}`" @click="write(row)">Write One</button>
								<RouterLink v-else-if="!row.archive && can('site.settings')" class="button button--ghost button--small" :to="{ name: 'content-type', params: { name: row.type } }">Type Settings</RouterLink>
							</td>
						</tr>
						<tr v-for="type in uncredited" :key="type.name">
							<th scope="row">
								<span class="res__name">{{ type.labels.plural }}</span>
								<span class="res__about">No profile field</span>
							</th>
							<td><span class="muted">—</span></td>
							<td><span class="muted">—</span></td>
							<td class="table__actions" />
						</tr>
					</tbody>
				</table>
			</div>
			<p class="panel__note"><strong>Inherited</strong> means the archive shows this profile's own body. Writing one creates a page for that archive: an ordinary entry, with its own status, that can't be duplicated. A filled dot is its own content and a ring is borrowed, so neither reads as a status.</p>
		</section>

		<PickModal v-model:pick="pick" :open="linking" noun="Account" :items="linkItems" none="Every account already has a profile. An account holds at most one." :busy="busy === 'link'" @close="linking = false" @confirm="link">
			Only accounts with no profile are listed. Linking makes <strong>{{ name }}</strong> that person's public profile, and the entries crediting it theirs.
		</PickModal>
	</div>
</template>

<style scoped>
.linked__who {
	padding-top: var(--s-4);
	padding-bottom: 0;
	border-top: 1px solid var(--border);
}

/* Where it appears: the profile's own row tinted as everyone's default,
   and a field whose archive is off faded. */
.res {
	min-width: 720px;
	table-layout: fixed;
}

.res__field {
	width: 28%;
}

.res__content {
	width: 128px;
}

.res__actions {
	width: 144px;
}

.res th[scope="row"] {
	font-weight: 400;
}

.res__name {
	display: block;
	color: var(--fg);
	font-weight: 500;
}

.res__about {
	display: block;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.res__path {
	display: block;
	overflow: hidden;
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.res tbody .res__default > *,
.res tbody .res__default:hover > * {
	background: var(--accent-soft);
}

.res__off > * {
	opacity: .62;
}

.table__actions {
	white-space: nowrap;
	text-align: right;
}
</style>
