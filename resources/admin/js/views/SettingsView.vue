<script setup lang="ts">
/**
 * The site-wide settings (the design direction's Settings, D-309): the
 * ones that exist, in panels, each value with whether it's still the
 * default, help where it needs it, and a warning where it's risky.
 *
 * Read-only: settings live in `config/` and `.env` (D-039), so each panel
 * names the file it's set in, by convention, and a notice says to
 * compile again after changing them on a compiled site.
 */

import { ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type SettingGroup, type SettingItem } from '../api';

const groups = ref<SettingGroup[] | null>(null);
const error  = ref('');

request<{ groups: SettingGroup[] }>('GET', '/settings').then((answer) => {
	groups.value = answer.groups;
}).catch((caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The settings couldn\'t be loaded.';
});

// Text with backticks marking code, as parts.
function parts(text: string): { text: string; code: boolean }[] {
	return text.split('`').map((part, index) => ({ text: part, code: index % 2 === 1 }));
}

function shown(item: SettingItem): string {
	if (typeof item.value === 'boolean') {
		return item.value ? 'On' : 'Off';
	}

	if (Array.isArray(item.value)) {
		return item.value.length > 0 ? item.value.join(', ') : 'None';
	}

	return item.value;
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Settings</h1>
			<p class="page-header__hint">Site-wide configuration.</p>
		</div>
	</header>

	<p class="notice"><span>Settings live in <code>config/</code> and <code>.env</code>; each panel names its file. After changing them on a site you've compiled, run <code>bin/blush cache:compile</code> again.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="groups" class="settings">
		<section v-for="group in groups" :key="group.key" class="panel" :aria-labelledby="`settings-${group.key}`">
			<header class="panel__header">
				<h2 :id="`settings-${group.key}`">{{ group.title }}</h2>
				<p class="panel__hint">{{ group.hint }}</p>
			</header>
			<dl class="panel__body settings__items">
				<div v-for="item in group.items" :key="item.key" class="setting">
					<dt>{{ item.label }}</dt>
					<dd>
						<span class="setting__value">
							<span v-if="item.kind === 'bool'" class="pill" :class="{ 'pill--warn': item.warning }">{{ shown(item) }}</span>
							<span v-else :class="{ mono: item.kind === 'mono' }">{{ shown(item) }}</span>
							<span v-if="item.default === true" class="setting__default">Default</span>
						</span>
						<span v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</span>
						<span v-if="item.help" class="setting__help">{{ item.help }}</span>
					</dd>
				</div>
			</dl>
			<p class="panel__body field__help settings__file">
				Set in <code>{{ group.file }}</code>.
				<template v-if="group.note"><template v-for="(part, index) in parts(group.note)" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template></template>
			</p>
		</section>
	</div>

	<div v-else-if="!error" class="settings" aria-hidden="true">
		<div v-for="index in 4" :key="index" class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.settings {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.settings__items {
	display: grid;
	gap: var(--s-4);
	margin: 0;
}

.settings__items > * + * {
	margin-top: 0;
}

.setting {
	display: grid;
	grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
	gap: var(--s-3);
}

.setting dt {
	color: var(--fg-2);
}

.setting dd {
	display: grid;
	gap: 4px;
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.setting__value {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 8px;
}

.setting__default {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.setting__help {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.setting__warning {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	color: var(--warn);
	font-size: var(--text-sm);
}

.setting__warning :deep(svg) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.settings__file {
	margin: 0;
	border-top: 1px solid var(--border);
}

@media (width <= 1100px) {
	.settings {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
