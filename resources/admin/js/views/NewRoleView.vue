<script setup lang="ts">
/**
 * A new role (D-312), its own screen like every New: a name, a key that
 * follows the name until it's typed, what it's for, and its
 * capabilities, in the role screen's sections (D-359). **Duplicate** on
 * a role opens this with that role's capabilities ticked
 * (`?from={name}`), which is how a variation of a built-in starts. You can't give a role a capability you don't have.
 * It's kept in `storage/roles.json`, outside git.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import CapabilitySections from '../components/CapabilitySections.vue';
import { useAction } from '../action';
import { ApiError, errorMessage } from '../api';
import { createRole, loadRoles, roleKeyOf, type RoleList } from '../people';
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
const field        = ref<string | null>(null);
const loadFail     = ref('');

const { busy, error, run } = useAction();

const source = computed(() => typeof route.query.from === 'string' ? list.value?.roles.find((role) => role.name === route.query.from) ?? null : null);

loadRoles().then((answer) => {
	list.value = answer;

	const from = source.value;

	if (from !== null) {
		label.value        = `${from.label} (copy)`;
		description.value  = from.description;
		capabilities.value = from.capabilities.filter((name) => name !== answer.all && can(name));
	}
}, (caught: unknown) => {
	loadFail.value = errorMessage(caught, 'The roles couldn\'t be loaded.');
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
	field.value = null;

	await run('The role couldn\'t be created.', async () => {
		const role = await createRole({ name: key.value, label: label.value, description: description.value, capabilities: capabilities.value });

		toast(`Created the ${role.label} role`);
		await router.push({ name: 'role', params: { name: role.name } });
	}, (caught) => {
		field.value = caught instanceof ApiError ? caught.field : null;
	});
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

	<form v-else-if="list" class="form-stack" @submit.prevent="submit">
		<section class="panel" aria-labelledby="about-heading">
			<header class="panel__header">
				<h2 id="about-heading">About</h2>
			</header>
			<div class="panel__body field-pair new-role__fields">
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
				<div class="field new-role__description">
					<label for="role-description">Description</label>
					<input id="role-description" v-model="description" autocomplete="off" aria-describedby="role-description-help">
					<p id="role-description-help" class="field__help">What it's for, in a line. Shown where roles are given.</p>
				</div>
			</div>
		</section>

		<CapabilitySections v-model="capabilities" :capabilities="list.capabilities" :types="list.types" />

		<div class="submit-row submit-row--tight">
			<p v-if="error && field !== 'name'" class="field__error" role="alert">{{ error }}</p>
			<button type="submit" class="button button--primary" :disabled="busy || label.trim() === '' || key === '' || keyProblem !== ''">{{ busy ? 'Creating…' : 'Create role' }}</button>
		</div>
	</form>
</template>

<style scoped>
/* Fields top-aligned, whatever help text each one has. */
.new-role__fields {
	align-items: start;
}

/* Every input the same height, whatever its font. */
.new-role__fields input {
	height: var(--ctl);
}

.new-role__description {
	grid-column: 1 / -1;
}

.new-role__fields > * + * {
	margin-top: 0;
}
</style>
