<script setup lang="ts">
/**
 * Media's upload rules (D-406, from the settings sketch): a grid of All
 * Files, then each kind, by whether it may be uploaded, its largest file,
 * and its path under `user/media`. A kind takes All Files' value until it
 * has one of its own: an empty box shows the value it takes, in gray, so
 * a column reads down. All Files turned off turns every kind off; a kind
 * the site allows no types of can't be turned on, and says why.
 *
 * A size is whole megabytes, and can't be typed past what the server
 * takes. A path is a folder name or a pattern; the path box you're in offers
 * its tokens, which land at the caret with the slashes they need, and a
 * pattern shows the file it would make. A path changed from the saved one
 * says, under the grid, that files already uploaded keep their addresses.
 *
 * Where the panel is narrow, the grid stops being one: All Files and
 * each kind are a line saying what they do, opened to show their boxes
 * (All Files, and a kind with rules of its own, start open).
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import type { UploadsInfo } from '../api';
import type { IconName } from '../icons';
import { expand, megabytes, patternOf, sizeProblem, type UploadGrid, type UploadRow } from '../uploads';
import { t } from '../messages';
import AdminIcon from './AdminIcon.vue';
import MessageText from './MessageText.vue';
import ToggleSwitch from './ToggleSwitch.vue';

const props = defineProps<{
	info: UploadsInfo;
	// The rules as saved, for the path warning.
	saved: UploadGrid;
	disabled?: boolean;
}>();

const grid = defineModel<UploadGrid>({ required: true });

const id = useId();

const ICONS: Record<string, IconName> = { image: 'image', video: 'video', audio: 'music', document: 'file-text', file: 'file' };

interface Row {
	key: string;
	label: string;
	icon: IconName;
	all: boolean;
	example: string;
	extensions: string[];
}

const rows = computed<Row[]>(() => [
	{ key: 'all', label: t('upload_rules.all_files', 'All Files'), icon: 'files', all: true, example: props.info.kinds[0]?.example ?? 'photo.jpg', extensions: [] },
	...props.info.kinds.map((kind) => ({ key: kind.key, label: kind.label, icon: ICONS[kind.key] ?? 'file', all: false, example: kind.example, extensions: kind.extensions }))
]);

const server = computed(() => props.info.serverLimit === null ? null : megabytes(props.info.serverLimit));

function rowOf(row: Row): UploadRow {
	return row.all ? grid.value.all : (grid.value.kinds[row.key] ?? { enabled: true, size: '', path: '' });
}

// Writes one box, as a new grid.
function set(row: Row, change: Partial<UploadRow>): void {
	grid.value = row.all
		? { ...grid.value, all: { ...grid.value.all, ...change } }
		: { ...grid.value, kinds: { ...grid.value.kinds, [row.key]: { ...rowOf(row), ...change } } };
}

// Whether files of the row's kind may be uploaded, as the grid stands.
function allowed(row: Row): boolean {
	return grid.value.all.enabled && (row.all || (rowOf(row).enabled && row.extensions.length > 0));
}

function locked(row: Row): boolean {
	return props.disabled === true || (!row.all && (!grid.value.all.enabled || row.extensions.length === 0));
}

function lockReason(row: Row): string | null {
	if (row.all || props.disabled) {
		return null;
	}

	if (row.extensions.length === 0) {
		return t('upload_rules.none_allowed_reason', 'The site allows no {kind}: the types config/media.php allows say what can be.', { kind: row.label.toLowerCase() });
	}

	return grid.value.all.enabled ? null : t('upload_rules.all_off_reason', 'Every upload is off while All Files is.');
}

function sizePlaceholder(row: Row): string {
	const all = grid.value.all.size.trim();

	return row.all ? (server.value === null ? t('upload_rules.none', 'None') : String(server.value)) : (all || (server.value === null ? '—' : String(server.value)));
}

function pathPlaceholder(row: Row): string {
	return row.all ? t('upload_rules.straight_in', 'Straight in user/media') : (grid.value.all.path.trim() || t('upload_rules.straight_in', 'Straight in user/media'));
}

// The file a row's own pattern would make, where it can't be read off
// the box.
function example(row: Row): string | null {
	const own = rowOf(row).path.trim();

	return allowed(row) && own.includes('{') ? expand(own, props.info, row.all ? (props.info.kinds[0]?.key ?? 'image') : row.key, row.example) : null;
}

// What a row does, in a line, for its closed head.
function rowSummary(row: Row): string {
	if (!grid.value.all.enabled) {
		return t('upload_rules.no_uploads', 'No uploads at all');
	}

	if (!allowed(row)) {
		return row.extensions.length === 0 ? t('upload_rules.none_allowed', 'None allowed') : t('upload_rules.turned_off', 'Turned off');
	}

	if (row.all) {
		const size = grid.value.all.size.trim();

		return [t('upload_rules.allowed', 'Allowed'), size === '' ? (server.value === null ? t('upload_rules.any_size', 'any size') : t('upload_rules.up_to', 'up to {size} MB', { size: server.value })) : t('upload_rules.up_to', 'up to {size} MB', { size }), expand(grid.value.all.path.trim(), props.info, props.info.kinds[0]?.key ?? 'image', '').replace(/\/$/, '') || 'user/media'].join(' · ');
	}

	const own   = rowOf(row);
	const parts = [t('upload_rules.allowed', 'Allowed')];

	if (own.size.trim() !== '') {
		parts.push(t('upload_rules.megabytes', '{size} MB', { size: own.size.trim() }));
	}

	if (own.path.trim() !== '') {
		parts.push(expand(own.path.trim(), props.info, row.key, '').replace(/\/$/, '') || 'user/media');
	}

	return parts.length === 1 ? t('upload_rules.allowed_as_all', 'Allowed, as All Files') : parts.join(' · ');
}

// The rows whose path changed from the saved one.
const moved = computed(() => rows.value.filter((row) => {
	const was = row.all ? props.saved.all.path.trim() : (props.saved.kinds[row.key]?.path.trim() ?? '');

	return rowOf(row).path.trim() !== was;
}).map((row) => row.label));

const effectiveMoved = computed(() => props.info.kinds.some((kind) => patternOf(grid.value, kind.key) !== patternOf(props.saved, kind.key)));

function list(names: string[]): string {
	return names.length <= 1 ? (names[0] ?? '') : t('upload_rules.list', '{list} and {last}', { list: names.slice(0, -1).join(', '), last: names[names.length - 1] });
}

// The narrow layout, by the grid's own width.
const root   = ref<HTMLElement | null>(null);
const narrow = ref(false);
const open   = ref<Record<string, boolean>>({});

let observer: ResizeObserver | null = null;

onMounted(() => {
	observer = new ResizeObserver(([entry]) => {
		narrow.value = (entry?.contentRect.width ?? 1000) < 880;
	});

	if (root.value) {
		observer.observe(root.value);
	}
});

onBeforeUnmount(() => observer?.disconnect());

// All Files, and a kind with rules of its own, start open; a kind that
// follows All Files starts closed.
watch(() => props.saved, () => {
	open.value = {
		all: true,
		...Object.fromEntries(props.info.kinds.map((kind) => {
			const row = props.saved.kinds[kind.key];

			return [kind.key, row !== undefined && (!row.enabled || row.size.trim() !== '' || row.path.trim() !== '')];
		}))
	};
}, { immediate: true });

function shown(row: Row): boolean {
	return !narrow.value || open.value[row.key] === true;
}

// A size as typed: digits only, and never more than the server takes.
function typeSize(row: Row, event: Event): void {
	const input  = event.target as HTMLInputElement;
	let digits   = input.value.replace(/\D+/g, '').replace(/^0+/, '');

	if (server.value !== null && digits !== '' && Number(digits) > server.value) {
		digits = String(server.value);
	}

	input.value = digits;
	set(row, { size: digits });
}

// The tokens belong to the path box you're in.
const tokensFor = ref<string | null>(null);

function leave(event: FocusEvent, row: Row): void {
	const cell = (event.currentTarget as HTMLElement | null);

	if (!(event.relatedTarget instanceof Node && cell?.contains(event.relatedTarget)) && tokensFor.value === row.key) {
		tokensFor.value = null;
	}
}

function braced(token: string): string {
	return `{${token}}`;
}

// A token is a folder level, so it lands with the slashes it needs and
// never two of them.
async function insert(row: Row, token: string): Promise<void> {
	const input = document.getElementById(`${id}-path-${row.key}`) as HTMLInputElement | null;

	if (input === null || input.disabled) {
		return;
	}

	const value  = input.value;
	const start  = input.selectionStart ?? value.length;
	const end    = input.selectionEnd ?? start;
	const before = value.slice(0, start);
	const after  = value.slice(end);
	let add      = braced(token);

	if (before !== '' && !before.endsWith('/')) {
		add = `/${add}`;
	}

	if (after !== '' && !after.startsWith('/')) {
		add = `${add}/`;
	}

	set(row, { path: before + add + after });
	await nextTick();
	input.focus();
	input.setSelectionRange(before.length + add.length, before.length + add.length);
}
</script>

<template>
	<div ref="root" class="uploads" :class="{ 'uploads--narrow': narrow }">
		<div class="uploads__note">
			<p><MessageText :text="t('upload_rules.note_all', '{all} sets what every kind does. A kind takes those settings until you fill in one of its own: leave a box empty and it keeps what All Files says, shown in gray.')"><template #all><strong>{{ t('upload_rules.all_files', 'All Files') }}</strong></template></MessageText></p>
			<p>
				<MessageText :text="t('upload_rules.note_path', 'A path is a plain folder name such as {folder}, or a pattern; click into one for its tokens.')"><template #folder><code>uploads</code></template></MessageText>
				<template v-if="server !== null"> <MessageText :text="t('upload_rules.note_server', 'Your server takes files of at most {size} ({upload}, {post}), so a larger number won\'t hold.')"><template #size><strong>{{ t('upload_rules.megabytes', '{size} MB', { size: server }) }}</strong></template><template #upload><code>upload_max_filesize</code></template><template #post><code>post_max_size</code></template></MessageText></template>
			</p>
		</div>

		<table class="uploads__table">
			<thead v-if="!narrow">
				<tr><th scope="col">{{ t('upload_rules.kind', 'Kind') }}</th><th scope="col">{{ t('upload_rules.uploads', 'Uploads') }}</th><th scope="col">{{ t('upload_rules.largest_file', 'Largest file') }}</th><th scope="col">{{ t('upload_rules.path', 'Path') }}</th></tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row.key" class="uploads__row" :class="{ 'is-all': row.all, 'is-off': !allowed(row), 'is-open': shown(row) }">
					<th scope="row">
						<button v-if="narrow" type="button" class="uploads__head" :aria-expanded="shown(row)" @click="open[row.key] = !open[row.key]">
							<AdminIcon name="chevron-right" class="uploads__chevron" />
							<span class="uploads__name"><AdminIcon :name="row.icon" />{{ row.label }}</span>
							<span class="uploads__summary">{{ rowSummary(row) }}</span>
						</button>
						<span v-else class="uploads__kind">
							<span class="uploads__name"><AdminIcon :name="row.icon" />{{ row.label }}</span>
						</span>
					</th>
					<template v-if="shown(row)">
						<td :data-label="t('upload_rules.uploads', 'Uploads')">
							<div class="uploads__cell">
								<ToggleSwitch
									form
									:checked="rowOf(row).enabled && (row.all || row.extensions.length > 0)"
									:label="row.all ? t('upload_rules.files_may', 'Files may be uploaded') : t('upload_rules.kind_may', '{kind} may be uploaded', { kind: row.label })"
									:locked="locked(row)"
									:reason="lockReason(row)"
									@change="set(row, { enabled: $event })"
								/>
							</div>
						</td>
						<td :data-label="t('upload_rules.largest_file', 'Largest file')">
							<div class="uploads__cell uploads__size field">
								<input
									:id="`${id}-size-${row.key}`"
									:value="rowOf(row).size"
									class="mono"
									inputmode="numeric"
									:placeholder="sizePlaceholder(row)"
									:disabled="disabled || !allowed(row)"
									:aria-label="server === null ? t('upload_rules.size_label', 'Largest file for {kind}, in megabytes', { kind: row.label.toLowerCase() }) : t('upload_rules.size_label_max', 'Largest file for {kind}, in megabytes, up to {max}', { kind: row.label.toLowerCase(), max: server })"
									:aria-invalid="sizeProblem(rowOf(row).size, info.serverLimit) !== null ? 'true' : undefined"
									:maxlength="server === null ? 6 : String(server).length"
									@input="typeSize(row, $event)"
								>
								<span class="uploads__unit">{{ t('upload_rules.mb', 'MB') }}</span>
							</div>
							<p v-if="allowed(row) && sizeProblem(rowOf(row).size, info.serverLimit)" class="uploads__problem"><AdminIcon name="triangle-alert" />{{ sizeProblem(rowOf(row).size, info.serverLimit) }}</p>
						</td>
						<td :data-label="t('upload_rules.path', 'Path')" @focusin="tokensFor = row.key" @focusout="leave($event, row)">
							<div class="uploads__path">
								<div class="uploads__prefix" :class="{ 'is-disabled': disabled || !allowed(row) }">
									<span class="uploads__lead" aria-hidden="true">user/media/</span>
									<input
										:id="`${id}-path-${row.key}`"
										:value="rowOf(row).path"
										class="mono"
										spellcheck="false"
										autocomplete="off"
										:placeholder="pathPlaceholder(row)"
										:disabled="disabled || !allowed(row)"
										:aria-label="t('upload_rules.path_label', 'Path for {kind}, under user/media', { kind: row.label.toLowerCase() })"
										@input="set(row, { path: ($event.target as HTMLInputElement).value })"
									>
								</div>
								<p v-if="example(row)" class="uploads__example">→ {{ example(row) }}</p>
								<div v-if="tokensFor === row.key && allowed(row) && !disabled" class="uploads__tokens" role="group" :aria-label="t('upload_rules.tokens_label', 'Tokens for the {kind} path', { kind: row.label.toLowerCase() })">
									<button v-for="token in info.tokens" :key="token" type="button" class="mono" @mousedown.prevent @click="insert(row, token)" v-text="braced(token)" />
								</div>
							</div>
						</td>
					</template>
				</tr>
			</tbody>
		</table>

		<div v-if="!grid.all.enabled" class="uploads__bar">
			<AdminIcon name="info" />
			<p><MessageText :text="t('upload_rules.all_off', 'Nothing can be uploaded while {all} is off. The kinds keep their settings, and take effect again when it\'s turned back on.')"><template #all><strong>{{ t('upload_rules.all_files', 'All Files') }}</strong></template></MessageText></p>
		</div>

		<div v-if="moved.length > 0 && effectiveMoved" class="uploads__bar uploads__bar--warn">
			<AdminIcon name="triangle-alert" />
			<div>
				<p><MessageText :text="info.files !== null ? t('upload_rules.moved_count', '{kept} The {count, plural, one {# file} other {# files} } in the library stay where they are, so no link on the site breaks and nothing has to be inserted again. Only files uploaded after you save take the new path.', { count: info.files }) : t('upload_rules.moved', '{kept} The library\'s files stay where they are, so no link on the site breaks and nothing has to be inserted again. Only files uploaded after you save take the new path.')"><template #kept><strong>{{ t('upload_rules.kept', 'Files already uploaded keep their addresses.') }}</strong></template></MessageText></p>
				<p class="uploads__who"><MessageText :text="t('upload_rules.changed', 'Changed: {names}.')"><template #names><strong>{{ list(moved) }}</strong></template></MessageText></p>
			</div>
		</div>
	</div>
</template>

<style scoped>
.uploads__note {
	padding: var(--s-4) var(--padding-inline);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.uploads__note p {
	max-width: 104ch;
	margin: 0;
}

.uploads__note p + p {
	margin-top: var(--s-2);
}

.uploads__note strong {
	color: var(--color-text);
	font-weight: 500;
}

/* The grid: headed once, so no row explains itself again. */
.uploads__table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	table-layout: fixed;
}

.uploads__table th[scope="col"] {
	padding: var(--s-2) var(--s-3);
	border-top: 1px solid var(--border-color);
	border-bottom: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	font-weight: 600;
	letter-spacing: .06em;
	text-align: left;
	text-transform: uppercase;
}

.uploads__table th[scope="col"]:nth-child(1) {
	width: 210px;
	padding-left: var(--padding-inline);
}

.uploads__table th[scope="col"]:nth-child(2) {
	width: 140px;
}

.uploads__table th[scope="col"]:nth-child(3) {
	width: 170px;
}

.uploads__table th[scope="col"]:last-child {
	padding-right: var(--padding-inline);
}

/* Top-aligned, so a row growing (its tokens, an example) grows down. */
.uploads__table td,
.uploads__table th[scope="row"] {
	padding: var(--s-3);
	border-bottom: 1px solid var(--border-color);
	font-weight: 400;
	text-align: left;
	vertical-align: top;
}

.uploads__table th[scope="row"] {
	padding-left: var(--padding-inline);
}

.uploads__table td:last-child {
	padding-right: var(--padding-inline);
}

.uploads__kind {
	display: grid;
	gap: 2px;
	min-height: var(--ctl);
	align-content: center;
}

.uploads__name {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	color: var(--color-text);
	font-weight: 500;
}

.uploads__name :deep(.icon) {
	color: var(--color-text-muted);
}

.is-all .uploads__name {
	font-weight: 600;
}

.uploads__row.is-off:not(.is-all) .uploads__name {
	color: var(--color-text-muted);
}

.uploads__cell {
	display: flex;
	align-items: center;
	min-height: var(--ctl);
}

.uploads__size {
	gap: var(--s-2);
}

.uploads__size input.mono {
	flex: none;
	width: 84px;
	text-align: right;
}

.uploads__unit {
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
}

.uploads__problem {
	display: flex;
	gap: var(--s-1);
	margin: var(--s-1) 0 0;
	color: var(--color-warning);
	font-size: var(--font-size-small);
}

.uploads__problem :deep(.icon) {
	width: 14px;
	height: 14px;
	margin-top: 1px;
	color: var(--color-warning-indicator);
}

.uploads__path {
	display: grid;
	gap: var(--s-2);
	max-width: 780px;
}

/* The folder every path is under, then the box. */
.uploads__prefix {
	display: flex;
	align-items: stretch;
	min-height: var(--ctl);
	overflow: hidden;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
}

.uploads__prefix:hover {
	border-color: var(--color-text-muted);
}

.uploads__prefix:focus-within {
	border-color: var(--color-accent);
	outline: 2px solid var(--color-accent);
}

.uploads__prefix.is-disabled {
	border-color: var(--border-color);
	background: var(--color-surface-subtle);
}

.uploads__lead {
	display: flex;
	align-items: center;
	padding: 0 var(--s-2) 0 var(--s-3);
	border-right: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
	white-space: nowrap;
}

.uploads__prefix input {
	flex: 1;
	min-width: 0;
	padding: 0 var(--s-3);
	border: 0;
	outline: 0;
	background: none;
	color: var(--color-text);
	font-size: var(--font-size-small);
}

.uploads__prefix input::placeholder,
.uploads__size input::placeholder {
	color: var(--color-text-muted);
}

.uploads__prefix input:disabled {
	cursor: not-allowed;
}

.uploads__example {
	margin: 0;
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	overflow-wrap: anywhere;
}

.uploads__tokens {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.uploads__tokens button {
	height: 24px;
	padding: 0 var(--s-2);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	cursor: pointer;
}

.uploads__tokens button:hover {
	border-color: var(--color-accent-border);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

/* The panel's footer or a bar draws the line under the last row. */
.uploads__table tbody tr:last-child > * {
	border-bottom: 0;
}

/* A row turned off keeps its boxes, dimmed; the reason is beside it. */
.uploads__row.is-off:not(.is-all) td:nth-child(3),
.uploads__row.is-off:not(.is-all) td:nth-child(4) {
	opacity: .5;
}

/* A bar under the grid, about it: a glyph and words, never color alone. */
.uploads__bar {
	display: flex;
	align-items: flex-start;
	gap: var(--s-3);
	padding: var(--s-3) var(--padding-inline) var(--s-4);
	border-top: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.uploads__bar p {
	max-width: 100ch;
	margin: 0;
}

.uploads__bar p + p {
	margin-top: var(--s-1);
}

.uploads__bar > :deep(.icon) {
	margin-top: 1px;
	color: var(--color-text-muted);
}

.uploads__bar--warn {
	background: var(--color-warning-subtle);
}

.uploads__bar--warn > :deep(.icon) {
	color: var(--color-warning-indicator);
}

.uploads__bar--warn strong {
	color: var(--color-warning);
}

.uploads__who {
	color: var(--color-text-muted);
}

.uploads__who strong {
	color: var(--color-text-secondary);
	font-weight: 500;
}

/* Narrow: each kind is a line saying what it does; opened, its boxes are
   rows of label and control, as every other setting is. */
.uploads--narrow .uploads__table,
.uploads--narrow tbody,
.uploads--narrow tr,
.uploads--narrow td,
.uploads--narrow th[scope="row"] {
	display: block;
	width: auto;
}

.uploads--narrow .uploads__table th[scope="row"] {
	padding: 0;
	border-top: 1px solid var(--border-color);
	border-bottom: 0;
}

.uploads--narrow .uploads__table td {
	display: grid;
	grid-template-columns: 160px minmax(0, 1fr);
	gap: var(--s-2) var(--s-4);
	align-items: start;
	padding: var(--s-3) var(--padding-inline);
	border-top: 1px solid var(--border-color);
	border-bottom: 0;
}

.uploads--narrow .uploads__table td::before {
	content: attr(data-label);
	padding-top: 8px;
	color: var(--color-text);
	font-size: var(--font-size-small);
	font-weight: 500;
}

.uploads--narrow .uploads__table td > * {
	grid-column: 2;
}

.uploads--narrow .uploads__size {
	justify-self: start;
}

.uploads__head {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 2px var(--s-2);
	width: 100%;
	padding: var(--s-4) var(--padding-inline);
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.uploads__head:hover {
	background: var(--color-surface-subtle);
}

.uploads__chevron {
	color: var(--color-text-muted);
	transition: transform .14s ease-out;
}

.uploads__head[aria-expanded="true"] .uploads__chevron {
	transform: rotate(90deg);
}

.uploads__summary {
	flex-basis: 100%;
	padding-left: calc(16px + var(--s-2));
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

@container (width < 560px) {
	.uploads--narrow .uploads__table td {
		grid-template-columns: minmax(0, 1fr);
	}

	.uploads--narrow .uploads__table td > * {
		grid-column: 1;
	}

	.uploads--narrow .uploads__table td::before {
		padding-top: 0;
	}
}

@media (prefers-reduced-motion: reduce) {
	.uploads__chevron {
		transition: none;
	}
}
</style>
