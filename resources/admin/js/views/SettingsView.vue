<script setup lang="ts">
/**
 * A Settings screen (the design direction's Settings, D-309, D-324,
 * D-325): General, Reading, Addresses and Search, or System, as
 * panels. Settings the site owner can change are a form saved together
 * with the save bar (`PATCH settings`); beside them, the related ones set
 * in code are only shown, with the file they're set in. Each says
 * whether it's still the default, with help where it needs it and a
 * warning where it's risky.
 *
 * Saved settings live in `user/data/settings.json` and win over `config/`.
 * A saved one offers its config value back ("Use config/app.php's"),
 * which is a change like any other until it's saved. A save that changes
 * what has addresses asks the server to compile and reindex
 * (`POST settings/refresh`), then the screen loads again. Leaving, or
 * moving to another Settings screen, with changes unsaved asks first.
 */

import { computed, ref, watch } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import { ApiError, request, type SettingGroup, type SettingItem, type SettingValue } from '../api';
import { screenTitle, screenTrail } from '../screen';
import { toast } from '../toast';

const props = defineProps<{ screen: string }>();

const screens: Record<string, { title: string; hint: string }> = {
	general: { title: 'General', hint: 'The site\'s name, language, and time, and where it runs.' },
	reading: { title: 'Reading', hint: 'What the home page shows, and the feeds.' },
	search: { title: 'Addresses and Search', hint: 'How addresses are written, and what search engines are told.' },
	system: { title: 'System', hint: 'How the site is put together and run, all set in code.' }
};

const groups  = ref<SettingGroup[] | null>(null);
const error   = ref('');
const form    = ref<Record<string, SettingValue>>({});
const initial = ref<Record<string, SettingValue>>({});
const unset   = ref<string[]>([]);
const saving  = ref(false);
const failure = ref('');

const about    = computed(() => screens[props.screen] ?? { title: 'Settings', hint: '' });
const editable = computed(() => Object.keys(initial.value).length > 0);

// The settings whose values changed, then the ones going back to config.
const changed = computed(() => Object.keys(form.value).filter((key) => !same(form.value[key], initial.value[key]) && !unset.value.includes(key)));
const count   = computed(() => changed.value.length + unset.value.length);

// What the checkbox beside a setting says.
const checkboxText: Record<string, string> = {
	'routes.trailingSlash': 'Addresses end in a slash',
	'feed.content': 'Feeds carry each entry\'s full content',
	'sitemap.enabled': 'The site has a sitemap and robots.txt'
};

async function load(): Promise<void> {
	const screen = props.screen;

	try {
		const answer = await request<{ groups: SettingGroup[] }>('GET', `/settings/${screen}`);
		const values: Record<string, SettingValue> = {};

		if (screen !== props.screen) {
			return;
		}

		for (const item of answer.groups.flatMap((group) => group.items)) {
			if (item.setting !== undefined && item.input !== undefined) {
				values[item.setting] = copy(item.input);
			}
		}

		groups.value  = answer.groups;
		form.value    = values;
		initial.value = Object.fromEntries(Object.entries(values).map(([key, value]) => [key, copy(value)]));
		unset.value   = [];
		error.value   = '';
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The settings couldn\'t be loaded.';
	}
}

function copy(value: SettingValue): SettingValue {
	return Array.isArray(value) ? [...value] : value;
}

function same(a: SettingValue | undefined, b: SettingValue | undefined): boolean {
	return JSON.stringify(a) === JSON.stringify(b);
}

// The value a setting is sent as: lines without blanks, and the home
// page's `''` as `null`.
function outgoing(setting: string, value: SettingValue): SettingValue | null {
	if (setting === 'content.home' && value === '') {
		return null;
	}

	return Array.isArray(value) ? value.map((line) => line.trim()).filter((line) => line !== '') : value;
}

async function save(): Promise<void> {
	if (count.value === 0 || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const set    = Object.fromEntries(changed.value.map((key) => [key, outgoing(key, form.value[key] ?? '')]));
		const answer = await request<{ refresh: boolean }>('PATCH', '/settings', { set, unset: unset.value });

		if (answer.refresh) {
			await request('POST', '/settings/refresh').catch(() => undefined);
		}

		await load();
		toast('Settings saved');
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The settings couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	form.value    = Object.fromEntries(Object.entries(initial.value).map(([key, value]) => [key, copy(value)]));
	unset.value   = [];
	failure.value = '';
}

// Goes back to the config's value once saved, or keeps the saved one.
function useConfig(setting: string, on: boolean): void {
	unset.value = on ? [...unset.value, setting] : unset.value.filter((key) => key !== setting);

	const saved = initial.value[setting];

	if (on && saved !== undefined) {
		form.value[setting] = copy(saved);
	}
}

function lines(setting: string): string {
	const value = form.value[setting];

	return Array.isArray(value) ? value.join('\n') : '';
}

function setLines(setting: string, text: string): void {
	form.value[setting] = text.split('\n');
}

function checked(setting: string, value: string): boolean {
	const list = form.value[setting];

	return Array.isArray(list) && list.includes(value);
}

function check(setting: string, value: string, on: boolean, options: { value: string }[]): void {
	const list = form.value[setting];
	const next = Array.isArray(list) ? list.filter((item) => item !== value) : [];

	// Kept in the options' order, as the server saves them.
	form.value[setting] = options.map((option) => option.value).filter((option) => option === value ? on : next.includes(option));
}

function stringValue(setting: string): string {
	const value = form.value[setting];

	return typeof value === 'string' ? value : '';
}

// The time in a time zone now, for its help.
function timeIn(zone: string): string {
	try {
		return new Intl.DateTimeFormat(undefined, { timeZone: zone, weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date());
	} catch {
		return '';
	}
}

// Help that follows the form, where the server's would go stale.
function liveHelp(item: SettingItem): string | null {
	if (item.setting === 'app.timezone') {
		const time = timeIn(stringValue('app.timezone'));

		return time === '' ? null : `It's ${time} there now.`;
	}

	if (item.setting === 'routes.trailingSlash') {
		return `Addresses look like /about${form.value['routes.trailingSlash'] === true ? '/' : ''}. ${item.help ?? ''}`;
	}

	return item.help;
}

// A warning for a change that reaches far.
function changeWarning(item: SettingItem): string | null {
	if (item.setting === 'routes.trailingSlash' && changed.value.includes('routes.trailingSlash')) {
		return 'Every address on the site changes. The old ones redirect, but update the links you control.';
	}

	if (item.setting === 'sitemap.enabled' && form.value['sitemap.enabled'] === false && changed.value.includes('sitemap.enabled')) {
		return 'Search engines lose the sitemap, and robots.txt goes too.';
	}

	return null;
}

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

const leave = (): boolean => count.value === 0 || window.confirm('Leave without saving? Your changes will be lost.');

watch(() => props.screen, () => {
	groups.value  = null;
	form.value    = {};
	initial.value = {};
	unset.value   = [];
	failure.value = '';
	void load();
});

watch(about, (value) => {
	screenTitle.value = value.title;
	screenTrail.value = [{ label: 'Settings', to: { name: 'settings', params: { screen: 'general' } } }];
}, { immediate: true });

void load();

onBeforeRouteLeave(leave);
onBeforeRouteUpdate(leave);
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ about.title }}</h1>
			<p class="page-header__hint">{{ about.hint }}</p>
		</div>
	</header>

	<p v-if="screen === 'system'" class="notice"><span>These live in <code>config/</code> and <code>.env</code>; each names its file. After changing them on a site you've compiled, run <code>bin/blush cache:compile</code> again.</span></p>
	<p v-else-if="editable" class="notice"><span>What you save here is kept in <code>user/data/settings.json</code> and wins over <code>config/</code>. The rest are set in code and only shown.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<form v-if="groups" class="settings" @submit.prevent="save">
		<section v-for="group in groups" :key="group.key" class="panel" :aria-labelledby="`settings-${group.key}`">
			<header class="panel__header">
				<h2 :id="`settings-${group.key}`">{{ group.title }}</h2>
				<p class="panel__hint">{{ group.hint }}</p>
			</header>
			<div class="panel__body settings__items">
				<div v-for="item in group.items" :key="item.key" class="setting">
					<template v-if="item.setting !== undefined">
						<label v-if="item.control !== 'checks' && item.control !== 'checkbox'" class="setting__label" :for="`setting-${item.key}`">{{ item.label }}</label>
						<span v-else :id="`setting-${item.key}-label`" class="setting__label">{{ item.label }}</span>

						<div class="field setting__control" :class="{ 'is-unset': unset.includes(item.setting) }">
							<input v-if="item.control === 'text' || item.control === 'mono'" :id="`setting-${item.key}`" v-model="form[item.setting]" :class="{ mono: item.control === 'mono' }" type="text" autocomplete="off" :spellcheck="item.control === 'text'" :disabled="unset.includes(item.setting)" :aria-describedby="`setting-${item.key}-help`">
							<AdminSelect v-else-if="item.control === 'select'" :id="`setting-${item.key}`" :model-value="stringValue(item.setting)" :options="item.options ?? []" :disabled="unset.includes(item.setting)" :described-by="`setting-${item.key}-help`" @update:model-value="form[item.setting!] = $event" />
							<input v-else-if="item.control === 'number'" :id="`setting-${item.key}`" v-model.number="form[item.setting]" class="setting__number" type="number" min="1" max="100" step="1" :disabled="unset.includes(item.setting)" :aria-describedby="`setting-${item.key}-help`">
							<textarea v-else-if="item.control === 'lines'" :id="`setting-${item.key}`" class="mono" rows="3" spellcheck="false" :value="lines(item.setting)" :disabled="unset.includes(item.setting)" :aria-describedby="`setting-${item.key}-help`" @input="setLines(item.setting!, ($event.target as HTMLTextAreaElement).value)" />
							<label v-else-if="item.control === 'checkbox'" class="checkbox"><input v-model="form[item.setting]" type="checkbox" :disabled="unset.includes(item.setting)" :aria-labelledby="`setting-${item.key}-label`" :aria-describedby="`setting-${item.key}-help`"> {{ checkboxText[item.setting] ?? 'On' }}</label>
							<div v-else-if="item.control === 'checks'" class="setting__checks" role="group" :aria-labelledby="`setting-${item.key}-label`" :aria-describedby="`setting-${item.key}-help`">
								<label v-for="option in item.options ?? []" :key="option.value" class="checkbox"><input type="checkbox" :checked="checked(item.setting, option.value)" :disabled="unset.includes(item.setting)" @change="check(item.setting!, option.value, ($event.target as HTMLInputElement).checked, item.options ?? [])"> {{ option.label }}</label>
							</div>

							<p v-if="changeWarning(item)" class="setting__warning"><AdminIcon name="triangle-alert" />{{ changeWarning(item) }}</p>
							<p :id="`setting-${item.key}-help`" class="field__help">
								<template v-if="liveHelp(item)">{{ liveHelp(item) }}</template>
								<span class="setting__source">
									<template v-if="unset.includes(item.setting)">Uses <code>{{ item.file }}</code>'s value once saved. <button type="button" class="link-button" @click="useConfig(item.setting!, false)">Keep the saved one</button></template>
									<template v-else-if="item.saved">Saved here. <button type="button" class="link-button" @click="useConfig(item.setting!, true)">Use <code>{{ item.file }}</code>'s value</button></template>
									<template v-else>From <code>{{ item.file }}</code><template v-if="item.default === true">, the default</template>.</template>
								</span>
							</p>
						</div>
					</template>

					<template v-else>
						<span class="setting__label">{{ item.label }}</span>
						<div class="setting__shown">
							<span class="setting__value">
								<span v-if="item.kind === 'bool'" class="pill" :class="{ 'pill--warn': item.warning }">{{ shown(item) }}</span>
								<span v-else :class="{ mono: item.kind === 'mono' }">{{ shown(item) }}</span>
								<span v-if="item.default === true" class="setting__default">Default</span>
							</span>
							<span v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</span>
							<span v-if="item.help" class="field__help">{{ item.help }}</span>
							<span v-if="item.file" class="field__help">Set in <code>{{ item.file }}</code>.</span>
						</div>
					</template>
				</div>
			</div>
			<p v-if="group.note" class="panel__body field__help settings__note">
				<template v-for="(part, index) in parts(group.note)" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template>
			</p>
		</section>

		<div v-if="count > 0 || failure" class="save-bar" role="region" aria-label="Unsaved changes">
			<span class="save-bar__count" aria-live="polite">{{ count === 1 ? '1 unsaved change' : `${count} unsaved changes` }}</span>
			<span v-if="failure" class="save-bar__error" role="alert">{{ failure }}</span>
			<button type="button" class="button button--ghost button--small" :disabled="saving" @click="revert">Revert</button>
			<button type="submit" class="button button--primary button--small" :disabled="saving || count === 0">{{ saving ? 'Saving…' : 'Save changes' }}</button>
		</div>
	</form>

	<div v-else-if="!error" class="settings" aria-hidden="true">
		<div v-for="index in 3" :key="index" class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.settings {
	display: grid;
	gap: var(--s-4);
	max-width: 48rem;
}

.settings__items {
	display: grid;
	gap: var(--s-5);
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

.setting__label {
	padding-top: 7px;
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
}

.setting__control {
	min-width: 0;
}

.setting__control .checkbox {
	min-height: var(--ctl);
}

.setting__control.is-unset > :first-child {
	opacity: .6;
}

.setting__number {
	max-width: 8rem;
}

.setting__checks {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2) var(--s-4);
	min-height: var(--ctl);
}

.setting__source {
	display: block;
}

/* A value set in code, read where the controls start. */
.setting__shown {
	display: grid;
	gap: 4px;
	min-width: 0;
	padding-top: 7px;
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

.setting__warning {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	margin: 0;
	color: var(--warn);
	font-size: var(--text-sm);
}

.setting__warning :deep(svg) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.settings__note {
	margin: 0;
	border-top: 1px solid var(--border);
}

.link-button {
	padding: 0;
	border: 0;
	background: none;
	color: var(--accent);
	font: inherit;
	text-decoration: underline;
	text-underline-offset: .15em;
	cursor: pointer;
}

.save-bar {
	position: fixed;
	bottom: calc(28px + env(safe-area-inset-bottom, 0px));
	left: 50%;
	z-index: 40;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
	max-width: calc(100vw - 32px);
	padding: 9px 10px 9px 20px;
	border: 1px solid var(--border-strong);
	border-radius: 999px;
	background: var(--surface);
	box-shadow: var(--shadow-3);
	transform: translateX(-50%);
}

.save-bar__count {
	font-size: var(--text-sm);
	white-space: nowrap;
	color: var(--fg-2);
}

.save-bar__error {
	max-width: 28rem;
	color: var(--danger);
	font-size: var(--text-sm);
	font-weight: 500;
}

@media (prefers-reduced-motion: no-preference) {
	.save-bar {
		animation: save-rise .16s ease-out;
	}
}

@keyframes save-rise {
	from {
		opacity: 0;
		transform: translate(-50%, 8px);
	}
}

@media (width <= 640px) {
	.setting {
		grid-template-columns: minmax(0, 1fr);
		gap: 7px;
	}

	.setting__label,
	.setting__shown {
		padding-top: 0;
	}
}
</style>
