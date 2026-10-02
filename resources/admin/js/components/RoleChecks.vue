<script setup lang="ts">
/**
 * An account's roles as checkboxes (D-312; the prototype's role list),
 * each with what it's for. A role you can't give (it can do things you
 * can't) is shown but can't be ticked or unticked. Member (D-365) is
 * what holding nothing else means: ticking another role unticks it,
 * unticking the last one ticks it, and ticking it takes the rest.
 */

import { MEMBER, type RoleInfo } from '../people';

const props = defineProps<{
	roles: RoleInfo[];
	idPrefix: string;
	disabled?: boolean;
	invalid?: boolean;
	describedBy?: string;
}>();

const model = defineModel<string[]>({ required: true });

function toggle(name: string, on: boolean): void {
	const others = on
		? (name === MEMBER ? [] : [...model.value.filter((item) => item !== MEMBER), name])
		: model.value.filter((item) => item !== name && item !== MEMBER);

	model.value = others.length === 0 ? [MEMBER] : others;
}

// Member alone can't be unticked: there'd be nothing left.
function locked(role: RoleInfo): boolean {
	return props.disabled === true || !role.grantable || (role.name === MEMBER && model.value.length === 1 && model.value[0] === MEMBER);
}
</script>

<template>
	<div class="role-checks" role="group" :aria-describedby="describedBy" :aria-invalid="invalid ? 'true' : undefined">
		<label v-for="role in roles" :key="role.name" class="role-check" :class="{ 'role-check--on': model.includes(role.name), 'role-check--locked': locked(role) }">
			<input :id="`${idPrefix}${role.name}`" type="checkbox" :checked="model.includes(role.name)" :disabled="locked(role)" @change="toggle(role.name, ($event.target as HTMLInputElement).checked)">
			<span>
				<span class="role-check__name">{{ role.label }}</span>
				<span v-if="role.description" class="role-check__text">{{ role.description }}</span>
				<span v-if="!role.grantable" class="role-check__text">It can do things you can't, so you can't give or take it.</span>
			</span>
		</label>
	</div>
</template>

<style scoped>
.role-checks {
	display: grid;
	gap: var(--s-2);
}

.role-check {
	display: flex;
	align-items: flex-start;
	gap: 11px;
	padding: 12px var(--s-4);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	cursor: pointer;
}

.role-check:hover {
	background: var(--surface-2);
}

.role-check--on {
	border-color: var(--accent-line);
}

.role-check--locked {
	cursor: default;
}

.role-check--locked:hover {
	background: none;
}

.role-check input {
	flex: none;
	width: 15px;
	height: 15px;
	margin: 2px 0 0;
	accent-color: var(--accent);
}

.role-check__name {
	display: block;
	font-weight: 500;
}

.role-check__text {
	display: block;
	margin-top: 3px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	line-height: 1.5;
}
</style>
