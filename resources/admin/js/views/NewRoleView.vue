<script setup lang="ts">
/**
 * A new role (D-312), its own screen like every New: a name, a key that
 * follows the name until it's typed, what it's for, and its
 * capabilities. **Duplicate** on a role opens this with that role's
 * capabilities ticked (`?from={name}`), which is how a variation of a
 * built-in starts. You can't give a role a capability you don't have.
 * It's kept in `storage/roles.json`, outside git.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import CapabilityChecks from '../components/CapabilityChecks.vue';
import { ApiError } from '../api';
import { createRole, grants, loadRoles, roleKeyOf, type RoleList } from '../people';
import { can } from '../session';
import { toast } from '../toast';

const route  = useRoute();
const router = useRouter();

const list         = ref<RoleList | null>(null);
const label        = ref('');
const key          = ref('');
const keyTouched   = ref(false);
const description  = ref('');
const capabilities = ref<string[]>([]);
const busy         = ref(false);
const error        = ref('');
const field        = ref<string | null>(null);
const loadFail     = ref('');

const source = computed(() => typeof route.query.from === 'string' ? list.value?.roles.find((role) => role.name === route.query.from) ?? null : null);

loadRoles().then((answer) => {
	list.value = answer;

	const from = source.value;

	if (from !== null) {
		label.value        = `${from.label} (copy)`;
		description.value  = from.description;
		capabilities.value = answer.capabilities.map((capability) => capability.name).filter((name) => grants(from, name, answer.all) && can(name));
	}
}, (caught: unknown) => {
	loadFail.value = caught instanceof ApiError ? caught.message : 'The roles couldn\'t be loaded.';
});

watch(label, (value) => {
	if (!keyTouched.value) {
		key.value = roleKeyOf(value);
	}
});

const keyProblem = computed(() => {
	if (key.value === '') {
		return label.value === '' ? '' : 'Give it a key.';
	}

	if (!/^[a-z][a-z0-9_-]*$/.test(key.value)) {
		return 'A key starts with a lowercase letter and uses lowercase letters, digits, “_”, and “-”.';
	}

	return list.value?.roles.some((role) => role.name === key.value) ? `“${key.value}” is already a role. Pick another key.` : '';
});

async function submit(): Promise<void> {
	busy.value  = true;
	error.value = '';
	field.value = null;

	try {
		const role = await createRole({ name: key.value, label: label.value, description: description.value, capabilities: capabilities.value });

		toast(`Created the ${role.label} role`);
		await router.push({ name: 'role', params: { name: role.name } });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The role couldn\'t be created.';
		field.value = caught instanceof ApiError ? caught.field : null;
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">New Role</h1>
			<p class="page-header__hint">{{ source ? `Starting from ${source.label}'s capabilities` : 'A named set of capabilities to give accounts' }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="source ? { name: 'role', params: { name: source.name } } : { name: 'roles' }">Cancel</RouterLink>
		</div>
	</header>

	<p v-if="loadFail" class="notice notice--error" role="alert">{{ loadFail }}</p>

	<form v-else-if="list" class="new-role" @submit.prevent="submit">
		<section class="panel" aria-labelledby="about-heading">
			<header class="panel__header">
				<h2 id="about-heading">About</h2>
			</header>
			<div class="panel__body new-role__fields">
				<div class="field">
					<label for="role-label">Name</label>
					<input id="role-label" v-model="label" autocomplete="off" required placeholder="Reviewer">
				</div>
				<div class="field">
					<label for="role-key">Key</label>
					<input id="role-key" v-model="key" class="mono" autocomplete="off" spellcheck="false" required :aria-invalid="keyProblem || field === 'name' ? 'true' : undefined" aria-describedby="role-key-help" @input="keyTouched = true">
					<p v-if="keyProblem" id="role-key-help" class="field__error">{{ keyProblem }}</p>
					<p v-else-if="field === 'name'" id="role-key-help" class="field__error">{{ error }}</p>
					<p v-else id="role-key-help" class="field__help">Names the role in account files. Fixed once it's made.</p>
				</div>
				<div class="field">
					<label for="role-description">Description</label>
					<input id="role-description" v-model="description" autocomplete="off" aria-describedby="role-description-help">
					<p id="role-description-help" class="field__help">What it's for, in a line. Shown where roles are given.</p>
				</div>
			</div>
		</section>

		<section class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">Capabilities</h2>
				<p class="panel__hint">{{ capabilities.length }} of {{ list.capabilities.length }}</p>
			</header>
			<CapabilityChecks v-model="capabilities" :capabilities="list.capabilities" id-prefix="capability-" />
		</section>

		<div class="new-role__save">
			<p v-if="error && field !== 'name'" class="field__error" role="alert">{{ error }}</p>
			<button type="submit" class="button button--primary" :disabled="busy || label.trim() === '' || key === '' || keyProblem !== ''">{{ busy ? 'Creating…' : 'Create role' }}</button>
		</div>
	</form>
</template>

<style scoped>
.new-role {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
	align-items: start;
	gap: var(--s-4);
}

.new-role__fields {
	display: grid;
	gap: var(--s-4);
}

.new-role__fields > * + * {
	margin-top: 0;
}

.new-role__save {
	display: flex;
	flex-wrap: wrap;
	grid-column: 1 / -1;
	align-items: center;
	gap: var(--s-2);
}

.new-role__save .field__error {
	flex-basis: 100%;
	margin: 0;
}

@media (width <= 1100px) {
	.new-role {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
