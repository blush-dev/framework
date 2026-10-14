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
import AdminAvatar from '../components/AdminAvatar.vue';
import AdminIcon from '../components/AdminIcon.vue';
import CapabilitySections from '../components/CapabilitySections.vue';
import MenuButton from '../components/MenuButton.vue';
import SaveBar from '../components/SaveBar.vue';
import { useAction } from '../action';
import { errorMessage } from '../api';
import { deleteRole, loadRoles, MEMBER, originOf, updateRole, type RoleInfo, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { can } from '../session';
import { toast } from '../toast';
import { t } from '../messages';

const route  = useRoute();
const router = useRouter();
const list   = ref<RoleList | null>(null);
const error  = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = errorMessage(caught, t('role_view.errors.load', 'The role couldn\'t be loaded.'));
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
		? t('role_view.locked_config', 'This role is defined in config/auth.php, so it\'s changed there.')
		: t('role_view.locked_beyond', 'This role can do things you can\'t, so you can\'t change it.');
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

	await run(t('role_view.errors.save', 'The role couldn\'t be saved.'), async () => {
		const saved = await updateRole(value.name, changes.value);

		replace(saved);
		toast(t('role_view.saved', 'Saved {role}', { role: saved.label }));
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
		? { title: t('role_view.delete_title', 'Delete the {role} Role?', { role: value.label }), body: t('role_view.delete_body', 'This can\'t be undone.'), confirm: t('role_view.delete_confirm', 'Delete the Role'), danger: true }
		: { title: t('role_view.reset_title', 'Reset {role}?', { role: value.label }), body: t('role_view.reset_body', 'It goes back to the capabilities it\'s built with, and your changes to it are lost.'), confirm: t('role_view.reset_confirm', 'Reset the Role'), danger: true };

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

			toast(t('role_view.deleted', 'Deleted the {role} role', { role: value.label }), { kind: 'danger' });
			await router.push({ name: 'roles' });
		} else {
			replace(after);
			toast(t('role_view.reset', 'Reset {role}', { role: after.label }));
		}
	} catch (caught) {
		removal.value = errorMessage(caught, t('role_view.errors.change', 'The role couldn\'t be changed.'));
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
		toast(t('role_view.copied', 'Copied {role} as JSON', { role: value.label }));
	} catch {
		removal.value = t('role_view.errors.copy', 'The role couldn\'t be copied.');
	}
}

guardLeave(() => changed.value);
</script>

<template>
	<form v-if="role" class="role form-stack" @submit.prevent="save">
		<header class="page-header">
			<RouterLink class="page-back" :to="{ name: 'roles' }"><AdminIcon name="chevron-left" />{{ t('role_view.all_roles', 'All roles') }}</RouterLink>
			<div class="page-header__text role__head">
				<h1 tabindex="-1">{{ role.label }}</h1>
				<p v-if="role.description" class="page-header__hint">{{ role.description }}</p>
				<div class="page-facts">
					<span class="page-facts__item"><AdminIcon name="key-round" /><span class="mono">{{ role.name }}</span></span>
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item">{{ originOf(role) }}</span>
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item">
						<template v-if="holders.length">
							<span class="role__faces" aria-hidden="true">
								<AdminAvatar v-for="holder in holders.slice(0, 3)" :key="holder.username" class="role__face" :name="holder.displayName" :image="holder.avatar" />
							</span>
							<RouterLink :to="holdersLink">{{ holders.length === 1 ? holders[0]?.displayName : t('role_view.accounts', '{count, plural, one {# account} other {# accounts} }', { count: holders.length }) }}</RouterLink>
						</template>
						<template v-else>{{ t('role_view.nobody', 'Nobody holds it') }}</template>
					</span>
				</div>
			</div>
			<div class="page-header__actions">
				<RouterLink v-if="!everything && !nothing && can('roles.manage')" class="button" :to="{ name: 'role-new', query: { from: role.name } }"><AdminIcon name="copy" />{{ t('role_view.duplicate', 'Duplicate') }}</RouterLink>
				<MenuButton button-class="button button--icon" :label="t('role_view.more_actions', 'More actions')">
					<template #button><AdminIcon name="ellipsis" /></template>
					<button v-if="role.editable && custom" type="button" class="menu-item" @click="renaming = true"><AdminIcon name="pen-line" />{{ t('role_view.rename', 'Rename this role') }}</button>
					<button type="button" class="menu-item" @click="copyJson"><AdminIcon name="copy" />{{ t('role_view.copy_json', 'Copy as JSON') }}</button>
					<template v-if="role.editable && role.origin !== 'built-in'">
						<div class="menu-divider" />
						<button v-if="custom" type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('role_view.delete', 'Delete this role') }}</button>
						<button v-else type="button" class="menu-item menu-item--described" @click="remove">
							<AdminIcon name="refresh-cw" />
							<span>
								<span class="menu-item__name">{{ t('role_view.reset_name', 'Reset to built-in capabilities') }}</span>
								<span class="menu-item__text">{{ t('role_view.reset_text', 'Puts back the capabilities {role} ships with.', { role: role.label }) }}</span>
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
				<h2 id="rename-heading">{{ t('role_view.rename_heading', 'Name and Description') }}</h2>
			</header>
			<div class="panel__body field-pair role__rename">
				<div class="field">
					<label for="role-label">{{ t('role_view.name', 'Name') }}</label>
					<input id="role-label" v-model="label" autocomplete="off" required>
				</div>
				<div class="field">
					<label for="role-key">{{ t('role_view.key', 'Key') }}</label>
					<input id="role-key" :value="role.name" class="mono" disabled aria-describedby="role-key-help">
					<p id="role-key-help" class="field__help">{{ t('role_view.key_help', 'Names the role in account files. Fixed once it\'s made.') }}</p>
				</div>
				<div class="field role__description">
					<label for="role-description">{{ t('role_view.description', 'Description') }}</label>
					<input id="role-description" v-model="description" autocomplete="off" aria-describedby="role-description-help">
					<p id="role-description-help" class="field__help">{{ t('role_view.description_help', 'What it\'s for, in a line. Shown where roles are given.') }}</p>
				</div>
			</div>
		</section>

		<section v-if="everything" class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">{{ t('role_view.capabilities', 'Capabilities') }}</h2>
				<p class="panel__hint">{{ t('role_view.not_editable', 'Not editable on this role') }}</p>
			</header>
			<div class="role__everything">
				<AdminIcon name="shield" />
				<div>
					<h3>{{ t('role_view.everything', 'Everything, Including What Doesn\'t Exist Yet') }}</h3>
					<p>{{ t('role_view.everything_text', '{role} holds every capability on every content type, and any type or capability an extension adds later is included the moment it appears. There\'s nothing to grant here, so there\'s nothing to draw.', { role: role.label }) }}</p>
					<p>{{ t('role_view.owner_text', 'Only an owner can give this role, or change an owner\'s account, so whoever holds it can\'t be locked out. Administrator is the role to give anyone else who runs the site; an owner can change what it allows.') }}</p>
					<RouterLink v-if="holders.length" class="button" :to="holdersLink"><AdminIcon name="users" />{{ holders.length === 1 ? t('role_view.see_account', 'See the Account That Holds It') : t('role_view.see_accounts', 'See the Accounts That Hold It') }}</RouterLink>
				</div>
			</div>
		</section>

		<section v-else-if="nothing" class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">{{ t('role_view.capabilities', 'Capabilities') }}</h2>
				<p class="panel__hint">{{ t('role_view.not_editable', 'Not editable on this role') }}</p>
			</header>
			<div class="role__everything">
				<AdminIcon name="user-round" />
				<div>
					<h3>{{ t('role_view.nothing', 'Nothing but Their Own Account') }}</h3>
					<p>{{ t('role_view.nothing_text', '{role} can sign in and look after their own account (their name, password, and preferences), and nothing else. It\'s what an account holds when it holds no other role: a new account starts with it, and taking someone\'s last role leaves them with it.', { role: role.label }) }}</p>
					<p>{{ t('role_view.nothing_more', 'It never gets a capability, so whoever can make accounts but not give roles hands out nothing. To let someone do more, give them another role.') }}</p>
				</div>
			</div>
		</section>

		<CapabilitySections v-else-if="list" v-model="capabilities" :capabilities="list.capabilities" :types="list.types" :base="role.capabilities" :readonly="!role.editable" />

		<SaveBar v-if="role.editable" :count="count" :failure="failure" :saving="saving" :ready="changed" @revert="reset(role)" />
	</form>

	<template v-else>
		<header class="page-header">
			<RouterLink class="page-back" :to="{ name: 'roles' }"><AdminIcon name="chevron-left" />{{ t('role_view.all_roles', 'All roles') }}</RouterLink>
			<div class="page-header__text">
				<h1 tabindex="-1">{{ t('role_view.role', 'Role') }}</h1>
			</div>
		</header>
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="list" class="notice notice--error" role="alert">{{ t('role_view.missing', 'There\'s no “{name}” role.', { name: route.params.name }) }}</p>
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
	border: 2px solid var(--color-background);
	border-radius: 50%;
	background: var(--color-surface-strong);
	color: var(--color-text-secondary);
	font-size: var(--font-size-xxsmall);
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
	padding: var(--s-6) var(--padding-inline);
}

.role__everything > svg {
	width: 20px;
	height: 20px;
	margin-top: 2px;
	color: var(--color-accent);
}

.role__everything h3 {
	font-family: var(--font-family-display);
	font-size: var(--font-size-panel-title);
	font-weight: var(--font-weight-display);
}

.role__everything p {
	max-width: 64ch;
	margin-top: 7px;
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
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
