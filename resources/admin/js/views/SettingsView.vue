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
 * are. Writing's embeds (D-633) are one setting, the providers turned
 * on (none by default, D-705), drawn as a chip for each provider (`EmbedChips`, the Writing
 * sketch's, D-695), with how many are on and buttons to turn them all
 * on or off in the panel's header, and where it's set under them.
 *
 * A choice of a few short options (`segmented`, Writing's Raw HTML) is
 * drawn as them side by side, with the chosen one's details under them.
 * A panel whose settings are all from one file (`source`, D-695) says
 * it once in its foot, so each row says where it's from only when it's
 * saved here. A panel of only switches has each row's words first and
 * its switch at the end, as the Writing sketch does, and so does a
 * `compact` one (AI's, from the AI sketch, D-765), with a shown value
 * under its label and its links and `aside` at the end. AI's crawlers
 * are one list drawn as a switch for each option (`switches`), and its
 * tools a list grouped by who registered them (`OfferedTools`), whose
 * descriptions the panel's header opens or closes together.
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
import MessageText from '../components/MessageText.vue';
import DateFormatPicker from '../components/DateFormatPicker.vue';
import EmbedChips from '../components/EmbedChips.vue';
import LocalePicker from '../components/LocalePicker.vue';
import OfferedTools from '../components/OfferedTools.vue';
import SaveBar from '../components/SaveBar.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import UploadRules from '../components/UploadRules.vue';
import { useAction } from '../action';
import { cancelEmailChange, errorMessage, request, saveSettings, type FieldDescription, type SettingGroup, type SettingItem, type UploadsInfo } from '../api';
import { choiceLabel, control, fromForm, isChoiceGroup, toForm, type FormValue } from '../fields';
import { locale, t } from '../messages';
import { when } from '../people';
import { screenTitle, screenTrail } from '../screen';
import { copyText, toast } from '../toast';
import { fromGrid, summary, toGrid, type UploadGrid } from '../uploads';

const props = defineProps<{ screen: string }>();

const screens: Record<string, { title: string; hint: string }> = {
	general: { title: t('settings_view.general', 'General'), hint: t('settings_view.general_hint', 'The site\'s name, language, and time, and where it runs.') },
	reading: { title: t('settings_view.reading', 'Reading'), hint: t('settings_view.reading_hint', 'What the homepage shows, and the feeds.') },
	writing: { title: t('settings_view.writing', 'Writing'), hint: t('settings_view.writing_hint', 'How what\'s written renders, and what raw HTML in it does.') },
	media: { title: t('settings_view.media', 'Media'), hint: t('settings_view.media_hint', 'What may be uploaded, how large, and where it\'s kept.') },
	email: { title: t('settings_view.email', 'Email'), hint: t('settings_view.email_hint', 'Who the site\'s email is from, and how it\'s sent.') },
	search: { title: t('settings_view.search', 'Addresses and Search'), hint: t('settings_view.search_hint', 'How addresses are written, and what search engines are told.') },
	ai: { title: t('settings_view.ai', 'AI'), hint: t('settings_view.ai_hint', 'What AI tools can read, and what AI crawlers are asked.') },
	api: { title: t('settings_view.api', 'API'), hint: t('settings_view.api_hint', 'What other sites, apps, and scripts can read, and what a token may change.') },
	system: { title: t('settings_view.system', 'System'), hint: t('settings_view.system_hint', 'How the site is put together and run, all set in code.') }
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

// Email's Send a Test (D-846): sent now, so a transport that fails says
// why.
const { busy: testing, error: testError, run: runTest } = useAction();

async function sendTest(): Promise<void> {
	await runTest(t('settings_view.test_failed', 'The test wasn\'t sent.'), async () => {
		const answer = await request<{ sent: string }>('POST', '/settings/email/test');

		toast(t('settings_view.test_sent', 'Sent a test to {email}', { email: answer.sent }), { kind: 'good' });
	});
}

// A new site address waits on its confirmation link (D-849) until it's
// used, or cancelled here.
const { busy: cancelBusy, run: runCancel } = useAction();

async function cancelChange(): Promise<void> {
	await runCancel(t('settings_view.email_change_cancel_failed', 'The change couldn\'t be cancelled.'), async () => {
		await cancelEmailChange('site');
		await load();
		toast(t('settings_view.email_change_cancelled', 'Cancelled the change of email address'));
	}, (caught) => {
		toast(errorMessage(caught, t('settings_view.email_change_cancel_failed', 'The change couldn\'t be cancelled.')), { kind: 'warn' });
	});
}

const about = computed(() => screens[props.screen] ?? { title: t('settings_view.settings', 'Settings'), hint: '' });

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
		error.value = errorMessage(caught, t('settings_view.load_failed', 'The settings couldn\'t be loaded.'));
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

	await run(t('settings_view.save_failed', 'The settings couldn\'t be saved.'), async () => {
		const set = Object.fromEntries(changed.value.map((key) => [key, outgoing(key, form.value[key] ?? '')]));

		const change = await saveSettings({ set, unset: unset.value });

		await load();
		toast(change === null
			? t('settings_view.saved', 'Settings saved')
			: t('settings_view.email_change_sent', 'Check {email} for a link to confirm it as the site\'s email address', { email: change.email }));
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

// The embed providers turned on, in and out of the form's lines.
function onList(setting: string): string[] {
	const value = form.value[setting];

	return typeof value === 'string' ? value.split('\n').map((line) => line.trim()).filter((line) => line !== '') : [];
}

function setProvider(setting: string, name: string, on: boolean): void {
	const names = onList(setting).filter((entry) => entry !== name);

	form.value[setting] = (on ? [...names, name] : names).sort().join('\n');
}

// Turns every provider on or off.
function setProviders(item: SettingItem, on: boolean): void {
	if (item.setting !== undefined) {
		form.value[item.setting] = on ? (item.providers ?? []).map((provider) => provider.name).sort().join('\n') : '';
	}
}

// The embeds setting in a group, if it has one.
function embedsOf(group: SettingGroup): SettingItem | null {
	return group.items.find((item) => item.kind === 'embeds' && item.setting !== undefined && item.providers !== undefined) ?? null;
}

// How many of an embeds setting's providers are on.
function providersOn(item: SettingItem): number {
	const names = onList(item.setting ?? '');

	return (item.providers ?? []).filter((provider) => names.includes(provider.name)).length;
}

// What a panel that names its file says in its foot: nothing where every
// row is saved here and says so, and "the defaults" where the rest are.
function sourceNote(group: SettingGroup): string | null {
	const edited = group.items.filter((item) => item.setting !== undefined);
	const rest   = edited.filter((item) => item.saved !== true && !unset.value.includes(item.setting!));

	if (group.source === undefined || rest.length === 0) {
		return null;
	}

	const lead = rest.length < edited.length ? 'rest' : 'all';
	const tail = rest.every((item) => item.default === true) ? (rest.length === 1 ? 'one' : 'many') : 'none';

	return t('settings_view.source_note', '{lead, select, rest {The rest are from} other {From}} `{source}`{tail, select, one {, the default} many {, the defaults} other {}}.', { lead, source: group.source, tail });
}

// Whether a group is only switches, each drawn with its words first and
// its switch at the row's end (the Writing sketch's, D-695).
function onlySwitches(group: SettingGroup): boolean {
	return group.items.every((item) => item.setting !== undefined && item.field !== undefined && kindOf(item) === 'toggle');
}

// Whether a group is drawn as compact rows: only switches, or a group
// the server marks (D-765).
function compact(group: SettingGroup): boolean {
	return group.compact === true || onlySwitches(group);
}

// What a shown setting's value reads as under its label in a compact
// row, if anything.
function line(item: SettingItem): string {
	return Array.isArray(item.value) && item.value.length === 0 ? t('settings_view.none', 'None') : shown(item);
}

// Whether a `switches` setting has an option turned on in the form.
function switchedOn(setting: string, option: string): boolean {
	return onList(setting).includes(option);
}

// Turns one option on or off, kept in the options' order, so turning it
// back leaves nothing changed.
function setSwitch(item: SettingItem, option: string, on: boolean): void {
	const setting = item.setting ?? '';
	const chosen  = onList(setting).filter((entry) => entry !== option);

	if (on) {
		chosen.push(option);
	}

	form.value[setting] = (item.field?.item?.options ?? []).filter((entry) => chosen.includes(entry)).join('\n');
}

// The tools setting in a group, if it has one, and which tools'
// descriptions are open.
function toolsOf(group: SettingGroup): SettingItem | null {
	return group.items.find((item) => item.kind === 'tools' && item.sources !== undefined) ?? null;
}

const openTools = ref<string[]>([]);

function toolNames(item: SettingItem): string[] {
	return (item.sources ?? []).flatMap((source) => source.tools.map((tool) => tool.name));
}

function toggleTool(name: string): void {
	openTools.value = openTools.value.includes(name) ? openTools.value.filter((entry) => entry !== name) : [...openTools.value, name];
}

function toggleTools(item: SettingItem): void {
	const names = toolNames(item);

	openTools.value = openTools.value.length === names.length ? [] : names;
}

// The chosen option's details, for a segmented choice.
function chosenDetail(item: SettingItem): string | null {
	const value = item.setting === undefined ? '' : form.value[item.setting];

	return typeof value === 'string' ? item.field?.details?.[value]?.text ?? null : null;
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
		return new Intl.DateTimeFormat(locale(), { timeZone: zone, weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date());
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

		return time === '' ? null : t('settings_view.time_there', 'It\'s {time} there now.', { time });
	}

	if (item.setting === 'routes.trailingSlash') {
		return `${t('settings_view.addresses_look_like', 'Addresses look like {example}.', { example: `/about${form.value['routes.trailingSlash'] === true ? '/' : ''}` })} ${item.help ?? ''}`;
	}

	return item.help;
}

// A warning for a change that reaches far.
function changeWarning(item: SettingItem): string | null {
	if (item.setting === 'routes.trailingSlash' && changed.value.includes('routes.trailingSlash')) {
		return t('settings_view.trailing_slash_warning', 'Every address on the site changes. The old ones redirect, but update the links you control.');
	}

	if (item.setting === 'sitemap.enabled' && form.value['sitemap.enabled'] === false && changed.value.includes('sitemap.enabled')) {
		return t('settings_view.sitemap_warning', 'Search engines lose the sitemap, and robots.txt goes too.');
	}

	return null;
}

function kindOf(item: SettingItem): string {
	return item.field === undefined ? 'text' : control(item.field);
}

// Whether a setting's control is a group of options, labeled by its row.
function isGroup(item: SettingItem): boolean {
	const kind = kindOf(item);

	return isChoiceGroup(kind);
}

// Whether a setting's control wants the row's width, its help under it.
function isWide(item: SettingItem): boolean {
	const kind = kindOf(item);

	return kind === 'textarea';
}

// Text with backticks marking code, as parts.
function parts(text: string): { text: string; code: boolean }[] {
	return text.split('`').map((part, index) => ({ text: part, code: index % 2 === 1 }));
}

function shown(item: SettingItem): string {
	if (typeof item.value === 'boolean') {
		return item.value ? t('settings_view.on', 'On') : t('settings_view.off', 'Off');
	}

	if (Array.isArray(item.value)) {
		return item.value.length > 0 ? item.value.join(', ') : t('settings_view.none', 'None');
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
	screenTrail.value = [{ label: t('settings_view.settings', 'Settings'), to: { name: 'settings', params: { screen: 'general' } } }];
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

	<p v-if="screen === 'system'" class="notice"><AdminIcon name="info" /><span><MessageText :text="t('settings_view.system_notice', 'These live in {config} and {env}; each names its file. After changing them on a site you\'ve compiled, run {command} again.')"><template #config><code>config/</code></template><template #env><code>.env</code></template><template #command><code>bin/blush cache:compile</code></template></MessageText></span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<form v-if="groups" class="setting-panels" @submit.prevent="save">
		<section v-for="group in groups" :key="group.key" class="panel" :aria-labelledby="`settings-${group.key}`">
			<header class="panel__header setting-panels__header">
				<h2 :id="`settings-${group.key}`">{{ group.title }}</h2>
				<p class="panel__hint">{{ groupHint(group) }}</p>
				<div v-if="embedsOf(group)" class="panel__actions">
					<span class="panel__hint">{{ t('settings_view.providers_on', '{on} of {total} on', { on: providersOn(embedsOf(group)!), total: embedsOf(group)!.providers?.length ?? 0 }) }}</span>
					<button type="button" class="button button--ghost button--small" :disabled="unset.includes(embedsOf(group)!.setting!) || providersOn(embedsOf(group)!) === embedsOf(group)!.providers?.length" @click="setProviders(embedsOf(group)!, true)">{{ t('settings_view.turn_all_on', 'Turn All On') }}</button>
					<button type="button" class="button button--ghost button--small" :disabled="unset.includes(embedsOf(group)!.setting!) || providersOn(embedsOf(group)!) === 0" @click="setProviders(embedsOf(group)!, false)">{{ t('settings_view.turn_all_off', 'Turn All Off') }}</button>
				</div>
				<div v-else-if="toolsOf(group)" class="panel__actions">
					<span class="panel__hint">{{ t('settings_view.tools_count', '{tools, plural, one {# tool} other {# tools} } from {sources, plural, one {# source} other {# sources} }', { tools: toolNames(toolsOf(group)!).length, sources: toolsOf(group)!.sources!.length }) }}</span>
					<button type="button" class="button button--ghost button--small" @click="toggleTools(toolsOf(group)!)">{{ openTools.length === toolNames(toolsOf(group)!).length ? t('settings_view.hide_descriptions', 'Hide Descriptions') : t('settings_view.show_descriptions', 'Show Descriptions') }}</button>
				</div>
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
						<template v-if="unset.includes(item.setting)"><MessageText :text="t('settings_view.uses_rules', 'Uses {file}\'s rules once saved. {action}')"><template #file><code>{{ item.file }}</code></template><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, false)">{{ t('settings_view.keep_saved_ones', 'Keep the saved ones') }}</button></template></MessageText></template>
						<template v-else-if="item.saved"><MessageText :text="t('settings_view.saved_here', 'Saved here. {action}')"><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, true)"><MessageText :text="t('settings_view.use_rules', 'Use {file}\'s rules')"><template #file><code>{{ item.file }}</code></template></MessageText></button></template></MessageText></template>
						<template v-else><MessageText :text="t('settings_view.from_file', 'From {file}{isDefault, select, true {, the default} other {} }.', { isDefault: String(item.default === true) })"><template #file><code>{{ item.file }}</code></template></MessageText></template>
					</p>
				</template>
			</template>
			<div v-if="group.items.some((item) => item.kind !== 'uploads')" class="setting-panels__rows">
				<template v-for="item in group.items" :key="item.key">
					<EmbedChips
						v-if="item.kind === 'embeds' && item.setting !== undefined && item.providers !== undefined"
						:providers="item.providers"
						:on="onList(item.setting)"
						:locked="unset.includes(item.setting)"
						@change="(name, on) => setProvider(item.setting!, name, on)"
					/>
					<OfferedTools
						v-else-if="item.kind === 'tools' && item.sources !== undefined"
						:sources="item.sources"
						:open="openTools"
						@toggle="toggleTool"
					/>
					<template v-else-if="item.kind === 'switches' && item.setting !== undefined && item.field?.item !== undefined">
						<div v-for="option in item.field.item.options ?? []" :key="option" class="setting setting--switch">
							<div class="setting__label"><span>{{ choiceLabel(item.field, option) }}</span></div>
							<div class="field setting__control" :class="{ 'is-unset': unset.includes(item.setting) }">
								<div class="setting__switch">
									<ToggleSwitch
										form
										:checked="switchedOn(item.setting, option)"
										:label="t('settings_view.switch_label', '{option}: {setting}', { option: choiceLabel(item.field, option), setting: item.label.toLowerCase() })"
										:words="item.words"
										:described-by="`setting-${item.key}-${option}-help`"
										:locked="unset.includes(item.setting)"
										@change="setSwitch(item, option, $event)"
									/>
								</div>
							</div>
							<div :id="`setting-${item.key}-${option}-help`" class="setting__help">
								<p v-if="item.field.details?.[option]?.text">{{ item.field.details[option]!.text }}</p>
								<p v-if="item.field.details?.[option]?.code" class="setting__code">{{ item.field.details[option]!.code }}</p>
							</div>
						</div>
					</template>
					<div v-else-if="item.kind !== 'uploads'" class="setting" :class="{ 'setting--wide': isWide(item) && !compact(group), 'setting--switch': compact(group), 'is-off': locked(item) }">
						<template v-if="item.setting !== undefined && item.field !== undefined">
							<div class="setting__label">
								<label v-if="!isGroup(item) && kindOf(item) !== 'toggle'" :for="`setting-${item.key}`">{{ item.label }}</label>
								<span v-else :id="`setting-${item.key}-label`">{{ item.label }}</span>
							</div>

							<div class="field setting__control" :class="{ 'is-unset': unset.includes(item.setting) }">
								<div v-if="kindOf(item) === 'toggle'" class="setting__switch">
									<template v-if="compact(group)">
										<span v-if="item.aside" class="setting__aside">{{ item.aside }}</span>
										<a v-if="item.link" class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a>
									</template>
									<ToggleSwitch
										form
										:checked="form[item.setting] === true"
										:label="item.label"
										:described-by="`setting-${item.key}-help`"
										:locked="unset.includes(item.setting) || locked(item)"
										@change="form[item.setting!] = $event"
									/>
								</div>
								<template v-else-if="item.kind === 'segmented'">
									<div class="segmented" role="group" :aria-labelledby="`setting-${item.key}-label`" :aria-describedby="`setting-${item.key}-help`">
										<button
											v-for="option in item.field.options ?? []"
											:key="option"
											type="button"
											:aria-pressed="form[item.setting] === option"
											:disabled="unset.includes(item.setting) || locked(item)"
											@click="form[item.setting!] = option"
										>{{ choiceLabel(item.field, option) }}</button>
									</div>
									<p v-if="chosenDetail(item)" class="setting__detail">{{ chosenDetail(item) }}</p>
								</template>
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

								<template v-if="!compact(group)">
									<p v-if="changeWarning(item)" class="setting__warning"><AdminIcon name="triangle-alert" />{{ changeWarning(item) }}</p>
									<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
									<p v-if="item.link" class="setting__links"><a class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a></p>
								</template>
							</div>

							<div :id="`setting-${item.key}-help`" class="setting__help">
								<p v-if="liveHelp(item)">{{ liveHelp(item) }}</p>
								<p v-if="item.emailChange" class="setting__source"><MessageText :text="t('settings_view.email_change_waiting', 'Changes to {email} once the link sent there is used, by {date}. {action}', { email: item.emailChange.email, date: when(item.emailChange.expires) })"><template #action><button type="button" class="link-button" :disabled="cancelBusy" @click="cancelChange">{{ t('settings_view.cancel_change', 'Cancel the change') }}</button></template></MessageText></p>
								<template v-if="compact(group)">
									<p v-if="changeWarning(item)" class="setting__warning"><AdminIcon name="triangle-alert" />{{ changeWarning(item) }}</p>
									<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
								</template>
								<p v-if="isSite(item.setting)" class="setting__source">
									<template v-if="unset.includes(item.setting)"><MessageText :text="t('settings_view.cleared_once_saved', 'Cleared once saved. {action}')"><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, false)">{{ t('settings_view.keep_it', 'Keep it') }}</button></template></MessageText></template>
									<template v-else-if="item.saved"><MessageText :text="t('settings_view.saved_here', 'Saved here. {action}')"><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, true)">{{ t('settings_view.clear_it', 'Clear it') }}</button></template></MessageText></template>
									<template v-else>{{ t('settings_view.not_saved_yet', 'Not saved yet.') }}</template>
								</p>
								<p v-else-if="!group.source || item.saved || unset.includes(item.setting)" class="setting__source">
									<template v-if="unset.includes(item.setting)"><MessageText :text="t('settings_view.uses_value', 'Uses {file}\'s value once saved. {action}')"><template #file><code>{{ item.file }}</code></template><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, false)">{{ t('settings_view.keep_saved_one', 'Keep the saved one') }}</button></template></MessageText></template>
									<template v-else-if="item.saved"><MessageText :text="t('settings_view.saved_here', 'Saved here. {action}')"><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, true)"><MessageText :text="t('settings_view.use_value', 'Use {file}\'s value')"><template #file><code>{{ item.file }}</code></template></MessageText></button></template></MessageText></template>
									<template v-else><MessageText :text="t('settings_view.from_file', 'From {file}{isDefault, select, true {, the default} other {} }.', { isDefault: String(item.default === true) })"><template #file><code>{{ item.file }}</code></template></MessageText></template>
								</p>
							</div>
						</template>

						<template v-else-if="compact(group)">
							<div class="setting__label">
								<span>{{ item.label }}</span>
								<p v-if="item.copy" class="setting__address"><code>{{ item.value }}</code><button type="button" class="button button--small" @click="copyText(String(item.value), t('settings_view.the_address', 'the address'), t('settings_view.the_address', 'the address'))"><AdminIcon name="copy" />{{ t('settings_view.copy', 'Copy') }}</button></p>
								<p v-else-if="line(item) !== ''" class="setting__line" :class="{ 'setting__none': line(item) === t('settings_view.none', 'None'), mono: item.kind === 'mono' }">{{ line(item) }}</p>
							</div>
							<div v-if="item.aside || item.link || item.links?.length" class="setting__control">
								<span v-if="item.aside" class="setting__aside">{{ item.aside }}</span>
								<a v-if="item.link" class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a>
								<RouterLink v-for="link in item.links ?? []" :key="link.to" class="setting__link" :to="link.to">{{ link.label }}</RouterLink>
							</div>
							<div class="setting__help">
								<p v-if="item.help">{{ item.help }}</p>
								<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
								<p v-if="item.file" class="setting__source"><MessageText :text="t('settings_view.set_in', 'Set in {file}.')"><template #file><code>{{ item.file }}</code></template></MessageText></p>
							</div>
						</template>

						<template v-else>
							<div class="setting__label"><span>{{ item.label }}</span></div>
							<div class="setting__control">
								<p class="setting__value">
									<span v-if="item.kind === 'bool'" class="pill" :class="item.warning ? 'pill--warn' : { 'setting__pill--on': item.value === true }">{{ shown(item) }}</span>
									<span v-else :class="{ mono: item.kind === 'mono', 'setting__none': shown(item) === t('settings_view.none', 'None') }">{{ shown(item) }}</span>
									<span v-if="item.default === true" class="tag">{{ t('settings_view.default', 'Default') }}</span>
									<button v-if="item.kind === 'test'" type="button" class="button button--small" :disabled="testing || item.value === ''" @click="sendTest"><AdminIcon name="mail" />{{ testing ? t('settings_view.sending', 'Sending…') : t('settings_view.send_test', 'Send a Test') }}</button>
									<button v-if="item.copy" type="button" class="button button--small" @click="copyText(String(item.value), t('settings_view.the_address', 'the address'), t('settings_view.the_address', 'the address'))"><AdminIcon name="copy" />{{ t('settings_view.copy', 'Copy') }}</button>
								</p>
								<p v-if="item.warning" class="setting__warning"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
								<p v-if="item.kind === 'test' && testError" class="setting__warning" role="alert"><AdminIcon name="triangle-alert" />{{ testError }}</p>
								<p v-if="item.link || item.links?.length" class="setting__links">
									<a v-if="item.link" class="setting__link" :href="item.link.href" target="_blank" rel="noopener">{{ item.link.label }}<AdminIcon name="arrow-up-right" /></a>
									<RouterLink v-for="link in item.links ?? []" :key="link.to" class="setting__link" :to="link.to">{{ link.label }}</RouterLink>
								</p>
							</div>
							<div class="setting__help">
								<p v-if="item.help">{{ item.help }}</p>
								<p v-if="item.file" class="setting__source"><MessageText :text="t('settings_view.set_in', 'Set in {file}.')"><template #file><code>{{ item.file }}</code></template></MessageText></p>
							</div>
						</template>
					</div>
				</template>
			</div>
			<template v-for="item in group.items" :key="`${item.key}-source`">
				<p v-if="(item.kind === 'embeds' || item.kind === 'switches') && item.setting !== undefined" class="setting-panels__foot">
					{{ item.help }}
					<template v-if="unset.includes(item.setting)"><MessageText :text="t('settings_view.uses_file', 'Uses {file}\'s once saved. {action}')"><template #file><code>{{ item.file }}</code></template><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, false)">{{ t('settings_view.keep_saved_ones', 'Keep the saved ones') }}</button></template></MessageText></template>
					<template v-else-if="item.saved"><MessageText :text="t('settings_view.saved_here', 'Saved here. {action}')"><template #action><button type="button" class="link-button" @click="useConfig(item.setting!, true)"><MessageText :text="t('settings_view.use_file', 'Use {file}\'s')"><template #file><code>{{ item.file }}</code></template></MessageText></button></template></MessageText></template>
					<template v-else><MessageText :text="t('settings_view.from_file', 'From {file}{isDefault, select, true {, the default} other {} }.', { isDefault: String(item.default === true) })"><template #file><code>{{ item.file }}</code></template></MessageText></template>
				</p>
			</template>
			<template v-for="item in group.items" :key="`${item.key}-warning`">
				<p v-if="item.kind === 'switches' && item.warning" class="setting-panels__foot setting-panels__foot--warn"><AdminIcon name="triangle-alert" />{{ item.warning }}</p>
			</template>
			<p v-if="sourceNote(group)" class="setting-panels__foot">
				<template v-for="(part, index) in parts(sourceNote(group)!)" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template>
			</p>
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
