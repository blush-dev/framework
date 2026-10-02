<script setup lang="ts">
/**
 * An account's roles as checkboxes (D-312; the prototype's role list),
 * each with what it's for. A role you can't give (it can do things you
 * can't) is shown but can't be ticked or unticked. Member (D-365) is
 * what holding nothing else means: ticking another role unticks it,
 * unticking the last one ticks it, and ticking it takes the rest.
 */

import AdminIcon from './AdminIcon.vue';
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
			<input :id="`${idPrefix}${role.name}`" class="role-check__input" type="checkbox" :checked="model.includes(role.name)" :disabled="locked(role)" @change="toggle(role.name, ($event.target as HTMLInputElement).checked)">
			<span class="role-check__box" aria-hidden="true"><AdminIcon name="check" /></span>
			<span class="role-check__text">
				<span class="role-check__name">{{ role.label }}</span>
				<span v-if="role.description" class="role-check__about">{{ role.description }}</span>
				<span v-if="!role.grantable" class="role-check__about">It can do things you can't, so you can't give or take it.</span>
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

.role-check__input {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: 0;
	opacity: 0;
}

.role-check__box {
	display: grid;
	flex: none;
	place-items: center;
	width: 16px;
	height: 16px;
	border: 1px solid var(--border-strong);
	border-radius: 4px;
	background: var(--surface);
	color: transparent;
}

.role-check__box .icon {
	width: 11px;
	height: 11px;
	stroke-width: 2.6;
}

.role-check--on .role-check__box {
	border-color: var(--accent);
	background: var(--accent);
	color: var(--accent-fg);
}

.role-check--on.role-check--locked .role-check__box {
	border-color: var(--fg-3);
	background: var(--fg-3);
}

.role-check__input:focus-visible + .role-check__box {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
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
