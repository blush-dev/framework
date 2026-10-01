<script setup lang="ts">
/**
 * A new account (D-312; the prototype's Invite, as its own screen like
 * every New): a username, its roles, and optionally a name (D-322) and
 * an author. Blush
 * sends no email, so **Create account** makes the account with a link
 * for choosing a password, and opens the account's screen with the link
 * to copy and send. The link is shown that once.
 */

import { computed, nextTick, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AuthorField from '../components/AuthorField.vue';
import RoleChecks from '../components/RoleChecks.vue';
import { ApiError } from '../api';
import { createAccount, freshLink, loadRoles, type RoleInfo } from '../people';
import { toast } from '../toast';

const router   = useRouter();
const roles    = ref<RoleInfo[]>([]);
const username = ref('');
const chosen   = ref<string[]>([]);
const name     = ref('');
const author   = ref('');
const busy     = ref(false);
const error    = ref('');
const field    = ref<'username' | 'name' | 'roles' | 'author' | null>(null);
const loadFail = ref('');

const usernameInput = ref<HTMLInputElement | null>(null);
const nameInput     = ref<HTMLInputElement | null>(null);

loadRoles().then((list) => {
	roles.value  = list.roles;
	// Author, as the prototype starts, or the first role you can give.
	chosen.value = [list.roles.find((role) => role.name === 'author' && role.grantable)?.name ?? list.roles.find((role) => role.grantable)?.name ?? ''].filter(Boolean);
}, (caught: unknown) => {
	loadFail.value = caught instanceof ApiError ? caught.message : 'The roles couldn\'t be loaded.';
});

const usernameProblem = computed(() => username.value !== '' && !/^[a-z0-9][a-z0-9._-]{0,63}$/.test(username.value.toLowerCase())
	? 'Use lowercase letters, digits, “.”, “_”, and “-”, starting with a letter or digit.'
	: '');

async function submit(): Promise<void> {
	busy.value  = true;
	error.value = '';
	field.value = null;

	try {
		const answer = await createAccount(username.value.trim().toLowerCase(), chosen.value, author.value === '' ? null : author.value, name.value.trim() === '' ? null : name.value);

		freshLink.value = { username: answer.account.username, link: answer.link };
		toast(`Created ${answer.account.displayName}`);
		await router.push({ name: 'account', params: { username: answer.account.username } });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be created.';
		field.value = caught instanceof ApiError && (caught.field === 'username' || caught.field === 'name' || caught.field === 'roles' || caught.field === 'author') ? caught.field : null;

		if (field.value === 'username' || field.value === 'name' || field.value === null) {
			await nextTick();
			(field.value === 'name' ? nameInput : usernameInput).value?.focus();
		}
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">New Account</h1>
			<p class="page-header__hint">Someone who can sign in. They choose their own password with a link you send them.</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'accounts' }">Cancel</RouterLink>
		</div>
	</header>

	<p v-if="loadFail" class="notice notice--error" role="alert">{{ loadFail }}</p>

	<form v-else class="new-account" @submit.prevent="submit">
		<section class="panel" aria-labelledby="account-heading">
			<header class="panel__header">
				<h2 id="account-heading">Account</h2>
			</header>
			<div class="panel__body new-account__fields">
				<div class="field">
					<label for="account-username">Username</label>
					<input id="account-username" ref="usernameInput" v-model="username" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" required :aria-invalid="usernameProblem || field === 'username' ? 'true' : undefined" aria-describedby="account-username-help">
					<p v-if="usernameProblem" id="account-username-help" class="field__error">{{ usernameProblem }}</p>
					<p v-else-if="field === 'username'" id="account-username-help" class="field__error">{{ error }}</p>
					<p v-else id="account-username-help" class="field__help">What they sign in with. Fixed once it's made.</p>
				</div>
				<div class="field">
					<label for="account-name">Name</label>
					<input id="account-name" ref="nameInput" v-model="name" autocomplete="off" maxlength="100" :aria-invalid="field === 'name' ? 'true' : undefined" aria-describedby="account-name-help">
					<p v-if="field === 'name'" id="account-name-help" class="field__error">{{ error }}</p>
					<p v-else id="account-name-help" class="field__help">What the admin calls them. Optional; they can change it on their profile.</p>
				</div>
				<div class="field">
					<label for="account-author">Author</label>
					<AuthorField id="account-author" v-model="author" described-by="account-author-help" :invalid="field === 'author'" />
					<p v-if="field === 'author'" id="account-author-help" class="field__error">{{ error }}</p>
					<p v-else id="account-author-help" class="field__help">The author entry that's their public name: entries crediting it are theirs to edit. Leave it empty for none.</p>
				</div>
			</div>
		</section>

		<section class="panel" aria-labelledby="roles-heading">
			<header class="panel__header">
				<h2 id="roles-heading">Roles</h2>
				<p class="panel__hint">An account can hold more than one</p>
			</header>
			<div class="panel__body">
				<RoleChecks v-model="chosen" :roles="roles" id-prefix="account-role-" :invalid="field === 'roles'" :described-by="field === 'roles' ? 'account-roles-error' : undefined" />
				<p v-if="field === 'roles'" id="account-roles-error" class="field__error">{{ error }}</p>
			</div>
		</section>

		<div class="new-account__save">
			<p v-if="error && field === null" class="field__error" role="alert">{{ error }}</p>
			<p v-else class="visually-hidden" role="alert">{{ error }}</p>
			<button type="submit" class="button button--primary" :disabled="busy || username === '' || usernameProblem !== '' || chosen.length === 0">{{ busy ? 'Creating…' : 'Create account' }}</button>
			<p class="field__help">Next, you'll get a link to send them. It works once, for a week.</p>
		</div>
	</form>
</template>

<style scoped>
.new-account {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.new-account__fields {
	display: grid;
	gap: var(--s-4);
}

.new-account__fields > * + * {
	margin-top: 0;
}

.new-account__save {
	display: flex;
	flex-wrap: wrap;
	grid-column: 1 / -1;
	align-items: center;
	gap: var(--s-2) var(--s-3);
}

.new-account__save .field__error {
	flex-basis: 100%;
	margin: 0;
}

@media (width <= 1100px) {
	.new-account {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
