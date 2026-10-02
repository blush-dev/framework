<script setup lang="ts">
/**
 * One role (D-249): what it is, who holds it, and each capability it
 * grants or doesn't, in groups. A role you may change (D-312) is edited
 * here: a custom role's name, description, and capabilities, or a
 * built-in's capabilities, saved with **Save** or put back with
 * **Revert**; leaving with changes unsaved asks first. Its Danger Zone
 * deletes a custom role no account holds, or resets a changed built-in.
 * **Duplicate** starts a new role from this one's capabilities.
 *
 * The administrator always has everything, roles from `config/auth.php`
 * are changed there, and a role that can do things you can't isn't
 * yours to change; those are shown read-only.
 */

import { computed, ref, watch } from 'vue';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import CapabilityChecks from '../components/CapabilityChecks.vue';
import { ApiError } from '../api';
import { plural } from '../format';
import { capabilityGroups, deleteRole, grants, initials, loadRoles, originOf, updateRole, type RoleInfo, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { toast } from '../toast';

const route  = useRoute();
const router = useRouter();
const list   = ref<RoleList | null>(null);
const error  = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The role couldn\'t be loaded.';
});

const role   = computed(() => list.value?.roles.find((item) => item.name === route.params.name));
const all    = computed(() => list.value?.all ?? '*');
const total  = computed(() => list.value?.capabilities.length ?? 0);
const count  = computed(() => role.value === undefined ? 0 : list.value?.capabilities.filter((capability) => grants(role.value!, capability.name, all.value)).length ?? 0);
const groups = computed(() => capabilityGroups(list.value?.capabilities ?? []));
const custom = computed(() => role.value?.origin === 'custom');

watch(role, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });

// Why a role is read-only.
const lockedBecause = computed(() => {
	const value = role.value;

	if (value === undefined || value.editable) {
		return '';
	}

	if (value.capabilities.includes(all.value)) {
		return 'The administrator always has every capability, so it can\'t change.';
	}

	return value.origin === 'config'
		? 'This role is defined in config/auth.php, so it\'s changed there.'
		: 'This role can do things you can\'t, so you can\'t change it.';
});

// The form: what's on screen, and what was loaded.
const label        = ref('');
const description  = ref('');
const capabilities = ref<string[]>([]);
const saving       = ref(false);
const failure      = ref('');

function reset(value: RoleInfo | undefined): void {
	label.value        = value?.label ?? '';
	description.value  = value?.description ?? '';
	capabilities.value = value ? [...value.capabilities] : [];
	failure.value      = '';
}

watch(role, reset, { immediate: true });

const sameSet = (one: string[], two: string[]): boolean => one.length === two.length && one.every((item) => two.includes(item));

const changes = computed(() => {
	const value = role.value;

	if (value === undefined || !value.editable) {
		return {};
	}

	return {
		...(custom.value && label.value.trim() !== value.label ? { label: label.value } : {}),
		...(custom.value && description.value.trim() !== value.description ? { description: description.value } : {}),
		...(sameSet(capabilities.value, value.capabilities) ? {} : { capabilities: capabilities.value })
	};
});

const changed = computed(() => Object.keys(changes.value).length > 0);

function replace(changedRole: RoleInfo): void {
	if (list.value !== null) {
		list.value = { ...list.value, roles: list.value.roles.map((item) => item.name === changedRole.name ? changedRole : item) };
	}
}

async function save(): Promise<void> {
	const value = role.value;

	if (value === undefined || !changed.value || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const saved = await updateRole(value.name, changes.value);

		replace(saved);
		toast(`Saved ${saved.label}`);
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The role couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

// The Danger Zone: delete a custom role, or reset a changed built-in.
const removal = ref('');

async function remove(): Promise<void> {
	const value = role.value;

	if (value === undefined) {
		return;
	}

	const question = custom.value
		? `Delete the ${value.label} role?`
		: `Reset ${value.label} to the capabilities it's built with? Your changes to it are lost.`;

	if (!window.confirm(question)) {
		return;
	}

	removal.value = '';

	try {
		const after = await deleteRole(value.name);

		if (after === null) {
			// Gone, so nothing's left unsaved.
			if (list.value !== null) {
				list.value = { ...list.value, roles: list.value.roles.filter((item) => item.name !== value.name) };
			}

			toast(`Deleted the ${value.label} role`);
			await router.push({ name: 'roles' });
		} else {
			replace(after);
			toast(`Reset ${after.label}`);
		}
	} catch (caught) {
		removal.value = caught instanceof ApiError ? caught.message : 'The role couldn\'t be changed.';
	}
}

onBeforeRouteLeave(() => !changed.value || window.confirm('Leave without saving? Your changes will be lost.'));
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ role?.label ?? 'Role' }}</h1>
			<p v-if="role" class="page-header__hint">
				{{ role.capabilities.includes(all) ? 'Every capability' : `${count} of ${total} capabilities` }} · held by {{ plural(role.accounts.length, 'account') }}
			</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="role && !role.capabilities.includes(all)" class="button" :to="{ name: 'role-new', query: { from: role.name } }"><AdminIcon name="copy" />Duplicate</RouterLink>
			<RouterLink class="button" :to="{ name: 'roles' }"><AdminIcon name="arrow-left" />All roles</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="list && !role" class="notice notice--error" role="alert">There's no “{{ route.params.name }}” role.</p>
	<p v-if="lockedBecause" class="notice notice--warn"><span>{{ lockedBecause }}</span></p>

	<form v-if="role" class="detail" @submit.prevent="save">
		<div class="detail__side">
			<section class="panel" aria-labelledby="about-heading">
				<header class="panel__header">
					<h2 id="about-heading">About</h2>
					<p v-if="!role.editable" class="panel__hint">Read-only</p>
				</header>
				<div class="panel__body about">
					<template v-if="role.editable && custom">
						<div class="field">
							<label for="role-label">Name</label>
							<input id="role-label" v-model="label" autocomplete="off" required>
						</div>
						<div class="field">
							<label for="role-description">Description</label>
							<input id="role-description" v-model="description" autocomplete="off">
						</div>
					</template>
					<p v-else-if="role.description" class="about__description">{{ role.description }}</p>
					<dl class="facts">
						<div><dt>Key</dt><dd class="mono">{{ role.name }}</dd></div>
						<div><dt>Capabilities</dt><dd class="mono">{{ role.capabilities.includes(all) ? 'all' : `${count} / ${total}` }}</dd></div>
						<div><dt>Source</dt><dd>{{ originOf(role) }}</dd></div>
					</dl>
					<p v-if="role.editable && !custom" class="field__help">A built-in role keeps its name; its capabilities can change, and reset.</p>
				</div>
			</section>

			<section class="panel" aria-labelledby="held-heading">
				<header class="panel__header">
					<h2 id="held-heading">Held By</h2>
					<p class="panel__hint">{{ role.accounts.length ? plural(role.accounts.length, 'account') : 'Nobody' }}</p>
				</header>
				<ul v-if="role.accounts.length" class="panel__body people">
					<li v-for="holder in role.accounts" :key="holder.username">
						<span class="people__avatar" aria-hidden="true">{{ initials(holder.displayName) }}</span>
						<RouterLink :to="{ name: 'account', params: { username: holder.username } }">{{ holder.displayName }}</RouterLink>
					</li>
				</ul>
				<div v-else class="empty">
					<AdminIcon name="key-round" />
					<p class="empty__heading">No Accounts Have This Role</p>
					<p class="empty__text">Give it to an account on that account's screen.</p>
					<RouterLink class="button" :to="{ name: 'accounts' }">Go to accounts</RouterLink>
				</div>
			</section>

			<section v-if="role.editable && role.origin !== 'built-in'" class="panel" aria-labelledby="danger-heading">
				<header class="panel__header">
					<h2 id="danger-heading">Danger Zone</h2>
				</header>
				<div class="panel__body danger">
					<button type="button" class="button button--danger button--small" @click="remove">
						<template v-if="custom"><AdminIcon name="x" />Delete this role</template>
						<template v-else><AdminIcon name="refresh-cw" />Reset to built-in</template>
					</button>
					<p v-if="removal" class="field__error" role="alert">{{ removal }}</p>
					<p class="field__help">{{ custom ? 'Accounts that hold it must give it up first.' : `Gives ${role.label} back the capabilities it's built with.` }}</p>
				</div>
			</section>
		</div>

		<div class="detail__main">
			<section class="panel" aria-labelledby="capabilities-heading">
				<header class="panel__header">
					<h2 id="capabilities-heading">Capabilities</h2>
					<p class="panel__hint">{{ role.capabilities.includes(all) ? 'All granted' : `${role.editable ? capabilities.length : count} granted` }}</p>
				</header>
				<CapabilityChecks v-if="role.editable" v-model="capabilities" :capabilities="list?.capabilities ?? []" id-prefix="capability-" />
				<template v-else>
					<div v-for="group in groups" :key="group.name" class="capabilities">
						<h3>{{ group.name }}</h3>
						<ul>
							<li v-for="capability in group.capabilities" :key="capability.name" :class="grants(role, capability.name, all) ? 'is-on' : 'is-off'">
								<AdminIcon :name="grants(role, capability.name, all) ? 'circle-check' : 'x'" />
								<span>{{ capability.label }}<span class="visually-hidden">: {{ grants(role, capability.name, all) ? 'granted' : 'not granted' }}</span></span>
								<code>{{ capability.name }}</code>
							</li>
						</ul>
					</div>
				</template>
			</section>

			<div v-if="role.editable" class="save">
				<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
				<button type="submit" class="button button--primary" :disabled="!changed || saving">{{ saving ? 'Saving…' : 'Save' }}</button>
				<button v-if="changed" type="button" class="button button--ghost" :disabled="saving" @click="reset(role)">Revert</button>
			</div>
		</div>
	</form>
</template>

<style scoped>
.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
	align-items: start;
	gap: 16px;
}

.detail__side {
	display: grid;
	gap: 16px;
}

.about {
	display: grid;
	gap: var(--s-4);
}

.about > * + * {
	margin-top: 0;
}

.about__description {
	color: var(--fg-2);
}

.danger {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
}

.danger > * + * {
	margin-top: 0;
}

.detail__main {
	display: grid;
	gap: 16px;
}

.save {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.save .field__error {
	flex-basis: 100%;
	margin: 0;
}

.facts {
	display: grid;
	gap: 8px;
	margin: 0;
}

.facts > * + * {
	margin-top: 0;
}

.facts div {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}

.facts dt {
	color: var(--fg-2);
}

.facts dd {
	margin: 0;
}

.people {
	display: grid;
	gap: 8px;
	margin: 0;
	list-style: none;
}

.people li {
	display: flex;
	align-items: center;
	gap: 10px;
}

.people__avatar {
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 600;
	text-transform: uppercase;
}

.capabilities {
	padding: 12px var(--pad-x);
	border-top: 1px solid var(--border);
}

.capabilities h3 {
	margin-bottom: 6px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.capabilities ul {
	display: grid;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.capabilities li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: var(--text-sm);
}

.capabilities li svg {
	flex: none;
	width: 15px;
	height: 15px;
}

.capabilities code {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.capabilities .is-on svg {
	color: var(--good);
}

.capabilities .is-off {
	color: var(--fg-3);
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
