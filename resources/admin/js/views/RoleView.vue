<script setup lang="ts">
/**
 * One role (D-249), as the capability sections sketch draws it (D-359):
 * a header with what it's for and a strip of facts (its key, where it
 * comes from, who holds it), then its capabilities in sections
 * (`CapabilitySections`). A role you may change (D-312) is edited in
 * place: ticking anything brings up the save bar, which counts the
 * changes and saves or reverts them; leaving with changes unsaved asks
 * first. The header's ⋮ renames a custom role, copies the role as JSON
 * (for `config/auth.php`), and resets a changed built-in or deletes a
 * custom role. **Duplicate** starts a new role from this one's
 * capabilities.
 *
 * The owner always has everything (D-500), and the member nothing
 * (D-365), so each one's capabilities are one statement rather than
 * every box. The administrator is a list, like any other built-in. Roles from `config/auth.php`,
 * and a role that can do things you can't, are shown read-only, with
 * the reason.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import CapabilitySections from '../components/CapabilitySections.vue';
import MenuButton from '../components/MenuButton.vue';
import SaveBar from '../components/SaveBar.vue';
import { useAction } from '../action';
import { errorMessage } from '../api';
import { plural } from '../format';
import { deleteRole, initials, loadRoles, MEMBER, originOf, updateRole, type RoleInfo, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { can } from '../session';
import { toast } from '../toast';

const route  = useRoute();
const router = useRouter();
const list   = ref<RoleList | null>(null);
const error  = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = errorMessage(caught, 'The role couldn\'t be loaded.');
});

const role       = computed(() => list.value?.roles.find((item) => item.name === route.params.name));
const all        = computed(() => list.value?.all ?? '*');
const everything = computed(() => role.value?.capabilities.includes(all.value) ?? false);
// The member never has a capability (D-365).
const nothing    = computed(() => role.value?.name === MEMBER);
const custom     = computed(() => role.value?.origin === 'custom');

watch(role, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });

// Why a role is read-only.
const lockedBecause = computed(() => {
	const value = role.value;

	if (value === undefined || value.editable || everything.value || nothing.value) {
		return '';
	}

	return value.origin === 'config'
		? 'This role is defined in config/auth.php, so it\'s changed there.'
		: 'This role can do things you can\'t, so you can\'t change it.';
});

// Who holds it: a face for each of the first few, and where to see them.
const holders = computed(() => role.value?.accounts ?? []);
const holdersLink = computed(() => holders.value.length === 1 && holders.value[0] !== undefined
	? { name: 'account', params: { username: holders.value[0].username } }
	: { name: 'accounts' });

// The form: what's on screen, and what was loaded.
const label        = ref('');
const description  = ref('');
const capabilities = ref<string[]>([]);
const renaming     = ref(false);

const { busy: saving, error: failure, run } = useAction();

function reset(value: RoleInfo | undefined): void {
	label.value        = value?.label ?? '';
	description.value  = value?.description ?? '';
	capabilities.value = value ? [...value.capabilities] : [];
	renaming.value     = false;
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

// How many things changed: each capability ticked or cleared, and the
// name and description.
const count = computed(() => {
	const value = role.value;

	if (value === undefined) {
		return 0;
	}

	const ticked  = capabilities.value.filter((item) => !value.capabilities.includes(item)).length;
	const cleared = value.capabilities.filter((item) => !capabilities.value.includes(item)).length;

	return ticked + cleared + ('label' in changes.value ? 1 : 0) + ('description' in changes.value ? 1 : 0);
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

	await run('The role couldn\'t be saved.', async () => {
		const saved = await updateRole(value.name, changes.value);

		replace(saved);
		toast(`Saved ${saved.label}`);
	});
}

// The ⋮'s last item: delete a custom role, or reset a changed built-in.
const removal = ref('');

async function remove(): Promise<void> {
	const value = role.value;

	if (value === undefined) {
		return;
	}

	const question = custom.value
		? { title: `Delete the ${value.label} Role?`, body: 'This can\'t be undone.', confirm: 'Delete the role', danger: true }
		: { title: `Reset ${value.label}?`, body: 'It goes back to the capabilities it\'s built with, and your changes to it are lost.', confirm: 'Reset the role', danger: true };

	if (!await confirmAction(question)) {
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

			toast(`Deleted the ${value.label} role`, { kind: 'danger' });
			await router.push({ name: 'roles' });
		} else {
			replace(after);
			toast(`Reset ${after.label}`);
		}
	} catch (caught) {
		removal.value = errorMessage(caught, 'The role couldn\'t be changed.');
	}
}

// The role as `config/auth.php` or `storage/roles.json` would hold it.
async function copyJson(): Promise<void> {
	const value = role.value;

	if (value === undefined) {
		return;
	}

	const json = { name: value.name, label: value.label, capabilities: value.capabilities, ...(value.description === '' ? {} : { description: value.description }) };

	try {
		await navigator.clipboard.writeText(JSON.stringify(json, null, '\t'));
		toast(`Copied ${value.label} as JSON`);
	} catch {
		removal.value = 'The role couldn\'t be copied.';
	}
}

guardLeave(() => changed.value);
</script>

<template>
	<form v-if="role" class="role form-stack" @submit.prevent="save">
		<header class="page-header">
			<RouterLink class="page-back" :to="{ name: 'roles' }"><AdminIcon name="chevron-left" />All roles</RouterLink>
			<div class="page-header__text role__head">
				<h1 tabindex="-1">{{ role.label }}</h1>
				<p v-if="role.description" class="page-header__hint">{{ role.description }}</p>
				<div class="role__facts">
					<span class="role__fact"><AdminIcon name="key-round" /><span class="mono">{{ role.name }}</span></span>
					<span class="role__divider" aria-hidden="true" />
					<span class="role__fact">{{ originOf(role) }}</span>
					<span class="role__divider" aria-hidden="true" />
					<span class="role__fact">
						<template v-if="holders.length">
							<span class="role__faces" aria-hidden="true">
								<span v-for="holder in holders.slice(0, 3)" :key="holder.username" class="role__face">{{ initials(holder.displayName) }}</span>
							</span>
							<RouterLink :to="holdersLink">{{ holders.length === 1 ? holders[0]?.displayName : plural(holders.length, 'account') }}</RouterLink>
						</template>
						<template v-else>Nobody holds it</template>
					</span>
				</div>
			</div>
			<div class="page-header__actions">
				<RouterLink v-if="!everything && !nothing && can('roles.manage')" class="button" :to="{ name: 'role-new', query: { from: role.name } }"><AdminIcon name="copy" />Duplicate</RouterLink>
				<MenuButton button-class="button button--icon" label="More actions">
					<template #button><AdminIcon name="ellipsis" /></template>
					<button v-if="role.editable && custom" type="button" class="menu-item" @click="renaming = true"><AdminIcon name="pen-line" />Rename this role</button>
					<button type="button" class="menu-item" @click="copyJson"><AdminIcon name="copy" />Copy as JSON</button>
					<template v-if="role.editable && role.origin !== 'built-in'">
						<div class="menu-divider" />
						<button v-if="custom" type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />Delete this role</button>
						<button v-else type="button" class="menu-item menu-item--described" @click="remove">
							<AdminIcon name="refresh-cw" />
							<span>
								<span class="menu-item__name">Reset to built-in capabilities</span>
								<span class="menu-item__text">Puts back the capabilities {{ role.label }} ships with.</span>
							</span>
						</button>
					</template>
				</MenuButton>
			</div>
		</header>

		<p v-if="removal" class="notice notice--error" role="alert">{{ removal }}</p>
		<p v-if="lockedBecause" class="notice notice--warn"><span>{{ lockedBecause }}</span></p>

		<section v-if="renaming" class="panel" aria-labelledby="rename-heading">
			<header class="panel__header">
				<h2 id="rename-heading">Name and Description</h2>
			</header>
			<div class="panel__body field-pair role__rename">
				<div class="field">
					<label for="role-label">Name</label>
					<input id="role-label" v-model="label" autocomplete="off" required>
				</div>
				<div class="field">
					<label for="role-key">Key</label>
					<input id="role-key" :value="role.name" class="mono" disabled aria-describedby="role-key-help">
					<p id="role-key-help" class="field__help">Names the role in account files. Fixed once it's made.</p>
				</div>
				<div class="field role__description">
					<label for="role-description">Description</label>
					<input id="role-description" v-model="description" autocomplete="off" aria-describedby="role-description-help">
					<p id="role-description-help" class="field__help">What it's for, in a line. Shown where roles are given.</p>
				</div>
			</div>
		</section>

		<section v-if="everything" class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">Capabilities</h2>
				<p class="panel__hint">Not editable on this role</p>
			</header>
			<div class="role__everything">
				<AdminIcon name="shield" />
				<div>
					<h3>Everything, Including What Doesn't Exist Yet</h3>
					<p>{{ role.label }} holds every capability on every content type, and any type or capability an extension adds later is included the moment it appears. There's nothing to grant here, so there's nothing to draw.</p>
					<p>Only an owner can give this role, or change an owner's account, so whoever holds it can't be locked out. Administrator is the role to give anyone else who runs the site; an owner can change what it allows.</p>
					<RouterLink v-if="holders.length" class="button" :to="holdersLink"><AdminIcon name="users" />{{ holders.length === 1 ? 'See the account that holds it' : 'See the accounts that hold it' }}</RouterLink>
				</div>
			</div>
		</section>

		<section v-else-if="nothing" class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">Capabilities</h2>
				<p class="panel__hint">Not editable on this role</p>
			</header>
			<div class="role__everything">
				<AdminIcon name="user-round" />
				<div>
					<h3>Nothing but Their Own Account</h3>
					<p>{{ role.label }} can sign in and look after their own account (their name, password, and preferences), and nothing else. It's what an account holds when it holds no other role: a new account starts with it, and taking someone's last role leaves them with it.</p>
					<p>It never gets a capability, so whoever can make accounts but not give roles hands out nothing. To let someone do more, give them another role.</p>
				</div>
			</div>
		</section>

		<CapabilitySections v-else-if="list" v-model="capabilities" :capabilities="list.capabilities" :types="list.types" :base="role.capabilities" :readonly="!role.editable" />

		<SaveBar v-if="role.editable" :count="count" :failure="failure" :saving="saving" :ready="changed" @revert="reset(role)" />
	</form>

	<template v-else>
		<header class="page-header">
			<RouterLink class="page-back" :to="{ name: 'roles' }"><AdminIcon name="chevron-left" />All roles</RouterLink>
			<div class="page-header__text">
				<h1 tabindex="-1">Role</h1>
			</div>
		</header>
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="list" class="notice notice--error" role="alert">There's no “{{ route.params.name }}” role.</p>
	</template>
</template>

<style scoped>
.role {
	/* Room under the last section for the save bar. */
	padding-bottom: var(--s-7);
}

.role__head {
	gap: 9px;
}

/* The facts strip: what a card beside the role used to hold. */
.role__facts {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-3);
	margin-top: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.role__fact {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	white-space: nowrap;
}

.role__fact svg {
	width: 13px;
	height: 13px;
}

.role__fact a {
	color: var(--accent);
	font-weight: 500;
	text-decoration: none;
}

.role__fact a:hover {
	text-decoration: underline;
}

.role__divider {
	flex: none;
	width: 1px;
	height: 13px;
	background: var(--border);
}

.role__faces {
	display: inline-flex;
	margin-right: 4px;
}

.role__face {
	display: grid;
	place-items: center;
	width: 22px;
	height: 22px;
	margin-right: -6px;
	border: 2px solid var(--bg);
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-2xs);
	font-weight: 600;
}

.role__rename {
	align-items: start;
}

/* Every input the same height, whatever its font, as on New Role. */
.role__rename input {
	height: var(--ctl);
}

.role__description {
	grid-column: 1 / -1;
}

.role__rename > * + * {
	margin-top: 0;
}

/* The administrator's one statement, in place of every box ticked. */
.role__everything {
	display: flex;
	align-items: flex-start;
	gap: var(--s-4);
	padding: var(--s-6) var(--pad-x);
}

.role__everything > svg {
	width: 20px;
	height: 20px;
	margin-top: 2px;
	color: var(--accent);
}

.role__everything h3 {
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
}

.role__everything p {
	max-width: 64ch;
	margin-top: 7px;
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.6;
}

.role__everything .button {
	margin-top: var(--s-4);
}

@media (width <= 640px) {
	.role__everything {
		padding: var(--s-5) var(--s-4);
	}
}
</style>
