<script setup lang="ts">
/**
 * An account's roles as checkboxes (D-312; the prototype's role list),
 * each with what it's for. A role you can't give (it can do things you
 * can't, or it's the owner and you aren't one, D-500) is shown but can't
 * be ticked or unticked. Member (D-365) is
 * what holding nothing else means: ticking another role unticks it,
 * unticking the last one ticks it, and ticking it takes the rest.
 */

import AdminIcon from './AdminIcon.vue';
import { MEMBER, OWNER, type RoleInfo } from '../people';

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
			<input :id="`${idPrefix}${role.name}`" class="check-input" type="checkbox" :checked="model.includes(role.name)" :disabled="locked(role)" @change="toggle(role.name, ($event.target as HTMLInputElement).checked)">
			<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
			<span class="role-check__text">
				<span class="role-check__name">{{ role.label }}</span>
				<span v-if="role.description" class="role-check__about">{{ role.description }}</span>
				<span v-if="!role.grantable" class="role-check__about">{{ role.name === OWNER ? 'Only an owner gives or takes it.' : 'It can do things you can\'t, so you can\'t give or take it.' }}</span>
			</span>
		</label>
	</div>
</template>

<style scoped>
/* Roles as rows, not cards (the profiles sketch): selection is a fill,
   the way a selected table row's is. */
.role-checks {
	display: flex;
	flex-direction: column;
	gap: 2px;
	margin-inline: calc(var(--s-3) * -1);
}

.role-check {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-3);
	padding: var(--s-3);
	border-radius: var(--r-2);
	cursor: pointer;
}

.role-check:hover {
	background: var(--surface-2);
}

.role-check--on,
.role-check--on:hover {
	background: var(--accent-soft);
}

.role-check--locked {
	cursor: default;
}

.role-check--locked:not(.role-check--on):hover {
	background: none;
}

/* A role you can't give is gray when it's on, as the global box is,
   but isn't dimmed when it's off. */
.check-input:disabled + .check-box {
	opacity: 1;
}

.role-check__text {
	display: grid;
	min-width: 0;
	line-height: 1.35;
}

.role-check__name {
	font-weight: 600;
}

.role-check__about {
	color: var(--fg-2);
	font-size: var(--text-xs);
}
</style>
