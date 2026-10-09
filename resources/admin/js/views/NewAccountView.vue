<script setup lang="ts">
/**
 * A new account (D-312; the prototype's Invite, as its own screen like
 * every New; the profiles sketch's form, D-369): a username, an email
 * address (every account needs one, D-370), an optional display name
 * (D-322), its roles, and a profile: none, a new one (a display name and
 * slug, made as a draft and linked), or an existing one (D-356). The
 * note under the profile says what the admin will call the account: its
 * display name, else the profile's title, else its username.
 *
 * Blush sends no email, so **Create account** makes the account with a
 * link for choosing a password, and opens the account's screen with the
 * link to copy and send. The link is shown that once.
 */

import { computed, nextTick, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import ProfilePicker from '../components/ProfilePicker.vue';
import RoleChecks from '../components/RoleChecks.vue';
import { useAction } from '../action';
import { ApiError, errorMessage } from '../api';
import { createAccount, freshLink, loadRoles, MEMBER, NEW_PROFILE, type LinkableProfile, type RoleInfo } from '../people';
import { slugOf } from '../references';
import { can, canType } from '../session';
import { toast } from '../toast';
import { profileType } from '../types';

const router   = useRouter();
const roles    = ref<RoleInfo[]>([]);
const username = ref('');
const email    = ref('');
const name     = ref('');
const chosen   = ref<string[]>([]);
const author   = ref('');
const newName  = ref('');
const newSlug  = ref('');
const profiles = ref<LinkableProfile[]>([]);
const field    = ref<'username' | 'email' | 'accountName' | 'roles' | 'author' | 'name' | 'slug' | null>(null);
const loadFail = ref('');

const { busy, error, run } = useAction();

const usernameInput = ref<HTMLInputElement | null>(null);
const emailInput    = ref<HTMLInputElement | null>(null);
const nameInput     = ref<HTMLInputElement | null>(null);
const slugInput     = ref<HTMLInputElement | null>(null);

loadRoles().then((list) => {
	roles.value  = list.roles;
	// Member, always (D-365): giving more is a choice, and needs
	// `accounts.roles`.
	chosen.value = [MEMBER];
}, (caught: unknown) => {
	loadFail.value = errorMessage(caught, 'The roles couldn\'t be loaded.');
});

const usernameProblem = computed(() => username.value !== '' && !/^[a-z0-9][a-z0-9._-]{0,63}$/.test(username.value.toLowerCase())
	? 'Use lowercase letters, digits, “.”, “_”, and “-”, starting with a letter or digit.'
	: '');

const creating = computed(() => author.value === NEW_PROFILE);
const slug     = computed(() => slugOf(newSlug.value.trim() === '' ? newName.value : newSlug.value));

// What the account will be called, which follows the typing.
const willBe = computed(() => {
	if (name.value.trim() !== '') {
		return name.value.trim();
	}

	if (creating.value) {
		return newName.value.trim() === '' ? null : newName.value.trim();
	}

	return author.value === '' ? null : (profiles.value.find((item) => item.slug === author.value)?.title ?? null);
});

async function submit(): Promise<void> {
	error.value = '';
	field.value = null;

	if (creating.value && newName.value.trim() === '') {
		error.value = 'A profile needs a display name.';
		field.value = 'name';
		nameInput.value?.focus();

		return;
	}

	if (creating.value && profiles.value.some((item) => item.slug === slug.value)) {
		error.value = `There's already a profile at “${slug.value}”. Choose it from the list instead, or pick another slug.`;
		field.value = 'slug';
		slugInput.value?.focus();

		return;
	}

	await run('The account couldn\'t be created.', async () => {
		// A new profile is made with the account, a draft (D-668).
		const answer = await createAccount({
			username: username.value.trim().toLowerCase(),
			email: email.value.trim(),
			name: name.value.trim() === '' ? null : name.value,
			roles: chosen.value,
			author: creating.value ? slug.value : (author.value === '' ? null : author.value),
			...(creating.value ? { profileTitle: newName.value.trim() } : {})
		});
		const called = answer.account.displayName;

		freshLink.value = { username: answer.account.username, link: answer.link };

		toast(`Created ${called}`);
		await router.push({ name: 'account', params: { username: answer.account.username } });
	}, (caught) => {
		field.value = caught instanceof ApiError && (caught.field === 'username' || caught.field === 'email' || caught.field === 'roles' || caught.field === 'author') ? caught.field : (caught instanceof ApiError && caught.field === 'name' ? 'accountName' : null);

		if (field.value === 'username' || field.value === 'email' || field.value === null) {
			void nextTick(() => (field.value === 'email' ? emailInput : usernameInput).value?.focus());
		}
	});
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<RouterLink class="page-back" :to="{ name: 'accounts' }"><AdminIcon name="chevron-left" />All accounts</RouterLink>
			<div class="page-header__text">
				<h1 tabindex="-1">New Account</h1>
				<p class="page-header__hint">Someone who can sign in. They choose their own password from a link you send them.</p>
			</div>
		</header>

		<p v-if="loadFail" class="notice notice--error" role="alert">{{ loadFail }}</p>

		<form v-else class="people" @submit.prevent="submit">
			<p v-if="error && field !== null" class="notice notice--error"><AdminIcon name="triangle-alert" /><span class="notice__text">One field needs attention before this account can be made.</span></p>

			<div class="pair">
				<section class="panel" aria-labelledby="account-heading">
					<header class="panel__header">
						<h2 id="account-heading">Account</h2>
					</header>
					<div class="panel__body form-stack new-account__form">
						<div class="field">
							<label for="account-username">Username</label>
							<input id="account-username" ref="usernameInput" v-model="username" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" required :aria-invalid="usernameProblem || field === 'username' ? 'true' : undefined" aria-describedby="account-username-help">
							<p v-if="usernameProblem" id="account-username-help" class="field__error">{{ usernameProblem }}</p>
							<p v-else-if="field === 'username'" id="account-username-help" class="field__error">{{ error }}</p>
							<p v-else id="account-username-help" class="field__help">What they sign in with. Fixed once it's made.</p>
						</div>
						<div class="field">
							<label for="account-email">Email</label>
							<input id="account-email" ref="emailInput" v-model="email" type="email" autocomplete="off" required :aria-invalid="field === 'email' ? 'true' : undefined" aria-describedby="account-email-help">
							<p v-if="field === 'email'" id="account-email-help" class="field__error">{{ error }}</p>
							<p v-else id="account-email-help" class="field__help">Every account needs one. Blush sends no email, so the password link is still yours to send.</p>
						</div>
						<div class="field">
							<label for="account-name">Display name <span class="field__optional">Optional</span></label>
							<input id="account-name" v-model="name" autocomplete="off" maxlength="100" :aria-invalid="field === 'accountName' ? 'true' : undefined" aria-describedby="account-name-help">
							<p v-if="field === 'accountName'" id="account-name-help" class="field__error">{{ error }}</p>
							<p v-else id="account-name-help" class="field__help">What the admin calls them. Left empty, it's their profile's title, else the username.</p>
						</div>
						<div v-if="profileType" class="field">
							<label for="account-author">Profile</label>
							<ProfilePicker id="account-author" v-model="author" :create="canType(profileType, 'create')" described-by="account-author-help" :invalid="field === 'author'" @loaded="profiles = $event" />
							<p v-if="field === 'author'" id="account-author-help" class="field__error">{{ error }}</p>
							<div v-if="creating" class="sub-fields">
								<div class="field">
									<label for="account-profile-name">Display name on the site</label>
									<input id="account-profile-name" ref="nameInput" v-model="newName" autocomplete="off" maxlength="100" placeholder="Their name as readers should see it" :aria-invalid="field === 'name' ? 'true' : undefined" aria-describedby="account-profile-name-help">
									<p v-if="field === 'name'" id="account-profile-name-help" class="field__error">{{ error }}</p>
									<p v-else id="account-profile-name-help" class="field__help">The name on every byline: the profile's title.</p>
								</div>
								<div class="field">
									<label for="account-profile-slug">Slug</label>
									<input id="account-profile-slug" ref="slugInput" v-model="newSlug" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" :placeholder="slugOf(newName)" :aria-invalid="field === 'slug' ? 'true' : undefined" aria-describedby="account-profile-slug-help">
									<p v-if="field === 'slug'" id="account-profile-slug-help" class="field__error">{{ error }}</p>
									<p v-else id="account-profile-slug-help" class="field__help">Every archive address for this person uses it. Left empty, it follows the name.</p>
								</div>
							</div>
							<p class="will" aria-live="polite">
								<AdminIcon name="info" />
								<span v-if="willBe">The admin will call this account <strong>{{ willBe }}</strong>.</span>
								<span v-else-if="creating">Name the profile, and the admin calls this account by it unless it has a display name.</span>
								<span v-else>With no profile, this account has <strong>no presence</strong> on the site. You can link or create one later.</span>
							</p>
						</div>
					</div>
					<p class="panel__note">A profile belongs to at most one account, so those already linked can't be chosen.</p>
				</section>

				<section class="panel" aria-labelledby="roles-heading">
					<header class="panel__header">
						<h2 id="roles-heading">Roles</h2>
						<p class="panel__hint">{{ can('accounts.roles') ? 'An account can hold more than one' : 'New accounts are Members' }}</p>
					</header>
					<div class="panel__body">
						<p v-if="!can('accounts.roles')" class="field__help new-account__member">You can't give roles, so the account starts as a Member. Someone who can give roles can change that on its screen.</p>
						<RoleChecks v-model="chosen" :roles="roles" id-prefix="account-role-" :disabled="!can('accounts.roles')" :invalid="field === 'roles'" :described-by="field === 'roles' ? 'account-roles-error' : undefined" />
						<p v-if="field === 'roles'" id="account-roles-error" class="field__error">{{ error }}</p>
					</div>
					<p class="panel__note">Pick at least one. Roles can be changed later, from the account.</p>
				</section>
			</div>

			<div class="submit-row">
				<button type="submit" class="button button--primary" :disabled="busy || username === '' || email.trim() === '' || usernameProblem !== '' || chosen.length === 0">{{ busy ? 'Creating…' : 'Create Account' }}</button>
				<p class="submit-row__why">Next, you'll get a one-time link to send them. It works for a week.</p>
				<p v-if="error && field === null" class="field__error" role="alert">{{ error }}</p>
				<p v-else class="visually-hidden" role="alert">{{ error }}</p>
			</div>
		</form>
	</div>
</template>

<style scoped>
.new-account__form {
	max-width: 520px;
}

.new-account__member {
	margin-bottom: var(--s-3);
}

.field__optional {
	margin-left: var(--s-1);
	color: var(--fg-3);
	font-weight: 400;
}
</style>
