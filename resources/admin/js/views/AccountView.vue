<script setup lang="ts">
/**
 * One account (D-249, D-312; the profiles sketch's account screen,
 * D-369) at `/accounts/{username}`. Your own is **Your Account**, at
 * your own address (D-371; `/profile` leads there): the same screen,
 * with your password, name, email, and theme to change, and its trail
 * and title say Your Account.
 *
 * - The header: **All accounts** above the title, the account's display
 *   name (its own, else its profile's title, else its username, D-370),
 *   its username, standing, roles, and last sign-in, and an
 *   **Actions** menu: open its profile, make a password link, suspend or
 *   reinstate, and delete.
 * - **Account**: its username and dates, and, on your own, changing your
 *   password. **Roles** beside it, ticked and then saved (Discard puts
 *   them back).
 * - **Public Profile** (D-353), the full width: linked, linked but a
 *   draft, linked to a slug with no file, or none (link an existing one,
 *   or create one).
 * - On your own, **Theme and Color Scheme** (`AccountPreferences`).
 *
 * Blush sends no email, so a password is never set here: **Make a
 * password link** gives a link to copy and send, for a new account or a
 * forgotten password. A link just made (here, or by New Account) is
 * shown once; only a hash of it is kept.
 *
 * Your own account, and an account that can do things you can't, are
 * shown without the controls (`PeopleRules`), with a note saying why.
 * An owner's account is changed only by an owner (D-500); while the site
 * has none, Your Account offers to make you the owner.
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AccountPreferences from '../components/AccountPreferences.vue';
import AdminIcon from '../components/AdminIcon.vue';
import MenuButton from '../components/MenuButton.vue';
import AdminModal from '../components/AdminModal.vue';
import PickModal, { type PickItem } from '../components/PickModal.vue';
import RoleChecks from '../components/RoleChecks.vue';
import StatusPill from '../components/StatusPill.vue';
import { useAction } from '../action';
import { ApiError, entryRoute, errorMessage, patchEntry, request, type EntryDetail } from '../api';
import { config } from '../config';
import { plural } from '../format';
import { confirmAction } from '../confirm';
import { slugOf } from '../references';
import { claimable, freshLink, initials, loadAccounts, loadLinkable, loadRoles, makePasswordLink, removeAccount, statusPill, updateAccount, when, OWNER, type AccountInfo, type LinkableProfile, type PasswordLink, type RoleInfo, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { can, canType, loadSession, saveOwnDetails, session } from '../session';
import { loadTypes, profileType } from '../types';
import { copyText, toast } from '../toast';

const route  = useRoute();
const router = useRouter();

// Your Account is this screen on your own row, at its own address
// (D-371).
const mine     = computed(() => route.params.username === session.account?.username);
const accounts = ref<AccountInfo[] | null>(null);
const roles    = ref<RoleList | null>(null);
const error    = ref('');

// Your own account, from the session, for accounts that can't list them.
const own = computed<AccountInfo | undefined>(() => {
	const account = session.account;

	return account === null ? undefined : {
		username: account.username,
		email: account.email,
		name: account.name,
		displayName: account.displayName,
		roles: account.roles.map((role) => role.name),
		author: account.author,
		profile: account.profile,
		created: account.created,
		lastLogin: account.lastLogin,
		status: 'active',
		link: null,
		manages: false
	};
});

async function load(): Promise<void> {
	error.value = '';

	try {
		[accounts.value, roles.value] = mine.value && !can('accounts.view')
			? [[], null]
			: await Promise.all([loadAccounts(), loadRoles()]);
	} catch (caught) {
		error.value = errorMessage(caught, 'The account couldn\'t be loaded.');
	}
}

void load();
loadTypes().catch(() => undefined);

const account = computed(() => mine.value ? own.value : accounts.value?.find((item) => item.username === route.params.username));
const hasProfile = computed(() => account.value !== undefined && account.value.profile !== null);
const label   = (name: string): string => roles.value?.roles.find((role) => role.name === name)?.label ?? session.account?.roles.find((role) => role.name === name)?.label ?? name;

// Without `GET roles`, your own roles are drawn from the session's labels.
const roleList = computed<RoleInfo[]>(() => roles.value?.roles ?? (session.account?.roles ?? []).map((role) => ({
	name: role.name,
	label: role.label,
	description: '',
	capabilities: [],
	builtIn: false,
	origin: 'config',
	accounts: [],
	grantable: true,
	editable: false
})));

const editable = computed(() => account.value !== undefined && !mine.value && account.value.manages && can('accounts.roles'));
// Linking: anyone's you manage, and your own (D-373).
const linking  = computed(() => account.value !== undefined && (mine.value || account.value.manages) && can('accounts.edit'));

watch(account, (value) => {
	screenTitle.value = mine.value ? 'Your Account' : (value ? value.displayName : null);
}, { immediate: true });

function replace(changed: AccountInfo): void {
	accounts.value = accounts.value?.map((item) => item.username === changed.username ? changed : item) ?? null;
}

// After your own profile changes, the session has its new name.
async function refresh(): Promise<void> {
	if (mine.value) {
		await loadSession(true);
	}

	if (!mine.value || can('accounts.view')) {
		accounts.value = await loadAccounts();
	}
}

// Roles: ticked, then saved or discarded.
const held = ref<string[]>([]);

const { busy: rolesBusy, error: rolesError, run: runRoles } = useAction();

watch(account, (value) => {
	held.value = value ? [...value.roles] : [];
}, { immediate: true });

const rolesChanged = computed(() => account.value !== undefined && [...held.value].sort().join() !== [...account.value.roles].sort().join());

async function saveRoles(): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	await runRoles('The roles couldn\'t be saved.', async () => {
		const changed = await updateAccount(current.username, { roles: held.value });

		replace(changed);
		toast(`Saved roles for ${changed.displayName}`);
	});
}

function discardRoles(): void {
	held.value       = account.value ? [...account.value.roles] : [];
	rolesError.value = '';
}

const rolesNote = computed(() => {
	if (editable.value) {
		return 'Changes take effect when you save them, not as you tick.';
	}

	return mine.value ? 'Read-only, because it\'s your own account: someone else who manages accounts changes your roles.' : 'Read-only, because you can\'t change this account\'s roles.';
});

// Making yourself the owner of a site that has none (D-500).
const { busy: claimBusy, error: claimError, run: runClaim } = useAction();

async function claimOwner(): Promise<void> {
	const current = account.value;

	if (current === undefined || !await confirmAction({
		title: 'Make Yourself the Owner?',
		body: [
			'An owner can do everything, always, and only an owner can change an owner\'s account or make another owner. Nobody else can suspend, remove, or demote you.',
			'You keep the roles you have. Once the site has an owner, only owners can make more.'
		],
		confirm: 'Make Me the Owner'
	})) {
		return;
	}

	await runClaim('You couldn\'t be made the owner.', async () => {
		await updateAccount(current.username, { roles: [...current.roles, OWNER] });
		await refresh();
		toast('You\'re the site\'s owner');
	});
}

// The public profile (D-353): linked, linked but not yet public, linked
// to a slug with no file, or none, with what each allows. Linking picks
// an existing profile; creating makes a draft with the name given, links
// it, and opens it.
// Which modal is open: linking an existing profile, or creating one.
const profileMode  = ref<'' | 'link' | 'create'>('');
const pick         = ref('');
const newSlugTyped = ref('');
const linkable     = ref<LinkableProfile[] | null>(null);
// Only profiles no account has can be linked: a profile belongs to one.
// Locked ones (D-605) can't be.
const free         = computed(() => (linkable.value ?? []).filter((item) => item.account === null && item.linkable));
const linkItems    = computed<PickItem[] | null>(() => linkable.value === null ? null : free.value.map((item) => ({
	key: item.slug,
	initials: initials(item.title),
	name: item.title,
	meta: item.slug,
	aside: item.status === 'published' ? undefined : (item.status === 'draft' ? 'Draft' : 'Scheduled')
})));
const newName      = ref('');

const { busy: profileBusy, error: profileError, run: runProfile } = useAction();

watch(account, (value) => {
	profileMode.value = '';
	pick.value        = value?.author ?? '';
	newName.value     = '';
}, { immediate: true });

const newSlug = computed(() => slugOf(newSlugTyped.value.trim() === '' ? newName.value : newSlugTyped.value));

// Creating one starts from the account's own name, which is the common
// case: the person's name, on the site as in the admin.
function startCreate(): void {
	newName.value      = account.value?.name ?? '';
	newSlugTyped.value = '';
	profileError.value = '';
	profileMode.value  = 'create';
}

async function startLink(): Promise<void> {
	pick.value         = '';
	profileError.value = '';
	profileMode.value  = 'link';

	try {
		linkable.value = await loadLinkable();
	} catch (caught) {
		profileError.value = errorMessage(caught, 'The profiles couldn\'t be loaded.');
		linkable.value     = [];
	}
}

async function copyEmail(email: string): Promise<void> {
	await copyText(email, 'the email address', email);
}

async function changeLink(author: string | null, done: string): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	await runProfile('The profile couldn\'t be linked.', async () => {
		replace(await updateAccount(current.username, { author }));

		if (mine.value) {
			await loadSession(true);
		}

		profileMode.value = '';
		toast(done);
	});
}

function linkProfile(): Promise<void> {
	const chosen = free.value.find((item) => item.slug === pick.value);

	return pick.value === '' ? Promise.resolve() : changeLink(pick.value, `Linked ${chosen?.title ?? pick.value}`);
}

async function unlinkProfile(): Promise<void> {
	const current = account.value;
	const page    = current?.profile;
	const title   = page ? page.title || page.slug : (current?.author ?? '');

	if (current === undefined || !await confirmAction({
		title: `Unlink ${title}?`,
		body: [
			`The profile stays exactly as it is${page?.status === 'published' ? ': published' : ''}, with its **${plural(page?.uses ?? 0, 'byline', 'bylines')}** and every archive it serves. It becomes a **guest profile**: credited on the site, unable to sign in.`,
			`${mine.value ? 'Your account keeps its' : 'The account keeps its'} roles and its sign-in.`
		],
		confirm: 'Unlink the Profile'
	})) {
		return;
	}

	await changeLink(null, `Unlinked ${title}`);
}

async function createProfile(): Promise<void> {
	const current = account.value;

	if (current === undefined || profileType.value === null || newSlug.value === '') {
		return;
	}

	const type = profileType.value;

	await runProfile('The profile couldn\'t be created.', async () => {
		const created = await request<EntryDetail>('POST', '/entries', { type, title: newName.value.trim(), slug: newSlug.value, status: 'draft' });

		replace(await updateAccount(current.username, { author: created.slug }));

		if (mine.value) {
			await loadSession(true);
		}

		profileMode.value = '';
		toast(`Created ${newName.value.trim()}`);
		await router.push({ name: 'profile-detail', params: { slug: created.slug } });
	});
}

// Gives the profile an account links to its file: a draft, titled with
// the slug until it's named, opened to write.
async function createLinked(): Promise<void> {
	const current = account.value;

	if (current === undefined || !current.author || profileType.value === null) {
		return;
	}

	const type   = profileType.value;
	const author = current.author;

	await runProfile('The profile couldn\'t be created.', async () => {
		const created = await request<EntryDetail>('POST', '/entries', { type, title: author, slug: author, status: 'draft' });

		await refresh();
		await router.push(entryRoute({ id: created.id, type: created.type.name }));
	});
}

// Publishes a draft profile, so its page and bylines go live.
async function publishProfile(): Promise<void> {
	const page = account.value?.profile;

	if (!page?.id) {
		return;
	}

	const id = page.id;

	await runProfile('The profile couldn\'t be published.', async () => {
		await patchEntry(id, { status: 'published' });
		await refresh();
		toast(`Published ${page.title || page.slug}`);
	});
}

const profileUrl = computed(() => {
	const page = account.value?.profile;

	return page && page.status === 'published' && page.url ? new URL(page.url, config.site.url).href : null;
});

const canOpenProfile = computed(() => profileType.value !== null && canType(profileType.value, 'edit'));

// The password link: the one just made, shown once.
const link   = ref<PasswordLink | null>(null);
const copied = ref('');

const { busy: linkBusy, error: linkError, run: runLink } = useAction();

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

	if (current === undefined || (current.link !== null && !current.link.expired && !await confirmAction({ title: `Make a New Link for ${current.displayName}?`, body: 'The one they have stops working.', confirm: 'Make a New Link' }))) {
		return;
	}

	copied.value = '';

	await runLink('The link couldn\'t be made.', async () => {
		const answer = await makePasswordLink(current.username);

		replace(answer.account);
		link.value = answer.link;
	});
}

async function copyLink(): Promise<void> {
	if (link.value === null) {
		return;
	}

	copied.value = await copyText(link.value.url, 'the link') ? 'Copied the link.' : 'Copying failed; select the link and copy it instead.';
}

function selectAll(event: Event): void {
	(event.target as HTMLInputElement).select();
}

// The Actions menu: what someone who manages accounts does to the account
// as a whole.
const { busy: actionsBusy, error: actionsError, run: runActions } = useAction();

const actions = computed(() => {
	const current = account.value;

	return current !== undefined && !mine.value && current.manages && (can('accounts.edit') || can('accounts.suspend') || can('accounts.delete'));
});

async function setSuspended(suspended: boolean): Promise<void> {
	const current = account.value;

	if (current === undefined || (suspended && !await confirmAction({
		title: `Suspend ${current.displayName}?`,
		body: [
			'They stay in the list and keep their roles, but they\'re signed out and can\'t sign in until someone reinstates them.',
			current.profile ? `Their profile stays as it is, and its **${plural(current.profile.uses, 'byline', 'bylines')}** are untouched: suspending an account is about signing in, not about the site.` : 'Suspending an account is about signing in, not about the site.'
		],
		confirm: 'Suspend the Account'
	}))) {
		return;
	}

	await runActions('The account couldn\'t be changed.', async () => {
		replace(await updateAccount(current.username, { suspended }));
		link.value = null;
		toast(suspended ? `Suspended ${current.displayName}` : `Reinstated ${current.displayName}`, { kind: suspended ? 'danger' : 'good' });
	});
}

async function remove(): Promise<void> {
	const current = account.value;
	const page    = current?.profile;
	const stays   = page ? `**${page.title || page.slug}** isn't deleted. It stays as a guest profile, keeps its **${plural(page.uses, 'byline', 'bylines')}**, and keeps serving its archives.` : 'This account has no profile, so nothing on the site changes.';

	if (current === undefined || !await confirmAction({ title: `Delete ${current.displayName}?`, body: ['This can\'t be undone. The account, its roles, and its sign-in are gone for good.', stays], confirm: 'Delete the Account', danger: true })) {
		return;
	}

	await runActions('The account couldn\'t be deleted.', async () => {
		await removeAccount(current.username);
		toast(`Deleted ${current.displayName}`, { kind: 'danger' });
		await router.push({ name: 'accounts' });
	});
}

// The account's details: its display name (D-322) and email address
// (D-370), yours on Your Account, or another's with `accounts.edit`.
const canEditDetails = computed(() => account.value !== undefined && (mine.value || (account.value.manages && can('accounts.edit'))));
const editingDetails = ref(false);
const detailName     = ref('');
const detailEmail    = ref('');
const detailsField   = ref<'name' | 'email' | null>(null);
const nameInput      = ref<HTMLInputElement | null>(null);
const emailInput     = ref<HTMLInputElement | null>(null);

const { busy: detailsBusy, error: detailsError, run: runDetails } = useAction();

watch(account, () => {
	editingDetails.value = false;
});

async function startDetails(focus: 'name' | 'email' = 'name'): Promise<void> {
	detailName.value     = account.value?.name ?? '';
	detailEmail.value    = account.value?.email ?? '';
	detailsError.value   = '';
	detailsField.value   = null;
	editingDetails.value = true;
	await nextTick();
	(focus === 'email' ? emailInput : nameInput).value?.focus();
}

async function saveDetails(): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	detailsField.value = null;

	const changes = { name: detailName.value.trim() === '' ? null : detailName.value, email: detailEmail.value.trim() };

	await runDetails('The details couldn\'t be saved.', async () => {
		if (mine.value) {
			await saveOwnDetails(changes);
		} else {
			replace(await updateAccount(current.username, changes));
		}

		editingDetails.value = false;
		toast(`Saved ${mine.value ? 'your' : `${account.value?.displayName ?? current.username}'s`} details`);
	}, (caught) => {
		detailsField.value = caught instanceof ApiError && (caught.field === 'name' || caught.field === 'email') ? caught.field : null;
		(detailsField.value === 'email' ? emailInput : nameInput).value?.focus();
	});
}

// Changing your own password: the form is shown on request.
const changing        = ref(false);
const currentPassword = ref('');
const newPassword     = ref('');
const passwordField   = ref<'current' | 'password' | null>(null);
const currentInput    = ref<HTMLInputElement | null>(null);
const newInput        = ref<HTMLInputElement | null>(null);
const changeButton    = ref<HTMLButtonElement | null>(null);

const { busy: passwordBusy, error: passwordError, run: runPassword } = useAction();

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
	passwordField.value = null;

	await runPassword('Your password couldn\'t be changed.', async () => {
		await request<void>('POST', '/password', { current: currentPassword.value, password: newPassword.value });
		await stopPasswordChange();
		toast('Password changed. You\'re signed out everywhere else.');
	}, (caught) => {
		passwordField.value = caught instanceof ApiError && (caught.field === 'current' || caught.field === 'password') ? caught.field : null;
		(passwordField.value === 'password' ? newInput : currentInput).value?.focus();
	});
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<RouterLink v-if="can('accounts.view')" class="page-back" :to="{ name: 'accounts' }"><AdminIcon name="chevron-left" />All accounts</RouterLink>
			<div class="page-header__id">
				<span v-if="account" class="avatar avatar--large" :class="{ 'avatar--guest': !hasProfile }" aria-hidden="true">{{ initials(account.displayName) }}</span>
				<div class="page-header__text">
					<h1 tabindex="-1">{{ account ? account.displayName : (mine ? 'Your Account' : 'Account') }}</h1>
					<p v-if="account" class="page-header__hint">
						<span class="mono">{{ account.username }}</span>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>{{ account.roles.map(label).join(', ') }}</span>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>last signed in {{ account.lastLogin ? when(account.lastLogin) : 'never' }}</span>
					</p>
				</div>
			</div>
			<div v-if="actions && account" class="page-header__actions">
				<MenuButton button-class="button" align="end">
					<template #button><AdminIcon name="ellipsis" />Actions</template>
					<RouterLink v-if="account.profile && canOpenProfile" class="menu-item" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }"><AdminIcon name="user-round" />Open profile</RouterLink>
					<button v-if="account.email" type="button" class="menu-item" @click="copyEmail(account.email)"><AdminIcon name="copy" />Copy email address</button>
					<button v-if="can('accounts.edit') && account.status !== 'suspended'" type="button" class="menu-item" :disabled="linkBusy" @click="makeLink"><AdminIcon name="key-round" />{{ account.link ? 'Make a new password link' : 'Make a password link' }}</button>
					<template v-if="can('accounts.suspend') || can('accounts.delete')">
						<div class="menu-divider" />
						<template v-if="can('accounts.suspend')">
							<button v-if="account.status === 'suspended'" type="button" class="menu-item" :disabled="actionsBusy" @click="setSuspended(false)"><AdminIcon name="circle-check" />Reinstate account</button>
							<button v-else type="button" class="menu-item" :disabled="actionsBusy" @click="setSuspended(true)"><AdminIcon name="circle-pause" />Suspend account</button>
						</template>
						<button v-if="can('accounts.delete')" type="button" class="menu-item menu-item--danger" :disabled="actionsBusy" @click="remove"><AdminIcon name="trash-2" />Delete account</button>
					</template>
				</MenuButton>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="accounts && !account" class="notice notice--error" role="alert">There's no “{{ route.params.username }}” account.</p>
		<p v-if="actionsError" class="notice notice--error" role="alert">{{ actionsError }}</p>
		<p v-if="linkError" class="notice notice--error" role="alert">{{ linkError }}</p>

		<p v-if="account && mine" class="notice"><AdminIcon name="info" /><span class="notice__text"><strong>This is your account.</strong> It's the same screen anyone who manages accounts sees, with two differences: your password, email, and theme are yours to change, and your own roles and standing aren't. Someone else who manages accounts changes those.</span></p>
		<div v-if="account && mine && claimable" class="notice notice--warn">
			<AdminIcon name="shield" />
			<span class="notice__text"><strong>This site has no owner.</strong> An owner can do everything, and only an owner can change an owner's account, so nobody can lock them out. You can make yourself the owner.</span>
			<span class="notice__buttons"><button type="button" class="button button--small" :disabled="claimBusy" @click="claimOwner">{{ claimBusy ? 'Saving…' : 'Make Me the Owner' }}</button></span>
		</div>
		<p v-if="claimError" class="notice notice--error" role="alert">{{ claimError }}</p>
		<p v-else-if="account && !account.manages && account.roles.includes(OWNER)" class="notice"><AdminIcon name="info" /><span class="notice__text"><strong>{{ account.displayName }} is an owner,</strong> and only an owner can change an owner's details, roles, standing, and profile.</span></p>
		<p v-else-if="account && !account.manages" class="notice"><AdminIcon name="info" /><span class="notice__text"><strong>{{ account.displayName }} can do things you can't,</strong> so its details, roles, standing, and profile aren't yours to change.</span></p>

		<div v-if="account && !account.email" class="notice notice--warn">
			<AdminIcon name="triangle-alert" />
			<span class="notice__text"><strong>{{ mine ? 'Your account has' : 'This account has' }} no email address.</strong> Every account needs one; {{ mine ? 'yours' : 'this one' }} was made before they were asked for.</span>
			<span v-if="canEditDetails" class="notice__buttons"><button type="button" class="button button--small" @click="startDetails('email')">Add an Email Address</button></span>
		</div>

		<div v-if="account && link" class="notice">
			<AdminIcon name="key-round" />
			<span class="notice__text account-link">
				<span><strong>Send this link to {{ account.displayName }}</strong> however you like, so they can choose a password. It's shown once, works once, and lasts until {{ when(link.expires) }}.</span>
				<span class="account-link__row">
					<input class="input mono account-link__url" :value="link.url" readonly aria-label="Password link" @focus="selectAll">
					<button type="button" class="button button--small" @click="copyLink"><AdminIcon name="copy" />Copy Link</button>
				</span>
				<span class="visually-hidden" aria-live="polite">{{ copied }}</span>
			</span>
		</div>
		<div v-else-if="account && account.status === 'invited' && account.link" class="notice">
			<AdminIcon name="mail" />
			<span class="notice__text"><strong>This account is waiting on its invitation.</strong> {{ account.link.expired ? `Its password link expired ${when(account.link.expires)}; make a new one to send.` : `Its password link works until ${when(account.link.expires)}.` }}</span>
			<span v-if="linking" class="notice__buttons"><button type="button" class="button button--small" :disabled="linkBusy" @click="makeLink">{{ linkBusy ? 'Making…' : 'Make a New Link' }}</button></span>
		</div>

		<div v-if="account" class="pair">
			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header">
					<h2 id="account-heading">Account</h2>
					<p class="panel__hint">{{ mine ? 'Yours to change' : (canEditDetails ? 'An administrator\'s to change' : 'Read-only') }}</p>
				</header>
				<div class="panel__body">
					<dl v-if="!editingDetails" class="kv">
						<div><dt>Username</dt><dd class="mono">{{ account.username }}</dd></div>
						<div><dt>Email</dt><dd :class="{ muted: !account.email }">{{ account.email ?? 'None yet' }}</dd></div>
						<div><dt>Display name</dt><dd :class="{ muted: !account.name }">{{ account.name ?? (account.profile ? `${account.displayName}, from the profile` : 'Not set') }}</dd></div>
						<div><dt>Created</dt><dd>{{ when(account.created) }}</dd></div>
						<div><dt>Last signed in</dt><dd>{{ when(account.lastLogin) }}</dd></div>
					</dl>
					<form v-else class="form-stack details-form" :aria-busy="detailsBusy" @submit.prevent="saveDetails" @keydown.esc="editingDetails = false">
						<p class="field">
							<label for="account-name">Display name</label>
							<input id="account-name" ref="nameInput" v-model="detailName" autocomplete="off" maxlength="100" :placeholder="account.profile?.title ?? account.username" :aria-invalid="detailsField === 'name' || undefined" :aria-describedby="detailsField === 'name' ? 'details-error' : 'account-name-help'">
							<span id="account-name-help" class="field__help">What the admin calls {{ mine ? 'you' : 'them' }}. Left empty, it's {{ mine ? 'your' : 'their' }} profile's title, else the username.</span>
						</p>
						<p class="field">
							<label for="account-email">Email</label>
							<input id="account-email" ref="emailInput" v-model="detailEmail" type="email" autocomplete="email" required :aria-invalid="detailsField === 'email' || undefined" :aria-describedby="detailsField === 'email' ? 'details-error' : 'account-email-help'">
							<span id="account-email-help" class="field__help">Every account needs one. Blush sends no email; it's for the people who manage accounts.</span>
						</p>
						<p v-if="detailsError" id="details-error" class="field__error" role="alert">{{ detailsError }}</p>
						<p class="submit-row submit-row--tight">
							<button type="submit" class="button button--primary button--small" :disabled="detailsBusy">{{ detailsBusy ? 'Saving…' : 'Save' }}</button>
							<button type="button" class="button button--ghost button--small" :disabled="detailsBusy" @click="editingDetails = false">Cancel</button>
						</p>
					</form>
				</div>
				<div v-if="(mine || canEditDetails) && !editingDetails && !changing" class="panel__body panel__buttons">
					<button v-if="mine" ref="changeButton" type="button" class="button button--small" @click="startPasswordChange"><AdminIcon name="key-round" />Change Password</button>
					<button v-if="canEditDetails" type="button" class="button button--small" @click="startDetails()">{{ mine ? 'Change Name or Email' : 'Edit Details' }}</button>
				</div>
				<div v-if="mine && changing" class="panel__body account-password">
					<form class="form-stack details-form" :aria-busy="passwordBusy" @submit.prevent="changePassword" @keydown.esc="stopPasswordChange">
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
						<p class="submit-row submit-row--tight">
							<button type="submit" class="button button--primary button--small" :disabled="passwordBusy">{{ passwordBusy ? 'Changing…' : 'Change Password' }}</button>
							<button type="button" class="button button--ghost button--small" :disabled="passwordBusy" @click="stopPasswordChange">Cancel</button>
						</p>
					</form>
				</div>
				<p class="panel__note">
					<template v-if="account.name">{{ mine ? 'Your' : 'Their' }} display name is what the admin calls {{ mine ? 'you' : 'them' }}.<template v-if="account.profile"> On the site, {{ mine ? 'you\'re' : 'they\'re' }} {{ account.profile.title || account.profile.slug }}, the profile's title.</template></template>
					<template v-else-if="account.profile">With no display name, the admin uses {{ mine ? 'your' : 'their' }} profile's title: {{ account.displayName }}.</template>
					<template v-else>With no display name or profile, the admin uses the username.</template>
				</p>
			</section>

			<section class="panel" aria-labelledby="roles-heading">
				<header class="panel__header">
					<h2 id="roles-heading">Roles</h2>
					<p v-if="!rolesChanged" class="panel__hint">An account can hold more than one</p>
					<div v-if="editable && rolesChanged" class="panel__actions">
						<button type="button" class="button button--small" :disabled="rolesBusy" @click="discardRoles">Discard</button>
						<button type="button" class="button button--primary button--small" :disabled="rolesBusy" @click="saveRoles">{{ rolesBusy ? 'Saving…' : 'Save Roles' }}</button>
					</div>
				</header>
				<div class="panel__body">
					<RoleChecks v-model="held" :roles="roleList" id-prefix="role-" :disabled="!editable" :described-by="rolesError ? 'roles-error' : undefined" />
					<p v-if="rolesError" id="roles-error" class="field__error" role="alert">{{ rolesError }}</p>
				</div>
				<p class="panel__note">{{ rolesNote }}<template v-if="can('accounts.view')"> What each role allows is on <RouterLink class="lnk" :to="{ name: 'roles' }">Roles</RouterLink>.</template></p>
			</section>
		</div>

		<section v-if="account && profileType" class="panel" aria-labelledby="public-heading">
			<header class="panel__header">
				<h2 id="public-heading">Public Profile</h2>
				<p class="panel__hint">{{ mine ? 'Your name and bio on the site' : 'How they appear on the site' }}</p>
			</header>
			<div class="panel__body">
				<div v-if="account.profile" class="link-box">
					<span class="avatar avatar--large" aria-hidden="true">{{ initials(account.profile.title || account.profile.slug) }}</span>
					<span class="link-box__text">
						<RouterLink v-if="canOpenProfile" class="link-box__name lnk" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
						<strong v-else class="link-box__name">{{ account.profile.title || account.profile.slug }}</strong>
						<span v-if="account.profile.url" class="link-box__path">{{ account.profile.url }}</span>
						<span class="link-box__meta">
							<StatusPill :status="account.profile.status" />
							<span>· {{ account.profile.status === 'published' ? plural(account.profile.uses, 'byline', 'bylines') : 'nothing answers at this address yet' }}</span>
						</span>
					</span>
					<span class="link-box__buttons">
						<RouterLink v-if="canOpenProfile" class="button button--small" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">Open Profile</RouterLink>
						<a v-if="profileUrl" class="button button--small" :href="profileUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />View<span class="visually-hidden"> (new tab)</span></a>
						<button v-if="account.profile.status === 'draft' && profileType !== null && canType(profileType, 'publish')" type="button" class="button button--primary button--small" :disabled="profileBusy" @click="publishProfile">Publish</button>
						<button v-if="linking" type="button" class="button button--small" :disabled="profileBusy" @click="unlinkProfile"><AdminIcon name="unlink" />Unlink</button>
					</span>
				</div>
				<div v-else-if="account.author" class="link-box link-box--blank">
					<span class="avatar avatar--large avatar--guest" aria-hidden="true"><AdminIcon name="user-round" /></span>
					<span class="link-box__text">
						<strong class="link-box__name">Linked to <span class="mono">{{ account.author }}</span></strong>
						<span class="link-box__meta">It has no profile file yet, so bylines show the slug and there's no bio.</span>
					</span>
					<span class="link-box__buttons">
						<button v-if="profileType !== null && canType(profileType, 'create')" type="button" class="button button--primary button--small" :disabled="profileBusy" @click="createLinked">{{ profileBusy ? 'Creating…' : 'Create It' }}</button>
						<button v-if="linking" type="button" class="button button--small" :disabled="profileBusy" @click="unlinkProfile"><AdminIcon name="unlink" />Unlink</button>
					</span>
				</div>
				<div v-else class="link-box link-box--blank">
					<span class="avatar avatar--large avatar--guest" aria-hidden="true"><AdminIcon name="user-round" /></span>
					<span class="link-box__text">
						<strong class="link-box__name">No public profile</strong>
						<span class="link-box__meta">{{ mine ? 'You don\'t appear on the site. Entries you write show no byline until a profile is linked.' : `${account.displayName} doesn't appear on the site. Entries they write show no byline until a profile is linked.` }}</span>
					</span>
					<span v-if="linking" class="link-box__buttons">
						<button type="button" class="button button--small" @click="startLink"><AdminIcon name="link" />Link an Existing One</button>
						<button v-if="profileType !== null && canType(profileType, 'create')" type="button" class="button button--primary button--small" @click="startCreate">Create One</button>
					</span>
				</div>
				<p v-if="profileError && profileMode === ''" class="field__error" role="alert">{{ profileError }}</p>
			</div>
			<p v-if="account.profile && linking" class="panel__note">Unlinking leaves the profile and its <strong>{{ plural(account.profile.uses, 'byline', 'bylines') }}</strong> in place, as a guest profile.</p>
			<p v-else-if="!account.profile && !account.author && linking" class="panel__note"><strong>Create one</strong> makes a draft profile with the name you give, linked to this account, and opens it: the common case, and why an account and a profile being separate costs almost nothing.</p>
		</section>

		<AccountPreferences v-if="account && mine" />

		<PickModal v-if="account" v-model:pick="pick" :open="profileMode === 'link'" noun="Profile" :items="linkItems" none="Every profile already belongs to an account. Create a new one instead, and it's linked as it's made." :busy="profileBusy" :error="profileError" @close="profileMode = ''" @confirm="linkProfile">
			Only profiles with no account are listed: a profile belongs to at most one. Entries crediting it become {{ mine ? 'yours' : 'theirs' }}.
			<template v-if="profileType !== null && canType(profileType, 'create')" #empty>
				<button type="button" class="button button--primary" @click="startCreate">Create a Profile</button>
			</template>
		</PickModal>

		<AdminModal v-if="account" :open="profileMode === 'create'" title="Create a Profile" @close="profileMode = ''">
			<p>It starts as a draft, linked to {{ mine ? 'your account' : 'this account' }}, so nothing is public until you publish it.</p>
			<form id="create-profile" class="form-stack create-profile" @submit.prevent="createProfile">
				<p class="field">
					<label for="create-profile-name">Display name</label>
					<input id="create-profile-name" v-model="newName" autocomplete="off" maxlength="100" placeholder="Their name as readers should see it" autofocus aria-describedby="create-profile-name-help">
					<span id="create-profile-name-help" class="field__help">The name on every byline: the profile's title.</span>
				</p>
				<p class="field">
					<label for="create-profile-slug">Slug</label>
					<input id="create-profile-slug" v-model="newSlugTyped" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" :placeholder="slugOf(newName)" aria-describedby="create-profile-slug-help">
					<span id="create-profile-slug-help" class="field__help">Every archive address for this person uses it. Left empty, it follows the name.</span>
				</p>
			</form>
			<p v-if="profileError" class="field__error" role="alert">{{ profileError }}</p>
			<template #footer>
				<button type="button" class="button" @click="profileMode = ''">Cancel</button>
				<button type="submit" form="create-profile" class="button button--primary" :disabled="profileBusy || newSlug === ''">{{ profileBusy ? 'Creating…' : 'Create the Profile' }}</button>
			</template>
		</AdminModal>
	</div>
</template>

<style scoped>
.create-profile {
	margin-top: var(--s-4);
}

.account-link {
	display: grid;
	gap: var(--s-2);
}

.account-link__row {
	display: flex;
	align-items: center;
	gap: var(--s-2);
}

.account-link__url {
	flex: 1;
	min-width: 0;
	font-size: var(--text-xs);
}

.details-form {
	max-width: 24rem;
}

.account-password {
	border-top: 1px solid var(--border);
}
</style>
