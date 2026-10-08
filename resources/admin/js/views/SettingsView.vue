<script setup lang="ts">
/**
 * A Settings screen (the design direction's Settings, D-309, D-324,
 * D-325): General, Reading, Addresses and Search, AI (D-398), or System, as
 * panels. Settings the site owner can change are a form saved together
 * with the save bar (`PATCH settings`); beside them, the related ones set
 * in code are only shown, with the file they're set in. Each says
 * whether it's still the default, with help where it needs it and a
 * warning where it's risky. A setting that needs another on (`requires`,
 * D-402) is locked while that one is off in the form, saying why.
 *
 * The screens are drawn as the settings sketch has them (D-404): panels
 * the full width of the work area, each setting a row of its label, its
 * control, and its help beside it (under it, where the panel is narrow),
 * with the place it's set last. A control that wants the width (a text
 * area) takes the help's column too, with the help under it.
 *
 * Media's upload rules (D-406) are one setting drawn as a grid
 * (`UploadRules`), the whole of its panel, with where they're set under
 * it; the form holds the grid as text, so it's compared as the others
 * are.
 *
 * Each setting is edited as a field (D-343), with the control the server
 * names (`FieldInput`, as every form draws them), but a yes or no is a
 * switch saying On or Off (`ToggleSwitch`), and the language is a menu
 * of locales with Other for any code (`LocalePicker`, D-441); a setting
 * with a `menu` (the time zones, D-444) is a searchable menu of it, and
 * the date and time formats are menus of how each reads, with Custom for
 * any pattern (`DateFormatPicker`, D-445). After a screen's own
 * panels, each field set on it adds a panel of its settings, saved in
 * `settings.json`'s `site` section, with no config value behind them: a
 * saved one can be cleared instead.
 *
 * Saved settings live in `user/data/settings.json` and win over `config/`.
 * A saved one offers its config value back ("Use config/app.php's"),
 * which is a change like any other until it's saved. A save that changes
 * what has addresses asks the server to compile and reindex
 * (`POST settings/refresh`), then the screen loads again. Leaving, or
 * moving to another Settings screen, with changes unsaved asks first.
 */

import { computed, ref, watch } from 'vue';
import { confirmLeave, guardLeave } from '../confirm';
import { onBeforeRouteUpdate, RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import FieldInput from '../components/FieldInput.vue';
import DateFormatPicker from '../components/DateFormatPicker.vue';
import LocalePicker from '../components/LocalePicker.vue';
import SaveBar from '../components/SaveBar.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import UploadRules from '../components/UploadRules.vue';
import { useAction } from '../action';
import { errorMessage, request, saveSettings, type FieldDescription, type SettingGroup, type SettingItem, type UploadsInfo } from '../api';
import { control, fromForm, toForm, type FormValue } from '../fields';
import { screenTitle, screenTrail } from '../screen';
import { toast } from '../toast';
import { fromGrid, summary, toGrid, type UploadGrid } from '../uploads';

const props = defineProps<{ screen: string }>();

const screens: Record<string, { title: string; hint: string }> = {
	general: { title: 'General', hint: 'The site\'s name, language, and time, and where it runs.' },
	reading: { title: 'Reading', hint: 'What the homepage shows, and the feeds.' },
	writing: { title: 'Writing', hint: 'How what\'s written renders, and what raw HTML in it does.' },
	media: { title: 'Media', hint: 'What may be uploaded, how large, and where it\'s kept.' },
	search: { title: 'Addresses and Search', hint: 'How addresses are written, and what search engines are told.' },
	ai: { title: 'AI', hint: 'What AI tools can read, and what AI crawlers are asked.' },
	system: { title: 'System', hint: 'How the site is put together and run, all set in code.' }
};

const groups  = ref<SettingGroup[] | null>(null);
const error   = ref('');
const form    = ref<Record<string, FormValue>>({});
const initial = ref<Record<string, FormValue>>({});
// Each setting's field and the value it was loaded with, by setting.
const fields  = ref<Record<string, FieldDescription>>({});
const inputs  = ref<Record<string, unknown>>({});
const unset   = ref<string[]>([]);
// What the Media screen's grid needs, when it's showing.
const uploadsInfo = ref<UploadsInfo | null>(null);

const { busy: saving, error: failure, run } = useAction();

const about    = computed(() => screens[props.screen] ?? { title: 'Settings', hint: '' });
const editable = computed(() => Object.keys(initial.value).length > 0);

// The settings whose values changed, then the ones going back to config.
const changed = computed(() => Object.keys(form.value).filter((key) => !same(form.value[key], initial.value[key]) && !unset.value.includes(key)));
const count   = computed(() => changed.value.length + unset.value.length);

async function load(): Promise<void> {
	const screen = props.screen;

	try {
		const answer = await request<{ groups: SettingGroup[] }>('GET', `/settings/${screen}`);
		const values: Record<string, FormValue> = {};

		if (screen !== props.screen) {
			return;
		}

		fields.value = {};
		inputs.value = {};

		uploadsInfo.value = null;

		for (const item of answer.groups.flatMap((group) => group.items)) {
			if (item.kind === 'uploads' && item.setting !== undefined && item.uploads !== undefined) {
				uploadsInfo.value      = item.uploads;
				values[item.setting]   = JSON.stringify(toGrid(item.input, item.uploads));
			} else if (item.setting !== undefined && item.field !== undefined) {
				values[item.setting]       = toForm(item.field, item.input);
				fields.value[item.setting] = item.field;
				inputs.value[item.setting] = item.input;
			}
		}

		groups.value  = answer.groups;
		form.value    = values;
		initial.value = { ...values };
		unset.value   = [];
		error.value   = '';
	} catch (caught) {
		error.value = errorMessage(caught, 'The settings couldn\'t be loaded.');
	}
}

function same(a: FormValue | undefined, b: FormValue | undefined): boolean {
	return a === b;
}

// Whether a setting is one a field set adds (D-343), with no config value
// behind it.
function isSite(setting: string): boolean {
	return setting.startsWith('site.');
}

// The value a setting is sent as, from its form state. A built-in list
// left empty is an empty list (no feed formats); a field set's setting
// left empty is removed.
function outgoing(setting: string, value: FormValue): unknown {
	if (uploadsInfo.value !== null && setting === 'media.uploads' && typeof value === 'string') {
		return fromGrid(JSON.parse(value) as UploadGrid);
	}

	const field = fields.value[setting];

	if (field === undefined) {
		return value;
	}

	const sent = fromForm(field, value, inputs.value[setting]);

	return sent === null && field.type === 'list' && !isSite(setting) ? [] : sent;
}

async function save(): Promise<void> {
	if (count.value === 0 || saving.value) {
		return;
	}

	await run('The settings couldn\'t be saved.', async () => {
		const set = Object.fromEntries(changed.value.map((key) => [key, outgoing(key, form.value[key] ?? '')]));

		await saveSettings({ set, unset: unset.value });
		await load();
		toast('Settings saved');
	});
}

function revert(): void {
	form.value    = { ...initial.value };
	unset.value   = [];
	failure.value = '';
}

// Goes back to the config's value once saved, or keeps the saved one.
function useConfig(setting: string, on: boolean): void {
	unset.value = on ? [...unset.value, setting] : unset.value.filter((key) => key !== setting);

	const saved = initial.value[setting];

	if (on && saved !== undefined) {
		form.value[setting] = saved;
	}
}

// The upload grid, in and out of the form's text.
function gridOf(setting: string, from: Record<string, FormValue>): UploadGrid {
	const value = from[setting];

	return JSON.parse(typeof value === 'string' && value !== '' ? value : '{"all":{"enabled":true,"size":"","path":""},"kinds":{}}') as UploadGrid;
}

function setGrid(setting: string, grid: UploadGrid): void {
	form.value[setting] = JSON.stringify(grid);
}

// A group's hint: the upload grid's says what it does.
function groupHint(group: SettingGroup): string {
	const item = group.items.find((entry) => entry.kind === 'uploads');

	return item?.setting !== undefined ? summary(gridOf(item.setting, form.value)) : group.hint;
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
// Whether a setting is locked by another that's off in the form.
function locked(item: SettingItem): boolean {
	return item.requires !== undefined && form.value[item.requires.setting] === false;
}

function liveHelp(item: SettingItem): string | null {
	if (locked(item)) {
		return `${item.requires?.note ?? ''} ${item.help ?? ''}`.trim();
	}

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

function kindOf(item: SettingItem): string {
	return item.field === undefined ? 'text' : control(item.field);
}

// Whether a setting's control is a group of options, labeled by its row.
function isGroup(item: SettingItem): boolean {
	const kind = kindOf(item);

	return kind === 'radios' || kind === 'checks';
}

// Whether a setting's control wants the row's width, its help under it.
function isWide(item: SettingItem): boolean {
	const kind = kindOf(item);

	return kind === 'lines' || kind === 'textarea';
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

guardLeave(() => count.value > 0);
onBeforeRouteUpdate(() => count.value === 0 || confirmLeave());
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ about.title }}</h1>
			<p class="page-header__hint">{{ about.hint }}</p>
		</div>
	</header>

	<p v-if="screen === 'system'" class="notice"><AdminIcon name="info" /><span>These live in <code>config/</code> and <code>.env</code>; each names its file. After changing them on a site you've compiled, run <code>bin/blush cache:compile</code> again.</span></p>
	<p v-else-if="editable" class="notice"><AdminIcon name="info" /><span>What you save here is kept in <code>user/data/settings.json</code> and wins over <code>config/</code>. The rest are set in code and only shown.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<form v-if="groups" class="setting-panels" @submit.prevent="save">
		<section v-for="group in groups" :key="group.key" class="panel" :aria-labelledby="`settings-${group.key}`">
			<header class="panel__header setting-panels__header">
				<h2 :id="`settings-${group.key}`">{{ group.title }}</h2>
				<p class="panel__hint">{{ groupHint(group) }}</p>
			</header>
			<template v-for="item in group.items" :key="item.key">
				<template v-if="item.kind === 'uploads' && item.setting !== undefined && item.uploads !== undefined">
					<UploadRules
						:model-value="gridOf(item.setting, form)"
						:saved="gridOf(item.setting, initial)"
						:info="item.uploads"
						:disabled="unset.includes(item.setting)"
						@update:model-value="setGrid(item.setting!, $event)"
					/>
					<p class="setting-panels__foot">
						<template v-if="unset.includes(item.setting)">Uses <code>{{ item.file }}</code>'s rules once saved. <button type="button" class="link-button" @click="useConfig(item.setting!, false)">Keep the saved ones</button></template>
						<template v-else-if="item.saved">Saved here. <button type="button" class="link-button" @click="useConfig(item.setting!, true)">Use <code>{{ item.file }}</code>'s rules</button></template>
						<template v-else>From <code>{{ item.file }}</code><template v-if="item.default === true">, the default</template>.</template>
					</p>
				</template>
			</template>
			<div v-if="group.items.some((item) => item.kind !== 'uploads')" class="setting-panels__rows">
				<template v-for="item in group.items" :key="item.key">
					<div v-if="item.kind !== 'uploads'" class="setting" :class="{ 'setting--wide': isWide(item), 'is-off': locked(item) }">
						<template v-if="item.setting !== undefined && item.field !== undefined">
							<div class="setting__label">
								<label v-if="!isGroup(item) && kindOf(item) !== 'checkbox'" :for="`setting-${item.key}`">{{ item.label }}</label>
								<span v-else :id="`setting-${item.key}-label`">{{ item.label }}</span>
							</div>

							<div class="field setting__control" :class="{ 'is-unset': unset.includes(item.setting) }">
								<div v-if="kindOf(item) === 'checkbox'" class="setting__switch">
									<ToggleSwitch
										form
										:checked="form[item.setting] === true"
										:label="item.label"
										:described-by="`setting-${item.key}-help`"
										:locked="unset.includes(item.setting) || locked(item)"
										@change="form[item.setting!] = $event"
									/>
								</div>
								<LocalePicker
									v-else-if="item.locales"
									:id="`setting-${item.key}`"
									:model-value="typeof form[item.setting] === 'string' ? form[item.setting] as string : ''"
									:options="item.locales"
									:described-by="`setting-${item.key}-help`"
									:disabled="unset.includes(item.setting) || locked(item)"
									@update:model-value="form[item.setting!] = $event"
								/>
								<DateFormatPicker
									v-else-if="item.formats"
									:id="`setting-${item.key}`"
									:model-value="typeof form[item.setting] === 'string' ? form[item.setting] as string : ''"
									:options="item.formats"
									:kind="item.setting === 'app.timeFormat' ? 'time' : 'date'"
									:locale="stringValue('app.locale')"
									:described-by="`setting-${item.key}-help`"
									:disabled="unset.includes(item.setting) || locked(item)"
									@update:model-value="form[item.setting!] = $event"
								/>
								<AdminSelect
									v-else-if="item.menu"
									:id="`setting-${item.key}`"
									:model-value="typeof form[item.setting] === 'string' ? form[item.setting] as string : ''"
									:options="item.menu"
									searchable
									:described-by="`setting-${item.key}-help`"
									:disabled="unset.includes(item.setting) || locked(item)"
									@update:model-value="form[item.setting!] = $event"
								/>
								<FieldInput
									v-else
									:model-value="form[item.setting] ?? ''"
									:field="item.field"
									:id="`setting-${item.key}`"
									:described-by="`setting-${item.key}-help`"
									:labelled-by="`setting-${item.key}-label`"
									:disabled="unset.includes(item.setting) || locked(item)"
									@update:model-value="form[item.setting!] = $event"
								/>

								<p v-if="changeWarning(item)" class="setting__warning"><AdminIcon name="triangle-alert" />{{ changeWarning(item) }}</p>
								<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
								<p v-if="item.link" class="setting__links"><a class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a></p>
							</div>

							<div :id="`setting-${item.key}-help`" class="setting__help">
								<p v-if="liveHelp(item)">{{ liveHelp(item) }}</p>
								<p v-if="isSite(item.setting)" class="setting__source">
									<template v-if="unset.includes(item.setting)">Cleared once saved. <button type="button" class="link-button" @click="useConfig(item.setting!, false)">Keep it</button></template>
									<template v-else-if="item.saved">Saved here. <button type="button" class="link-button" @click="useConfig(item.setting!, true)">Clear it</button></template>
									<template v-else>Not saved yet.</template>
								</p>
								<p v-else class="setting__source">
									<template v-if="unset.includes(item.setting)">Uses <code>{{ item.file }}</code>'s value once saved. <button type="button" class="link-button" @click="useConfig(item.setting!, false)">Keep the saved one</button></template>
									<template v-else-if="item.saved">Saved here. <button type="button" class="link-button" @click="useConfig(item.setting!, true)">Use <code>{{ item.file }}</code>'s value</button></template>
									<template v-else>From <code>{{ item.file }}</code><template v-if="item.default === true">, the default</template>.</template>
								</p>
							</div>
						</template>

						<template v-else>
							<div class="setting__label"><span>{{ item.label }}</span></div>
							<div class="setting__control">
								<p class="setting__value">
									<span v-if="item.kind === 'bool'" class="pill" :class="item.warning ? 'pill--warn' : { 'setting__pill--on': item.value === true }">{{ shown(item) }}</span>
									<span v-else :class="{ mono: item.kind === 'mono', 'setting__none': shown(item) === 'None' }">{{ shown(item) }}</span>
									<span v-if="item.default === true" class="tag">Default</span>
								</p>
								<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
								<p v-if="item.link || item.links?.length" class="setting__links">
									<a v-if="item.link" class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a>
									<RouterLink v-for="link in item.links ?? []" :key="link.to" class="setting__link" :to="link.to">{{ link.label }}</RouterLink>
								</p>
							</div>
							<div class="setting__help">
								<p v-if="item.help">{{ item.help }}</p>
								<p v-if="item.file" class="setting__source">Set in <code>{{ item.file }}</code>.</p>
							</div>
						</template>
					</div>
				</template>
			</div>
			<p v-if="group.note" class="setting-panels__foot">
				<template v-for="(part, index) in parts(group.note)" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template>
			</p>
		</section>

		<SaveBar :count="count" :failure="failure" :saving="saving" @revert="revert" />
	</form>

	<div v-else-if="!error" class="setting-panels" aria-hidden="true">
		<div v-for="index in 3" :key="index" class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
	</div>
</template>
