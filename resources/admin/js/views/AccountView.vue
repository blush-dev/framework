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
 * - On your own, **Theme and Color Scheme** (`AccountPreferences`) and
 *   **Language** (`AccountLanguage`, D-831).
 *
 * A password is never set here: **Make a password link** gives a link
 * to copy and send, for a new account or a forgotten password, and
 * where the site can send email, **Email a password link** sends one
 * to the account's address instead (D-846). A link just made (here, or
 * by New Account) is shown once; only a hash of it is kept.
 *
 * Your own account, and an account that can do things you can't, are
 * shown without the controls (`UserRules`), with a note saying why.
 * An owner's account is changed only by an owner (D-500); while the site
 * has none, Your Account offers to make you the owner.
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminAvatar from '../components/AdminAvatar.vue';
import AccountLanguage from '../components/AccountLanguage.vue';
import AccountPreferences from '../components/AccountPreferences.vue';
import AccountTokens from '../components/AccountTokens.vue';
import AdminIcon from '../components/AdminIcon.vue';
import MenuButton from '../components/MenuButton.vue';
import AdminModal from '../components/AdminModal.vue';
import MessageText from '../components/MessageText.vue';
import PickModal, { type PickItem } from '../components/PickModal.vue';
import RoleChecks from '../components/RoleChecks.vue';
import ShownOnce from '../components/ShownOnce.vue';
import StatusPill from '../components/StatusPill.vue';
import { useAction } from '../action';
import { ApiError, errorMessage, patchEntry, request, type EntryDetail } from '../api';
import { config } from '../config';
import { confirmAction } from '../confirm';
import { t } from '../messages';
import { slugOf } from '../references';
import { claimable, emailPasswordLink, freshLink, loadAccounts, loadLinkable, loadRoles, makePasswordLink, removeAccount, statusPill, updateAccount, when, OWNER, type AccountInfo, type LinkableProfile, type PasswordLink, type RoleInfo, type RoleList } from '../people';
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
		avatar: account.avatar,
		roles: account.roles.map((role) => role.name),
		author: account.author,
		profile: account.profile,
		created: account.created,
		lastLogin: account.lastLogin,
		status: 'active',
		link: null,
		tokenOnly: false,
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
		error.value = errorMessage(caught, t('account_view.load_failed', 'The account couldn\'t be loaded.'));
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
	screenTitle.value = mine.value ? t('account_view.your_account', 'Your Account') : (value ? value.displayName : null);
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

	await runRoles(t('account_view.roles_save_failed', 'The roles couldn\'t be saved.'), async () => {
		const changed = await updateAccount(current.username, { roles: held.value });

		replace(changed);
		toast(t('account_view.roles_saved', 'Saved roles for {name}', { name: changed.displayName }));
	});
}

function discardRoles(): void {
	held.value       = account.value ? [...account.value.roles] : [];
	rolesError.value = '';
}

const rolesNote = computed(() => {
	if (editable.value) {
		return t('account_view.roles_note_editable', 'Changes take effect when you save them, not as you tick.');
	}

	return mine.value ? t('account_view.roles_note_mine', 'Read-only, because it\'s your own account: someone else who manages accounts changes your roles.') : t('account_view.roles_note_other', 'Read-only, because you can\'t change this account\'s roles.');
});

// Making yourself the owner of a site that has none (D-500).
const { busy: claimBusy, error: claimError, run: runClaim } = useAction();

async function claimOwner(): Promise<void> {
	const current = account.value;

	if (current === undefined || !await confirmAction({
		title: t('account_view.claim_title', 'Make Yourself the Owner?'),
		body: [
			t('account_view.claim_body', 'An owner can do everything, always, and only an owner can change an owner\'s account or make another owner. Nobody else can suspend, remove, or demote you.'),
			t('account_view.claim_body_roles', 'You keep the roles you have. Once the site has an owner, only owners can make more.')
		],
		confirm: t('account_view.make_me_owner', 'Make Me the Owner')
	})) {
		return;
	}

	await runClaim(t('account_view.claim_failed', 'You couldn\'t be made the owner.'), async () => {
		await updateAccount(current.username, { roles: [...current.roles, OWNER] });
		await refresh();
		toast(t('account_view.claimed', 'You\'re the site\'s owner'));
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
	name: item.title,
	meta: item.slug,
	aside: item.status === 'published' ? undefined : (item.status === 'draft' ? t('account_view.draft', 'Draft') : t('account_view.scheduled', 'Scheduled'))
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
		profileError.value = errorMessage(caught, t('account_view.profiles_load_failed', 'The profiles couldn\'t be loaded.'));
		linkable.value     = [];
	}
}

async function copyEmail(email: string): Promise<void> {
	await copyText(email, t('account_view.the_email_address', 'the email address'), email);
}

async function changeLink(author: string | null, done: string): Promise<void> {
	const current = account.value;

	if (current === undefined) {
		return;
	}

	await runProfile(t('account_view.link_failed', 'The profile couldn\'t be linked.'), async () => {
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

	return pick.value === '' ? Promise.resolve() : changeLink(pick.value, t('account_view.linked', 'Linked {title}', { title: chosen?.title ?? pick.value }));
}

async function unlinkProfile(): Promise<void> {
	const current = account.value;
	const page    = current?.profile;
	const title   = page ? page.title || page.slug : '';

	if (current === undefined || !await confirmAction({
		title: t('account_view.unlink_title', 'Unlink {title}?', { title }),
		body: [
			t('account_view.unlink_body', 'The profile stays exactly as it is{published, select, yes {: published} other {}}, with its **{count, plural, one {# byline} other {# bylines}}** and every archive it serves. It becomes a **guest profile**: credited on the site, unable to sign in.', { published: page?.status === 'published' ? 'yes' : 'no', count: page?.uses ?? 0 }),
			mine.value ? t('account_view.unlink_keeps_mine', 'Your account keeps its roles and its sign-in.') : t('account_view.unlink_keeps_other', 'The account keeps its roles and its sign-in.')
		],
		confirm: t('account_view.unlink_confirm', 'Unlink the Profile')
	})) {
		return;
	}

	await changeLink(null, t('account_view.unlinked', 'Unlinked {title}', { title }));
}

async function createProfile(): Promise<void> {
	const current = account.value;

	if (current === undefined || profileType.value === null || newSlug.value === '') {
		return;
	}

	const type = profileType.value;

	await runProfile(t('account_view.create_failed', 'The profile couldn\'t be created.'), async () => {
		const created = await request<EntryDetail>('POST', '/entries', { type, title: newName.value.trim(), slug: newSlug.value, status: 'draft' });

		replace(await updateAccount(current.username, { author: created.slug }));

		if (mine.value) {
			await loadSession(true);
		}

		profileMode.value = '';
		toast(t('account_view.created', 'Created {name}', { name: newName.value.trim() }));
		await router.push({ name: 'profile-detail', params: { slug: created.slug } });
	});
}

// Publishes a draft profile, so its page and bylines go live.
async function publishProfile(): Promise<void> {
	const page = account.value?.profile;

	if (!page?.id) {
		return;
	}

	const id = page.id;

	await runProfile(t('account_view.publish_failed', 'The profile couldn\'t be published.'), async () => {
		await patchEntry(id, { status: 'published' });
		await refresh();
		toast(t('account_view.published', 'Published {title}', { title: page.title || page.slug }));
	});
}

const profileUrl = computed(() => {
	const page = account.value?.profile;

	return page && page.status === 'published' && page.url ? new URL(page.url, config.site.url).href : null;
});

const canOpenProfile = computed(() => profileType.value !== null && canType(profileType.value, 'edit'));

// The password link: the one just made, shown once.
const link = ref<PasswordLink | null>(null);

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

	if (current === undefined || (current.link !== null && !current.link.expired && !await confirmAction({ title: t('account_view.new_link_title', 'Make a New Link for {name}?', { name: current.displayName }), body: t('account_view.new_link_body', 'The one they have stops working.'), confirm: t('account_view.make_new_link', 'Make a New Link') }))) {
		return;
	}

	await runLink(t('account_view.link_make_failed', 'The link couldn\'t be made.'), async () => {
		const answer = await makePasswordLink(current.username);

		replace(answer.account);
		link.value = answer.link;
	});
}

// Emails a new link instead (D-846), where the site can send email and
// the account has an address. Its link is made when it's sent.
const mailable = computed(() => config.mail && account.value !== undefined && account.value.email !== null && account.value.email !== '');

async function emailLink(): Promise<void> {
	const current = account.value;

	if (current === undefined || (current.link !== null && !current.link.expired && !await confirmAction({ title: t('account_view.email_link_title', 'Email {name} a New Link?', { name: current.displayName }), body: t('account_view.email_link_body', 'The one they have stops working once it\'s sent.'), confirm: t('account_view.email_new_link', 'Email a New Link') }))) {
		return;
	}

	await runLink(t('account_view.link_email_failed', 'The link couldn\'t be emailed.'), async () => {
		const answer = await emailPasswordLink(current.username);

		replace(answer.account);
		link.value = null;
		toast(t('account_view.link_emailed', 'Emailing a password link to {email}', { email: answer.sent }));
	});
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
		title: t('account_view.suspend_title', 'Suspend {name}?', { name: current.displayName }),
		body: [
			t('account_view.suspend_body', 'They stay in the list and keep their roles, but they\'re signed out and can\'t sign in until someone reinstates them.'),
			current.profile ? t('account_view.suspend_profile', 'Their profile stays as it is, and its **{count, plural, one {# byline} other {# bylines}}** are untouched: suspending an account is about signing in, not about the site.', { count: current.profile.uses }) : t('account_view.suspend_no_profile', 'Suspending an account is about signing in, not about the site.')
		],
		confirm: t('account_view.suspend_confirm', 'Suspend the Account')
	}))) {
		return;
	}

	await runActions(t('account_view.change_failed', 'The account couldn\'t be changed.'), async () => {
		replace(await updateAccount(current.username, { suspended }));
		link.value = null;
		toast(suspended ? t('account_view.suspended', 'Suspended {name}', { name: current.displayName }) : t('account_view.reinstated', 'Reinstated {name}', { name: current.displayName }), { kind: suspended ? 'danger' : 'good' });
	});
}

async function remove(): Promise<void> {
	const current = account.value;
	const page    = current?.profile;
	const stays   = page ? t('account_view.delete_profile_stays', '**{title}** isn\'t deleted. It stays as a guest profile, keeps its **{count, plural, one {# byline} other {# bylines}}**, and keeps serving its archives.', { title: page.title || page.slug, count: page.uses }) : t('account_view.delete_no_profile', 'This account has no profile, so nothing on the site changes.');

	if (current === undefined || !await confirmAction({ title: t('account_view.delete_title', 'Delete {name}?', { name: current.displayName }), body: [t('account_view.delete_body', 'This can\'t be undone. The account, its roles, and its sign-in are gone for good.'), stays], confirm: t('account_view.delete_confirm', 'Delete the Account'), danger: true })) {
		return;
	}

	await runActions(t('account_view.delete_failed', 'The account couldn\'t be deleted.'), async () => {
		await removeAccount(current.username);
		toast(t('account_view.deleted', 'Deleted {name}', { name: current.displayName }), { kind: 'danger' });
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

	await runDetails(t('account_view.details_save_failed', 'The details couldn\'t be saved.'), async () => {
		if (mine.value) {
			await saveOwnDetails(changes);
		} else {
			replace(await updateAccount(current.username, changes));
		}

		editingDetails.value = false;
		toast(mine.value ? t('account_view.details_saved_mine', 'Saved your details') : t('account_view.details_saved', 'Saved {name}\'s details', { name: account.value?.displayName ?? current.username }));
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

	await runPassword(t('account_view.password_failed', 'Your password couldn\'t be changed.'), async () => {
		await request<void>('POST', '/password', { current: currentPassword.value, password: newPassword.value });
		await stopPasswordChange();
		toast(t('account_view.password_changed', 'Password changed. You\'re signed out everywhere else.'));
	}, (caught) => {
		passwordField.value = caught instanceof ApiError && (caught.field === 'current' || caught.field === 'password') ? caught.field : null;
		(passwordField.value === 'password' ? newInput : currentInput).value?.focus();
	});
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<RouterLink v-if="can('accounts.view')" class="page-back" :to="{ name: 'accounts' }"><AdminIcon name="chevron-left" />{{ t('account_view.all_accounts', 'All accounts') }}</RouterLink>
			<div class="page-header__id">
				<AdminAvatar v-if="account" class="avatar--large" :class="{ 'avatar--guest': !hasProfile }" :name="account.displayName" :image="account.avatar" />
				<div class="page-header__text">
					<h1 tabindex="-1">{{ account ? account.displayName : (mine ? t('account_view.your_account', 'Your Account') : t('account_view.account', 'Account')) }}</h1>
					<p v-if="account" class="page-header__hint">
						<span class="mono">{{ account.username }}</span>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
						<template v-if="account.tokenOnly"><span class="page-header__sep" aria-hidden="true">·</span><span class="pill">{{ t('account_view.token_only', 'Token-only') }}</span></template>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>{{ account.roles.map(label).join(', ') }}</span>
						<span class="page-header__sep" aria-hidden="true">·</span>
						<span>{{ account.tokenOnly ? t('account_view.never_signs_in', 'never signs in') : t('account_view.last_signed_in', 'last signed in {when}', { when: account.lastLogin ? when(account.lastLogin) : t('account_view.never', 'never') }) }}</span>
					</p>
				</div>
			</div>
			<div v-if="actions && account" class="page-header__actions">
				<MenuButton button-class="button" align="end">
					<template #button><AdminIcon name="ellipsis" />{{ t('account_view.actions', 'Actions') }}</template>
					<RouterLink v-if="account.profile && canOpenProfile" class="menu-item" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }"><AdminIcon name="user-round" />{{ t('account_view.open_profile', 'Open profile') }}</RouterLink>
					<button v-if="account.email" type="button" class="menu-item" @click="copyEmail(account.email)"><AdminIcon name="copy" />{{ t('account_view.copy_email', 'Copy email address') }}</button>
					<button v-if="can('accounts.edit') && account.status !== 'suspended' && !account.tokenOnly" type="button" class="menu-item" :disabled="linkBusy" @click="makeLink"><AdminIcon name="key-round" />{{ account.link ? t('account_view.make_new_password_link', 'Make a new password link') : t('account_view.make_password_link', 'Make a password link') }}</button>
					<button v-if="mailable && can('accounts.edit') && account.status !== 'suspended' && !account.tokenOnly" type="button" class="menu-item" :disabled="linkBusy" @click="emailLink"><AdminIcon name="mail" />{{ t('account_view.email_password_link', 'Email a password link') }}</button>
					<template v-if="can('accounts.suspend') || can('accounts.delete')">
						<div class="menu-divider" />
						<template v-if="can('accounts.suspend')">
							<button v-if="account.status === 'suspended'" type="button" class="menu-item" :disabled="actionsBusy" @click="setSuspended(false)"><AdminIcon name="circle-check" />{{ t('account_view.reinstate', 'Reinstate account') }}</button>
							<button v-else type="button" class="menu-item" :disabled="actionsBusy" @click="setSuspended(true)"><AdminIcon name="circle-pause" />{{ t('account_view.suspend', 'Suspend account') }}</button>
						</template>
						<button v-if="can('accounts.delete')" type="button" class="menu-item menu-item--danger" :disabled="actionsBusy" @click="remove"><AdminIcon name="trash-2" />{{ t('account_view.delete', 'Delete account') }}</button>
					</template>
				</MenuButton>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="accounts && !account" class="notice notice--error" role="alert">{{ t('account_view.not_found', 'There\'s no “{username}” account.', { username: route.params.username }) }}</p>
		<p v-if="actionsError" class="notice notice--error" role="alert">{{ actionsError }}</p>
		<p v-if="linkError" class="notice notice--error" role="alert">{{ linkError }}</p>

		<div v-if="account && mine && claimable" class="notice notice--warn">
			<AdminIcon name="shield" />
			<span class="notice__text"><strong>{{ t('account_view.no_owner', 'This site has no owner.') }}</strong> {{ t('account_view.no_owner_text', 'An owner can do everything, and only an owner can change an owner\'s account, so nobody can lock them out. You can make yourself the owner.') }}</span>
			<span class="notice__buttons"><button type="button" class="button button--small" :disabled="claimBusy" @click="claimOwner">{{ claimBusy ? t('account_view.saving', 'Saving…') : t('account_view.make_me_owner', 'Make Me the Owner') }}</button></span>
		</div>
		<p v-if="claimError" class="notice notice--error" role="alert">{{ claimError }}</p>
		<p v-else-if="account && !mine && !account.manages && account.roles.includes(OWNER)" class="notice"><AdminIcon name="info" /><span class="notice__text"><MessageText :text="t('account_view.is_owner', '{lead} and only an owner can change an owner\'s details, roles, standing, and profile.')"><template #lead><strong>{{ t('account_view.is_owner_lead', '{name} is an owner,', { name: account.displayName }) }}</strong></template></MessageText></span></p>
		<p v-else-if="account && !mine && !account.manages" class="notice"><AdminIcon name="info" /><span class="notice__text"><MessageText :text="t('account_view.can_do_more', '{lead} so its details, roles, standing, and profile aren\'t yours to change.')"><template #lead><strong>{{ t('account_view.can_do_more_lead', '{name} can do things you can\'t,', { name: account.displayName }) }}</strong></template></MessageText></span></p>

		<p v-if="account && account.tokenOnly" class="notice"><AdminIcon name="info" /><span class="notice__text"><strong>{{ mine ? t('account_view.token_only_mine', 'Your account is token-only.') : t('account_view.token_only_other', 'This account is token-only.') }}</strong> {{ t('account_view.token_only_text', 'It never signs in, so it has no password: apps, scripts, and agents use it through its API tokens, below, with its roles.') }}</span></p>

		<div v-if="account && !account.email && !account.tokenOnly" class="notice notice--warn">
			<AdminIcon name="triangle-alert" />
			<span class="notice__text"><strong>{{ mine ? t('account_view.no_email_mine', 'Your account has no email address.') : t('account_view.no_email_other', 'This account has no email address.') }}</strong> {{ mine ? t('account_view.no_email_text_mine', 'Every account needs one; yours was made before they were asked for.') : t('account_view.no_email_text_other', 'Every account needs one; this one was made before they were asked for.') }}</span>
			<span v-if="canEditDetails" class="notice__buttons"><button type="button" class="button button--small" @click="startDetails('email')">{{ t('account_view.add_email', 'Add an Email Address') }}</button></span>
		</div>

		<ShownOnce v-if="account && link" :value="link.url" :label="t('account_view.password_link', 'Password link')" :noun="t('account_view.the_link', 'the link')" :copy="t('account_view.copy_link', 'Copy Link')">
			<MessageText :text="t('account_view.send_link', '{send} however you like, so they can choose a password. It\'s shown once, works once, and lasts until {date}.', { date: when(link.expires) })"><template #send><strong>{{ t('account_view.send_link_lead', 'Send this link to {name}', { name: account.displayName }) }}</strong></template></MessageText>
		</ShownOnce>
		<div v-else-if="account && account.status === 'invited' && account.link" class="notice">
			<AdminIcon name="mail" />
			<span class="notice__text"><strong>{{ t('account_view.invited', 'This account is waiting on its invitation.') }}</strong> {{ account.link.expired ? t('account_view.link_expired', 'Its password link expired {date}; make a new one to send.', { date: when(account.link.expires) }) : t('account_view.link_works_until', 'Its password link works until {date}.', { date: when(account.link.expires) }) }}</span>
			<span v-if="linking" class="notice__buttons"><button v-if="mailable" type="button" class="button button--small" :disabled="linkBusy" @click="emailLink">{{ t('account_view.email_new_link', 'Email a New Link') }}</button><button type="button" class="button button--small" :disabled="linkBusy" @click="makeLink">{{ linkBusy ? t('account_view.making', 'Making…') : t('account_view.make_new_link', 'Make a New Link') }}</button></span>
		</div>

		<div v-if="account" class="pair">
			<section class="panel" aria-labelledby="account-heading">
				<header class="panel__header">
					<h2 id="account-heading">{{ t('account_view.account', 'Account') }}</h2>
					<p class="panel__hint">{{ mine ? t('account_view.yours_to_change', 'Yours to change') : (canEditDetails ? t('account_view.admins_to_change', 'An administrator\'s to change') : t('account_view.read_only', 'Read-only')) }}</p>
				</header>
				<div class="panel__body">
					<dl v-if="!editingDetails" class="kv">
						<div><dt>{{ t('account_view.username', 'Username') }}</dt><dd class="mono">{{ account.username }}</dd></div>
						<div><dt>{{ t('account_view.email', 'Email') }}</dt><dd :class="{ muted: !account.email }">{{ account.email ?? t('account_view.none_yet', 'None yet') }}</dd></div>
						<div><dt>{{ t('account_view.display_name', 'Display name') }}</dt><dd :class="{ muted: !account.name }">{{ account.name ?? (account.profile ? t('account_view.from_profile', '{name}, from the profile', { name: account.displayName }) : t('account_view.not_set', 'Not set')) }}</dd></div>
						<div><dt>{{ t('account_view.created_label', 'Created') }}</dt><dd>{{ when(account.created) }}</dd></div>
						<div><dt>{{ t('account_view.last_signed_in_label', 'Last signed in') }}</dt><dd>{{ account.tokenOnly ? t('account_view.never_token_only', 'Never: it\'s token-only') : when(account.lastLogin) }}</dd></div>
					</dl>
					<form v-else class="form-stack details-form" :aria-busy="detailsBusy" @submit.prevent="saveDetails" @keydown.esc="editingDetails = false">
						<p class="field">
							<label for="account-name">{{ t('account_view.display_name', 'Display name') }}</label>
							<input id="account-name" ref="nameInput" v-model="detailName" autocomplete="off" maxlength="100" :placeholder="account.profile?.title ?? account.username" :aria-invalid="detailsField === 'name' || undefined" :aria-describedby="detailsField === 'name' ? 'details-error' : 'account-name-help'">
							<span id="account-name-help" class="field__help">{{ mine ? t('account_view.display_name_help_mine', 'What the admin calls you. Left empty, it\'s your profile\'s title, else the username.') : t('account_view.display_name_help_other', 'What the admin calls them. Left empty, it\'s their profile\'s title, else the username.') }}</span>
						</p>
						<p class="field">
							<label for="account-email">{{ t('account_view.email', 'Email') }}</label>
							<input id="account-email" ref="emailInput" v-model="detailEmail" type="email" autocomplete="email" :required="!account.tokenOnly" :aria-invalid="detailsField === 'email' || undefined" :aria-describedby="detailsField === 'email' ? 'details-error' : 'account-email-help'">
							<span id="account-email-help" class="field__help">{{ account.tokenOnly ? t('account_view.email_optional', 'Optional for a token-only account.') : t('account_view.email_needed', 'Every account needs one.') }} {{ t('account_view.email_help', 'Blush sends no email; it\'s for the people who manage accounts.') }}</span>
						</p>
						<p v-if="detailsError" id="details-error" class="field__error" role="alert">{{ detailsError }}</p>
						<p class="submit-row submit-row--tight">
							<button type="submit" class="button button--primary button--small" :disabled="detailsBusy">{{ detailsBusy ? t('account_view.saving', 'Saving…') : t('common.save', 'Save') }}</button>
							<button type="button" class="button button--ghost button--small" :disabled="detailsBusy" @click="editingDetails = false">{{ t('common.cancel', 'Cancel') }}</button>
						</p>
					</form>
				</div>
				<div v-if="(mine || canEditDetails) && !editingDetails && !changing" class="panel__body panel__buttons">
					<button v-if="mine" ref="changeButton" type="button" class="button button--small" @click="startPasswordChange"><AdminIcon name="key-round" />{{ t('account_view.change_password', 'Change Password') }}</button>
					<button v-if="canEditDetails" type="button" class="button button--small" @click="startDetails()">{{ mine ? t('account_view.change_name_email', 'Change Name or Email') : t('account_view.edit_details', 'Edit Details') }}</button>
				</div>
				<div v-if="mine && changing" class="panel__body panel__body--ruled">
					<form class="form-stack details-form" :aria-busy="passwordBusy" @submit.prevent="changePassword" @keydown.esc="stopPasswordChange">
						<input type="text" class="visually-hidden" name="username" :value="account.username" autocomplete="username" tabindex="-1" aria-hidden="true" readonly>
						<p class="field">
							<label for="current-password">{{ t('account_view.current_password', 'Current password') }}</label>
							<input id="current-password" ref="currentInput" v-model="currentPassword" type="password" autocomplete="current-password" required :aria-invalid="passwordField === 'current' || undefined" :aria-describedby="passwordField === 'current' ? 'password-error' : undefined">
						</p>
						<p class="field">
							<label for="new-password">{{ t('account_view.new_password', 'New password') }}</label>
							<input id="new-password" ref="newInput" v-model="newPassword" type="password" autocomplete="new-password" required :aria-invalid="passwordField === 'password' || undefined" :aria-describedby="passwordField === 'password' ? 'password-error' : 'new-password-help'">
							<span id="new-password-help" class="field__help">{{ t('account_view.new_password_help', 'You\'ll stay signed in here and be signed out on every other device.') }}</span>
						</p>
						<p v-if="passwordError" id="password-error" class="notice notice--error" role="alert">{{ passwordError }}</p>
						<p class="submit-row submit-row--tight">
							<button type="submit" class="button button--primary button--small" :disabled="passwordBusy">{{ passwordBusy ? t('account_view.changing', 'Changing…') : t('account_view.change_password', 'Change Password') }}</button>
							<button type="button" class="button button--ghost button--small" :disabled="passwordBusy" @click="stopPasswordChange">{{ t('common.cancel', 'Cancel') }}</button>
						</p>
					</form>
				</div>
				<p class="panel__note">
					<template v-if="account.name">{{ mine ? t('account_view.name_note_mine', 'Your display name is what the admin calls you.') : t('account_view.name_note_other', 'Their display name is what the admin calls them.') }}<template v-if="account.profile"> {{ mine ? t('account_view.on_site_mine', 'On the site, you\'re {title}, the profile\'s title.', { title: account.profile.title || account.profile.slug }) : t('account_view.on_site_other', 'On the site, they\'re {title}, the profile\'s title.', { title: account.profile.title || account.profile.slug }) }}</template></template>
					<template v-else-if="account.profile">{{ mine ? t('account_view.no_name_mine', 'With no display name, the admin uses your profile\'s title: {name}.', { name: account.displayName }) : t('account_view.no_name_other', 'With no display name, the admin uses their profile\'s title: {name}.', { name: account.displayName }) }}</template>
					<template v-else>{{ t('account_view.no_name_no_profile', 'With no display name or profile, the admin uses the username.') }}</template>
				</p>
			</section>

			<section class="panel" aria-labelledby="roles-heading">
				<header class="panel__header">
					<h2 id="roles-heading">{{ t('account_view.roles', 'Roles') }}</h2>
					<p v-if="!rolesChanged" class="panel__hint">{{ t('account_view.roles_hint', 'An account can hold more than one') }}</p>
					<div v-if="editable && rolesChanged" class="panel__actions">
						<button type="button" class="button button--small" :disabled="rolesBusy" @click="discardRoles">{{ t('account_view.discard', 'Discard') }}</button>
						<button type="button" class="button button--primary button--small" :disabled="rolesBusy" @click="saveRoles">{{ rolesBusy ? t('account_view.saving', 'Saving…') : t('account_view.save_roles', 'Save Roles') }}</button>
					</div>
				</header>
				<div class="panel__body">
					<RoleChecks v-model="held" :roles="roleList" id-prefix="role-" :disabled="!editable" :described-by="rolesError ? 'roles-error' : undefined" />
					<p v-if="rolesError" id="roles-error" class="field__error" role="alert">{{ rolesError }}</p>
				</div>
				<p class="panel__note">{{ rolesNote }}<template v-if="can('accounts.view')"> <MessageText :text="t('account_view.roles_link_note', 'What each role allows is on {link}.')"><template #link><RouterLink class="lnk" :to="{ name: 'roles' }">{{ t('account_view.roles', 'Roles') }}</RouterLink></template></MessageText></template></p>
			</section>
		</div>

		<section v-if="account && profileType" class="panel" aria-labelledby="public-heading">
			<header class="panel__header">
				<h2 id="public-heading">{{ t('account_view.public_profile', 'Public Profile') }}</h2>
				<p class="panel__hint">{{ mine ? t('account_view.public_hint_mine', 'Your name and bio on the site') : t('account_view.public_hint_other', 'How they appear on the site') }}</p>
			</header>
			<div class="panel__body">
				<div v-if="account.profile" class="link-box">
					<AdminAvatar class="avatar--large" :name="account.profile.title || account.profile.slug" :image="account.avatar" />
					<span class="link-box__text">
						<RouterLink v-if="canOpenProfile" class="link-box__name lnk" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
						<strong v-else class="link-box__name">{{ account.profile.title || account.profile.slug }}</strong>
						<span v-if="account.profile.url" class="link-box__path">{{ account.profile.url }}</span>
						<span class="link-box__meta">
							<StatusPill :status="account.profile.status" />
							<span>· {{ account.profile.status === 'published' ? t('account_view.bylines', '{count, plural, one {# byline} other {# bylines} }', { count: account.profile.uses }) : t('account_view.nothing_answers', 'nothing answers at this address yet') }}</span>
						</span>
					</span>
					<span class="link-box__buttons">
						<RouterLink v-if="canOpenProfile" class="button button--small" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ t('account_view.open_profile_button', 'Open Profile') }}</RouterLink>
						<a v-if="profileUrl" class="button button--small" :href="profileUrl" target="_blank" rel="noopener"><AdminIcon name="external-link" />{{ t('account_view.view', 'View') }}<span class="visually-hidden"> {{ t('account_view.new_tab', '(new tab)') }}</span></a>
						<button v-if="account.profile.status === 'draft' && profileType !== null && canType(profileType, 'publish')" type="button" class="button button--primary button--small" :disabled="profileBusy" @click="publishProfile">{{ t('account_view.publish', 'Publish') }}</button>
						<button v-if="linking" type="button" class="button button--small" :disabled="profileBusy" @click="unlinkProfile"><AdminIcon name="unlink" />{{ t('account_view.unlink', 'Unlink') }}</button>
					</span>
				</div>
				<div v-else class="link-box link-box--blank">
					<span class="avatar avatar--large avatar--guest" aria-hidden="true"><AdminIcon name="user-round" /></span>
					<span class="link-box__text">
						<strong class="link-box__name">{{ t('account_view.no_public_profile', 'No public profile') }}</strong>
						<span class="link-box__meta">{{ mine ? t('account_view.not_on_site_mine', 'You don\'t appear on the site. Entries you write show no byline until a profile is linked.') : t('account_view.not_on_site_other', '{name} doesn\'t appear on the site. Entries they write show no byline until a profile is linked.', { name: account.displayName }) }}</span>
					</span>
					<span v-if="linking" class="link-box__buttons">
						<button type="button" class="button button--small" @click="startLink"><AdminIcon name="link" />{{ t('account_view.link_existing', 'Link an Existing One') }}</button>
						<button v-if="profileType !== null && canType(profileType, 'create')" type="button" class="button button--primary button--small" @click="startCreate">{{ t('account_view.create_one', 'Create One') }}</button>
					</span>
				</div>
				<p v-if="profileError && profileMode === ''" class="field__error" role="alert">{{ profileError }}</p>
			</div>
			<p v-if="account.profile && linking" class="panel__note"><MessageText :text="t('account_view.unlink_note', 'Unlinking leaves the profile and its {bylines} in place, as a guest profile.')"><template #bylines><strong>{{ t('account_view.bylines', '{count, plural, one {# byline} other {# bylines} }', { count: account.profile.uses }) }}</strong></template></MessageText></p>
			<p v-else-if="!account.profile && linking" class="panel__note"><MessageText :text="t('account_view.create_note', '{create} makes a draft profile with the name you give, linked to this account, and opens it: the common case, and why an account and a profile being separate costs almost nothing.')"><template #create><strong>{{ t('account_view.create_note_lead', 'Create one') }}</strong></template></MessageText></p>
		</section>

		<AccountTokens v-if="account && (mine || can('accounts.edit'))" :account="account" :mine="mine" />

		<AccountPreferences v-if="account && mine" />

		<AccountLanguage v-if="account && mine" />

		<PickModal v-if="account" v-model:pick="pick" :open="profileMode === 'link'" :noun="t('account_view.profile_noun', 'Profile')" :items="linkItems" :none="t('account_view.pick_none', 'Every profile already belongs to an account. Create a new one instead, and it\'s linked as it\'s made.')" :busy="profileBusy" :error="profileError" @close="profileMode = ''" @confirm="linkProfile">
			{{ mine ? t('account_view.pick_text_mine', 'Only profiles with no account are listed: a profile belongs to at most one. Entries crediting it become yours.') : t('account_view.pick_text_other', 'Only profiles with no account are listed: a profile belongs to at most one. Entries crediting it become theirs.') }}
			<template v-if="profileType !== null && canType(profileType, 'create')" #empty>
				<button type="button" class="button button--primary" @click="startCreate">{{ t('account_view.create_a_profile', 'Create a Profile') }}</button>
			</template>
		</PickModal>

		<AdminModal v-if="account" :open="profileMode === 'create'" :title="t('account_view.create_a_profile', 'Create a Profile')" @close="profileMode = ''">
			<p>{{ mine ? t('account_view.create_intro_mine', 'It starts as a draft, linked to your account, so nothing is public until you publish it.') : t('account_view.create_intro_other', 'It starts as a draft, linked to this account, so nothing is public until you publish it.') }}</p>
			<form id="create-profile" class="form-stack create-profile" @submit.prevent="createProfile">
				<p class="field">
					<label for="create-profile-name">{{ t('account_view.profile_display_name', 'Display name') }}</label>
					<input id="create-profile-name" v-model="newName" autocomplete="off" maxlength="100" :placeholder="t('account_view.profile_name_placeholder', 'Their name as readers should see it')" autofocus aria-describedby="create-profile-name-help">
					<span id="create-profile-name-help" class="field__help">{{ t('account_view.profile_name_help', 'The name on every byline: the profile\'s title.') }}</span>
				</p>
				<p class="field">
					<label for="create-profile-slug">{{ t('account_view.slug', 'Slug') }}</label>
					<input id="create-profile-slug" v-model="newSlugTyped" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" :placeholder="slugOf(newName)" aria-describedby="create-profile-slug-help">
					<span id="create-profile-slug-help" class="field__help">{{ t('account_view.slug_help', 'Every archive address for this person uses it. Left empty, it follows the name.') }}</span>
				</p>
			</form>
			<p v-if="profileError" class="field__error" role="alert">{{ profileError }}</p>
			<template #footer>
				<button type="button" class="button" @click="profileMode = ''">{{ t('common.cancel', 'Cancel') }}</button>
				<button type="submit" form="create-profile" class="button button--primary" :disabled="profileBusy || newSlug === ''">{{ profileBusy ? t('account_view.creating', 'Creating…') : t('account_view.create_the_profile', 'Create the Profile') }}</button>
			</template>
		</AdminModal>
	</div>
</template>

<style scoped>
.create-profile {
	margin-top: var(--s-4);
}

.details-form {
	max-width: 24rem;
}
</style>
