<script setup lang="ts">
/**
 * One setting as a row (the settings sketch's, D-404, and the content
 * types sketch's, D-866): its label, its control (the default slot), and
 * its help, from the shared `.setting` classes, its control a `.field`
 * so inputs in it are drawn as every form's are, as on Settings. A setting changed and not
 * saved yet has a dot by its label (`changed`); on a plugin's type, one
 * the site changes from the plugin's value says **Changed Here** and
 * what the plugin sets (`code`).
 */

import { useSlots } from 'vue';
import { t } from '../messages';

withDefaults(defineProps<{
	label: string;
	// The control's id, for the label.
	controlId?: string;
	help?: string;
	// A control that takes the help's column, with the help under it.
	wide?: boolean;
	changed?: boolean;
	// What the plugin sets, when the site changes it; `null` otherwise.
	code?: string | null;
}>(), { controlId: undefined, help: '', wide: false, changed: false, code: null });

const slots = useSlots();
</script>

<template>
	<div class="setting" :class="{ 'setting--wide': wide }">
		<div class="setting__label">
			<label v-if="controlId" :for="controlId">{{ label }}</label>
			<span v-else>{{ label }}</span>
			<span v-if="changed" class="unsaved" :title="t('setting_row.unsaved', 'Not saved yet')"><span class="visually-hidden">{{ t('setting_row.unsaved', 'Not saved yet') }}</span></span>
			<span v-if="code !== null" class="pill pill--accent setting-row__tag">{{ t('setting_row.changed_here', 'Changed Here') }}</span>
		</div>
		<div class="field setting__control"><slot /></div>
		<div v-if="help || slots.help || code !== null" class="setting__help">
			<slot name="help">{{ help }}</slot>
			<span v-if="code !== null" class="setting-row__code">{{ t('setting_row.code', 'Its plugin sets {value}.', { value: code }) }}</span>
		</div>
	</div>
</template>

<style scoped>
.setting__label {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
}

.setting-row__tag {
	height: 20px;
	font-size: var(--font-size-xsmall);
}

.setting-row__code {
	display: block;
	margin-top: 4px;
	color: var(--color-text-muted);
}
</style>
