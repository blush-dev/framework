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
 *   guest profile, and an account with no profile can be linked here,
 *   unless it's locked (D-605): a switch, for whoever links accounts.
 * - **Where This Profile Appears**: the profile's own page, then one row
 *   per type and credit (D-610: a type crediting people two ways has two
 *   rows), with its archive, how many entries credit it that way, and
 *   where its body comes from, as a pill: **Written** (a page written
 *   for that archive) or **Inherited** (the profile's own). **Write one**
 *   creates that page, a draft, and opens it; **Move to trash** puts the
 *   archive back on the profile's body, and the page can be restored from
 *   its type's Trash tab (D-370). A field whose archive is off keeps a
 *   page written for it, marked **Unreachable**. Types that credit no one
 *   aren't listed (D-610).
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminAvatar from '../components/AdminAvatar.vue';
import AdminIcon from '../components/AdminIcon.vue';
import MenuButton from '../components/MenuButton.vue';
import PickModal, { type PickItem } from '../components/PickModal.vue';
import StatusPill from '../components/StatusPill.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { entryRoute, errorMessage, patchEntry, trashEntry } from '../api';
import { config } from '../config';
import { loadAccounts, loadProfile, removeArchivePage, setLinkable, statusPill, updateAccount, when, writeArchivePage, type AccountInfo, type ProfileAppearance, type ProfileDetail } from '../people';
import { screenTitle, screenTrail } from '../screen';
import { confirmAction } from '../confirm';
import { confirmLeaving } from '../referrers';
import { can, canType, session } from '../session';
import { toast } from '../toast';
import { labelsOf, profileType, types } from '../types';
import { formatNumber } from '../format';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';

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
		error.value  = errorMessage(caught, t('profile_detail_view.errors.load', 'The profile couldn\'t be loaded.'));
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
const name      = computed(() => profile.value ? profile.value.title || profile.value.slug : t('profile_detail_view.profile', 'Profile'));
const editRoute = computed(() => profile.value?.id ? entryRoute({ id: profile.value.id, type: profile.value.type }) : null);
const liveUrl   = computed(() => profile.value?.status === 'published' && profile.value.url ? new URL(profile.value.url, config.site.url).href : null);
const yours     = computed(() => session.account?.author === slug.value);


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
		toast(t('profile_detail_view.published', 'Published {name}', { name: name.value }));
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.publish', 'The profile couldn\'t be published.'));
	} finally {
		busy.value = '';
	}
}

async function trash(): Promise<void> {
	const current = profile.value;

	if (!current?.id) {
		return;
	}

	// Asked only when live entries credit it, as any entry's move is
	// (D-608).
	if (!await confirmLeaving(current.id, name.value, 'trash', true)) {
		return;
	}

	busy.value    = 'trash';
	failure.value = '';

	const back  = route.fullPath;
	const label = name.value;

	try {
		const undo = await trashEntry(current.id);

		// Its Undo puts it back and opens it again (D-525).
		toast(t('profile_detail_view.trashed', 'Moved {name} to the trash', { name: label }), {
			kind: 'danger',
			undo: () => void undo().then(
				() => router.push(back),
				(caught: unknown) => toast(errorMessage(caught, t('profile_detail_view.errors.restore', '{name} couldn\'t be restored.', { name: label })), { kind: 'warn' })
			)
		});
		await router.push(profileType.value ? { name: 'type', params: { type: profileType.value } } : { name: 'dashboard' });
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.trash', 'The profile couldn\'t be moved to the trash.'));
		busy.value    = '';
	}
}

// Writes the page for one archive, then opens it.
async function write(row: ProfileAppearance): Promise<void> {
	if (!await confirmAction({
		title: t('profile_detail_view.write_title', 'Write a {credit} Page?', { credit: row.label }),
		body: [t('profile_detail_view.write_body', '**{archive}** currently shows this profile\'s own body. Writing a page gives that archive its own content for {type} only.', { archive: row.archive ?? t('profile_detail_view.the_archive', 'The archive'), type: row.typeLabel }), t('profile_detail_view.write_body_entry', 'It\'s an ordinary entry with its own status, created as a draft. Moving it to the trash puts the archive back on the profile\'s body.')],
		confirm: t('profile_detail_view.write_confirm', 'Create the Page')
	})) {
		return;
	}

	busy.value    = `${row.type}.${row.relation}`;
	failure.value = '';

	try {
		const page = await writeArchivePage(slug.value, row.type, row.relation);

		await router.push(entryRoute(page));
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.write', 'The page couldn\'t be written.'));
	} finally {
		busy.value = '';
	}
}

// Moves an archive's page to the trash, so it shows the profile's body.
async function deletePage(row: ProfileAppearance): Promise<void> {
	if (!await confirmAction({
		title: t('profile_detail_view.delete_page_title', 'Move the {credit} Page to the Trash?', { credit: row.label }),
		body: [row.archive ? t('profile_detail_view.delete_page_falls_back', '**{archive}** falls back to this profile\'s own body, the way it did before the page was written.', { archive: row.archive }) : t('profile_detail_view.delete_page_off', 'Its archive is off, so nothing shows it now.'), t('profile_detail_view.delete_page_restore', 'You can restore it from the {type} Trash tab.', { type: row.typeLabel })],
		confirm: t('profile_detail_view.delete_page_confirm', 'Move to Trash'),
		danger: true
	})) {
		return;
	}

	busy.value    = `${row.type}.${row.relation}`;
	failure.value = '';

	try {
		await removeArchivePage(slug.value, row.type, row.relation);
		toast(t('profile_detail_view.page_trashed', 'Moved the {credit} page to the trash', { credit: row.label }), { kind: 'danger' });
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.page_trash', 'The page couldn\'t be moved to the trash.'));
	} finally {
		busy.value = '';
	}
}

// Unlinking leaves the profile and its bylines; it becomes a guest's.
async function unlink(): Promise<void> {
	const account = detail.value?.account;

	if (!account || !await confirmAction({
		title: t('profile_detail_view.unlink_title', 'Unlink {name}?', { name: name.value }),
		body: [t('profile_detail_view.unlink_body', 'The profile stays exactly as it is, with its **{count, plural, one {# byline} other {# bylines}}** and every archive it serves. It becomes a **guest profile**: credited on the site, unable to sign in.', { count: profile.value?.uses ?? 0 }), t('profile_detail_view.unlink_account', 'The account **{username}** keeps its roles and its sign-in.', { username: account.username })],
		confirm: t('profile_detail_view.unlink_confirm', 'Unlink the Profile')
	})) {
		return;
	}

	busy.value    = 'unlink';
	failure.value = '';

	try {
		await updateAccount(account.username, { author: null });
		toast(t('profile_detail_view.unlinked', 'Unlinked {name}', { name: name.value }), { kind: 'danger' });
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.unlink', 'The account couldn\'t be unlinked.'));
	} finally {
		busy.value = '';
	}
}

// Linking a guest profile to an account that has none.
const linking  = ref(false);
const free     = ref<AccountInfo[] | null>(null);
const linkItems = computed<PickItem[] | null>(() => free.value?.map((account) => ({
	key: account.username,
	name: account.displayName,
	image: account.avatar,
	meta: account.email ? `${account.username} · ${account.email}` : account.username,
	aside: account.roles.join(', '),
	guest: true
})) ?? null);
const pick     = ref('');
// Whoever links accounts can lock a guest profile against it (D-605).
const canLock  = computed(() => can('accounts.view') && can('accounts.edit') && detail.value !== null && !detail.value.linked && profile.value !== null);
const canLink  = computed(() => canLock.value && profile.value?.linkable === true);
// Anyone's link you manage, and your own (D-373).
const canUnlink = computed(() => detail.value?.account !== null && detail.value?.account !== undefined && can('accounts.edit') && (detail.value.account.manages || detail.value.account.username === session.account?.username));

async function lock(linkable: boolean): Promise<void> {
	busy.value    = 'lock';
	failure.value = '';

	try {
		await setLinkable(slug.value, linkable);
		toast(linkable ? t('profile_detail_view.linkable', '{name} can be linked to an account', { name: name.value }) : t('profile_detail_view.locked', 'Locked {name} against linking', { name: name.value }));
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.change', 'The profile couldn\'t be changed.'));
	} finally {
		busy.value = '';
	}
}

async function startLink(): Promise<void> {
	linking.value = true;
	pick.value    = '';

	try {
		free.value = (await loadAccounts()).filter((account) => account.author === null && (account.manages || account.username === session.account?.username));
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.accounts', 'The accounts couldn\'t be loaded.'));
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
		toast(t('profile_detail_view.linked', 'Linked {name}', { name: free.value?.find((account) => account.username === pick.value)?.displayName ?? pick.value }));
		linking.value = false;
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('profile_detail_view.errors.link', 'The account couldn\'t be linked.'));
	} finally {
		busy.value = '';
	}
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<RouterLink v-if="profileType" class="page-back" :to="{ name: 'type', params: { type: profileType } }"><AdminIcon name="chevron-left" />{{ t('profile_detail_view.all_profiles', 'All profiles') }}</RouterLink>
			<div class="page-header__id">
				<AdminAvatar v-if="profile" class="avatar--large" :class="{ 'avatar--guest': !detail?.linked }" :name="name" :image="profile.avatar" />
				<div class="page-header__text">
					<h1 tabindex="-1">{{ name }}</h1>
					<p v-if="profile" class="page-header__hint">
						<template v-if="profile.url"><span class="mono">{{ profile.url }}</span><span class="page-header__sep" aria-hidden="true">·</span></template>
						<StatusPill :status="profile.status" />
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>{{ t('profile_detail_view.bylines', '{count, plural, one {# byline} other {# bylines} }', { count: profile.uses }) }}</span>
						<span v-if="yours" class="tag--you">{{ t('profile_detail_view.you', 'You') }}</span>
					</p>
				</div>
			</div>
			<div v-if="profile" class="page-header__actions">
				<a v-if="liveUrl" class="button" :href="liveUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />{{ t('profile_detail_view.view', 'View') }}<span class="visually-hidden"> {{ t('profile_detail_view.new_tab', '(new tab)') }}</span></a>
				<button v-if="profile.status === 'draft' && profileType && canType(profileType, 'publish')" type="button" class="button" :disabled="busy === 'publish'" @click="publish">{{ t('profile_detail_view.publish', 'Publish') }}</button>
				<RouterLink v-if="editRoute" class="button button--primary" :to="editRoute"><AdminIcon name="pen-line" />{{ t('profile_detail_view.edit_profile', 'Edit Profile') }}</RouterLink>
				<MenuButton v-if="canUnlink || canLink || (profile.id && profileType && canType(profileType, 'delete'))" button-class="button button--icon" :label="t('profile_detail_view.more_actions', 'More actions')" align="end">
					<template #button><AdminIcon name="ellipsis-vertical" /></template>
					<button v-if="canUnlink" type="button" class="menu-item" @click="unlink"><AdminIcon name="unlink" />{{ t('profile_detail_view.unlink_account_item', 'Unlink the account') }}</button>
					<button v-else-if="canLink" type="button" class="menu-item" @click="startLink"><AdminIcon name="link" />{{ t('profile_detail_view.link_item', 'Link to an account') }}</button>
					<template v-if="profile.id && profileType && canType(profileType, 'delete')">
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" :disabled="busy === 'trash'" @click="trash"><AdminIcon name="trash-2" />{{ t('profile_detail_view.move_to_trash', 'Move to trash') }}</button>
					</template>
				</MenuButton>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>

		<div v-if="detail && profile" class="pair">
			<section class="panel" aria-labelledby="identity-heading">
				<header class="panel__header">
					<h2 id="identity-heading">{{ t('profile_detail_view.identity', 'Identity') }}</h2>
					<p class="panel__hint">{{ t('profile_detail_view.identity_hint', 'What a byline renders, wherever it appears') }}</p>
				</header>
				<div class="panel__body">
					<dl class="fact-rows">
						<div><dt>{{ t('profile_detail_view.display_name', 'Display name') }}</dt><dd>{{ profile.title || '—' }}</dd></div>
						<div><dt>{{ t('profile_detail_view.slug', 'Slug') }}</dt><dd class="mono">{{ profile.slug }}</dd></div>
						<div><dt>{{ t('profile_detail_view.byline_title', 'Byline title') }}</dt><dd :class="{ muted: !profile.subtitle }">{{ profile.subtitle ?? t('profile_detail_view.not_set', 'Not set') }}</dd></div>
						<div><dt>{{ t('profile_detail_view.avatar', 'Avatar') }}</dt><dd :class="{ mono: profile.avatar, muted: !profile.avatar }">{{ profile.avatar ?? t('profile_detail_view.initials', 'Initials') }}</dd></div>
					</dl>
				</div>
				<p class="panel__note"><MessageText :text="t('profile_detail_view.bio_note', 'The bio is this profile\'s body, written in the editor along with the rest of these. {edit} opens it.')"><template #edit><strong>{{ t('profile_detail_view.bio_note_edit', 'Edit profile') }}</strong></template></MessageText></p>
			</section>

			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header">
					<h2 id="account-heading">{{ t('profile_detail_view.linked_account', 'Linked Account') }}</h2>
					<p class="panel__hint">{{ t('profile_detail_view.linked_account_hint', 'At most one, and optional') }}</p>
				</header>
				<template v-if="detail.account">
					<div class="panel__body">
						<div class="who">
							<AdminAvatar class="avatar--large" :name="detail.account.displayName" :image="detail.account.avatar" />
							<span class="who__text">
								<span class="who__name">{{ detail.account.displayName }}</span>
								<span class="who__meta">{{ detail.account.username }}</span>
							</span>
						</div>
						<dl class="fact-rows">
							<div><dt>{{ t('profile_detail_view.email', 'Email') }}</dt><dd :class="{ muted: !detail.account.email }">{{ detail.account.email ?? t('profile_detail_view.none_yet', 'None yet') }}</dd></div>
							<div><dt>{{ t('profile_detail_view.standing', 'Standing') }}</dt><dd>{{ statusPill(detail.account.status).label }}</dd></div>
							<div><dt>{{ t('profile_detail_view.last_signed_in', 'Last signed in') }}</dt><dd>{{ when(detail.account.lastLogin) }}</dd></div>
						</dl>
						<div class="submit-row submit-row--tight">
							<RouterLink class="button button--small" :to="{ name: 'account', params: { username: detail.account.username } }">{{ t('profile_detail_view.open_account', 'Open Account') }}</RouterLink>
							<button v-if="canUnlink" type="button" class="button button--small" :disabled="busy === 'unlink'" @click="unlink"><AdminIcon name="unlink" />{{ t('profile_detail_view.unlink', 'Unlink') }}</button>
						</div>
					</div>
					<p class="panel__note">
						<MessageText :text="t('profile_detail_view.unlink_note', 'Unlinking leaves this profile and its {bylines} in place, as a guest profile. It doesn\'t change {username} otherwise.')">
							<template #bylines><strong>{{ t('profile_detail_view.bylines', '{count, plural, one {# byline} other {# bylines} }', { count: profile.uses }) }}</strong></template>
							<template #username><strong>{{ detail.account.username }}</strong></template>
						</MessageText>
					</p>
				</template>
				<div v-else-if="detail.linked" class="panel__body"><p>{{ t('profile_detail_view.linked_hidden', 'An account is linked to this profile.') }}</p></div>
				<template v-else>
					<div class="panel__body">
						<div class="link-box link-box--blank">
							<span class="avatar avatar--large avatar--guest" aria-hidden="true"><AdminIcon :name="profile.linkable ? 'key-round' : 'lock'" /></span>
							<span class="link-box__text">
								<span class="link-box__name">{{ profile.linkable ? t('profile_detail_view.guest', 'Guest profile') : t('profile_detail_view.locked_guest', 'Locked guest profile') }}</span>
								<span v-if="profile.linkable" class="link-box__meta">{{ t('profile_detail_view.guest_text', '{name} is credited on the site and has archives, but cannot sign in. Linking an account gives that person the admin.', { name }) }}</span>
								<span v-else class="link-box__meta">{{ t('profile_detail_view.locked_guest_text', '{name} is credited on the site and has archives, and no account can be linked to it.', { name }) }}</span>
							</span>
							<span v-if="canLink" class="link-box__buttons">
								<button type="button" class="button button--small" @click="startLink"><AdminIcon name="link" />{{ t('profile_detail_view.link_account', 'Link an Account') }}</button>
							</span>
						</div>
					</div>
					<div v-if="canLock" class="panel__body panel__body--ruled linked__lock">
						<dl class="fact-rows">
							<div>
								<dt>{{ t('profile_detail_view.can_be_linked', 'Can be linked') }}</dt>
								<dd><ToggleSwitch :checked="profile.linkable" :label="t('profile_detail_view.can_be_linked_label', 'Can be linked to an account')" :busy="busy === 'lock'" @change="lock" /></dd>
							</div>
						</dl>
					</div>
					<p class="panel__note">{{ t('profile_detail_view.guest_note', 'A guest profile is the normal state for anyone who writes for the site without working in it. Lock one no one should sign in as, such as an organization\'s byline or someone who has died.') }}</p>
				</template>
			</section>
		</div>

		<section v-if="detail && profile" class="panel" aria-labelledby="appears-heading">
			<header class="panel__header">
				<h2 id="appears-heading">{{ t('profile_detail_view.appears', 'Where This Profile Appears') }}</h2>
				<p class="panel__hint">{{ t('profile_detail_view.appears_hint', 'Its own page, then one row per type and credit') }}</p>
			</header>
			<div class="table-wrap">
				<table class="table res" aria-labelledby="appears-heading">
					<colgroup><col class="res__field"><col><col class="res__count"><col class="res__content"><col class="res__actions"></colgroup>
					<thead>
						<tr>
							<th scope="col">{{ t('profile_detail_view.credit', 'Credit') }}</th>
							<th scope="col">{{ t('profile_detail_view.archive', 'Archive') }}</th>
							<th scope="col" class="table__count">{{ t('profile_detail_view.entries', 'Entries') }}</th>
							<th scope="col">{{ t('profile_detail_view.text', 'Text') }}</th>
							<th scope="col" class="table__actions"><span class="visually-hidden">{{ t('profile_detail_view.actions', 'Actions') }}</span></th>
						</tr>
					</thead>
					<tbody>
						<tr class="res__default">
							<th scope="row">
								<span class="res__name">{{ t('profile_detail_view.the_profile', 'The profile') }}</span>
								<span class="res__about">{{ t('profile_detail_view.the_profile_about', 'Its own page, and every archive\'s default') }}</span>
							</th>
							<td><span v-if="profile.url" class="res__path">{{ profile.url }}</span><span v-else class="muted">—</span></td>
							<td class="table__count muted">—</td>
							<td><span class="pill pill--written">{{ t('profile_detail_view.written', 'Written') }}</span></td>
							<td class="table__actions">
								<RouterLink v-if="editRoute" class="button button--small" :to="editRoute">{{ t('common.edit', 'Edit') }}</RouterLink>
							</td>
						</tr>
						<tr v-for="row in detail.appears" :key="`${row.type}.${row.relation}`" :class="{ 'res__off': !row.archive }">
							<th scope="row">
								<span class="res__name">{{ row.label }}</span>
								<span class="res__about">{{ row.archive ? row.typeLabel : t('profile_detail_view.archive_off', '{type} · archive is off', { type: row.typeLabel }) }}</span>
							</th>
							<td><span v-if="row.archive" class="res__path">{{ row.archive }}</span><span v-else class="muted">—</span></td>
							<td class="table__count mono">{{ formatNumber(row.entries) }}</td>
							<td>
								<template v-if="!row.archive">
									<span v-if="row.page" class="pill" :title="t('profile_detail_view.unreachable_title', 'Kept, but nothing routes to it while the archive is off')">{{ t('profile_detail_view.unreachable', 'Unreachable') }}</span>
									<span v-else class="muted">—</span>
								</template>
								<span v-else-if="row.page" class="pill pill--written">{{ t('profile_detail_view.written', 'Written') }}</span>
								<span v-else class="pill pill--inherited">{{ t('profile_detail_view.inherited', 'Inherited') }}</span>
							</td>
							<td class="table__actions">
								<MenuButton v-if="row.page" button-class="button button--small" align="end" floating>
									<template #button>{{ t('common.edit', 'Edit') }}<AdminIcon name="chevron-down" /></template>
									<RouterLink class="menu-item" :to="entryRoute(row.page)"><AdminIcon name="pen-line" />{{ t('profile_detail_view.edit_page', 'Edit the page') }}</RouterLink>
									<template v-if="canType(row.type, 'delete')">
										<div class="menu-divider" />
										<button type="button" class="menu-item menu-item--danger" :disabled="busy === `${row.type}.${row.relation}`" @click="deletePage(row)"><AdminIcon name="trash-2" />{{ t('profile_detail_view.move_to_trash', 'Move to trash') }}</button>
									</template>
								</MenuButton>
								<button v-else-if="row.archive && canType(row.type, 'create')" type="button" class="button button--small" :disabled="busy === `${row.type}.${row.relation}`" @click="write(row)">{{ t('profile_detail_view.write_one', 'Write One') }}</button>
								<RouterLink v-else-if="!row.archive && can('site.settings')" class="button button--ghost button--small" :to="{ name: 'content-type', params: { name: row.type } }">{{ t('profile_detail_view.type_settings', 'Type Settings') }}</RouterLink>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p class="panel__note"><MessageText :text="t('profile_detail_view.inherited_note', '{inherited} means the archive shows this profile\'s own body. Writing one creates a page for that archive: an ordinary entry, with its own status, that can\'t be duplicated. A filled dot is its own content and a ring is borrowed, so neither reads as a status.')"><template #inherited><strong>{{ t('profile_detail_view.inherited', 'Inherited') }}</strong></template></MessageText></p>
		</section>

		<PickModal v-model:pick="pick" :open="linking" :noun="t('profile_detail_view.pick_noun', 'Account')" :items="linkItems" :none="t('profile_detail_view.pick_none', 'Every account already has a profile. An account holds at most one.')" :busy="busy === 'link'" @close="linking = false" @confirm="link">
			<MessageText :text="t('profile_detail_view.pick_text', 'Only accounts with no profile are listed. Linking makes {name} that person\'s public profile, and the entries crediting it theirs.')"><template #name><strong>{{ name }}</strong></template></MessageText>
		</PickModal>
	</div>
</template>

<style scoped>
/* The lock's switch (D-605), centered on its label. */
.linked__lock {
	padding-block: var(--s-2);
}

.linked__lock .fact-rows > div {
	align-items: center;
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

.res__count {
	width: 88px;
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
	color: var(--color-text);
	font-weight: 500;
}

.res__about {
	display: block;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.res__path {
	display: block;
	overflow: hidden;
	color: var(--color-text-secondary);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.res tbody .res__default > *,
.res tbody .res__default:hover > * {
	background: var(--color-accent-subtle);
}

.res__off > * {
	opacity: .62;
}

.table__actions {
	white-space: nowrap;
	text-align: right;
}
</style>
