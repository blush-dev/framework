<script setup lang="ts">
/**
 * A date and time as a value that opens a month (admin.md §8, The
 * document panel): a Monday-first grid with today ringed and the chosen
 * day tinted, soft rather than solid, then the time as two short fields
 * and an AM/PM switch on a 12-hour clock, never a native time input.
 * Hours read 1 to 12, so noon and midnight are 12. The model stays the
 * form's `YYYY-MM-DDTHH:MM` (empty for none); the 12-hour clock is only
 * how it's shown and typed.
 */

import { computed, nextTick, ref } from 'vue';
import { hour12, monthDays, WEEKDAYS } from '../month';
import { usePopover } from '../popover';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{
	id: string;
	describedBy?: string;
	invalid?: boolean;
}>();

const model = defineModel<string>({ required: true });

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function pad(value: number): string {
	return String(value).padStart(2, '0');
}

// The model as a date, or `null`.
const value = computed(() => {
	const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(model.value);

	return match === null ? null : new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]));
});

function write(date: Date): void {
	model.value = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const shown = computed(() => {
	const date = value.value;

	return date === null ? null : {
		day: `${WEEKDAYS[(date.getDay() + 6) % 7]} ${date.getDate()} ${MONTHS[date.getMonth()]?.slice(0, 3)} ${date.getFullYear()}`,
		time: `${pad(hour12(date.getHours()))}:${pad(date.getMinutes())} ${date.getHours() < 12 ? 'am' : 'pm'}`
	};
});

const button = ref<HTMLButtonElement | null>(null);
const panel  = ref<HTMLElement | null>(null);
const month  = ref(new Date());

const popover                = usePopover(button, panel, { gap: 6 });
const { open, place, close } = popover;

function sameDay(a: Date, b: Date): boolean {
	return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

// Six weeks from the Monday on or before the month's first day.
const days = computed(() => {
	const today = new Date();

	return monthDays(month.value.getFullYear(), month.value.getMonth(), 6).map((day) => ({
		...day,
		today: sameDay(day.date, today),
		chosen: value.value !== null && sameDay(day.date, value.value),
		label: `${WEEKDAYS[(day.date.getDay() + 6) % 7]} ${day.date.getDate()} ${MONTHS[day.date.getMonth()]}`
	}));
});

// The time fields, as typed until they're committed.
const hourText   = ref('');
const minuteText = ref('');

function syncTime(): void {
	const date = value.value ?? new Date();

	hourText.value   = pad(hour12(date.getHours()));
	minuteText.value = pad(date.getMinutes());
}

const afternoon = computed(() => (value.value?.getHours() ?? 9) >= 12);

function base(): Date {
	if (value.value !== null) {
		return new Date(value.value);
	}

	const date = new Date();

	date.setHours(9, 0, 0, 0);

	return date;
}

function pickDay(date: Date): void {
	const next = base();

	next.setFullYear(date.getFullYear(), date.getMonth(), date.getDate());
	write(next);
}

function commitTime(): void {
	const next   = base();
	const hour   = Number.parseInt(hourText.value, 10);
	const minute = Number.parseInt(minuteText.value, 10);
	const pm     = next.getHours() >= 12;

	next.setHours(
		Number.isNaN(hour) || hour < 1 || hour > 12 ? next.getHours() : (hour % 12) + (pm ? 12 : 0),
		Number.isNaN(minute) || minute < 0 || minute > 59 ? next.getMinutes() : minute
	);
	write(next);
	syncTime();
}

function setMeridiem(pm: boolean): void {
	const next = base();

	next.setHours((next.getHours() % 12) + (pm ? 12 : 0));
	write(next);
}

function quick(which: 'now' | 'tomorrow' | 'monday'): void {
	const next = new Date();

	if (which === 'tomorrow') {
		next.setDate(next.getDate() + 1);
		next.setHours(9, 0, 0, 0);
	} else if (which === 'monday') {
		next.setDate(next.getDate() + (((8 - next.getDay()) % 7) || 7));
		next.setHours(9, 0, 0, 0);
	}

	month.value = new Date(next.getFullYear(), next.getMonth(), 1);
	write(next);
	syncTime();
}

function move(by: number): void {
	month.value = new Date(month.value.getFullYear(), month.value.getMonth() + by, 1);
}

async function show(): Promise<void> {
	const date = value.value ?? new Date();

	month.value = new Date(date.getFullYear(), date.getMonth(), 1);
	syncTime();
	await popover.show();
	await nextTick();
	panel.value?.querySelector<HTMLElement>('.date__day.is-chosen, .date__day.is-today')?.focus();
}

function keydown(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		close();
	}
}

function timeKey(event: KeyboardEvent): void {
	if (event.key === 'Enter') {
		event.preventDefault();
		commitTime();
	}
}

defineExpose({ show });
</script>

<template>
	<button
		:id="props.id"
		ref="button"
		type="button"
		class="date"
		aria-haspopup="dialog"
		:aria-expanded="open"
		:aria-describedby="describedBy"
		:aria-invalid="invalid ? 'true' : undefined"
		@click="open ? close() : show()"
	>
		<template v-if="shown">
			<span class="date__day-text">{{ shown.day }}</span>
			<span class="date__time-text">{{ shown.time }}</span>
		</template>
		<span v-else class="date__day-text">Not set</span>
		<AdminIcon name="chevron-down" class="date__caret" />
	</button>

	<Teleport to="body">
		<div v-if="open" ref="panel" class="date-panel" role="dialog" aria-label="Choose a date and time" :style="place ?? { visibility: 'hidden' }" @keydown="keydown">
			<div class="date-panel__head">
				<button type="button" class="button button--ghost button--icon" @click="move(-1)">
					<AdminIcon name="chevron-left" /><span class="visually-hidden">Previous month</span>
				</button>
				<p class="date-panel__month" aria-live="polite">{{ MONTHS[month.getMonth()] }} {{ month.getFullYear() }}</p>
				<button type="button" class="button button--ghost button--icon" @click="move(1)">
					<AdminIcon name="chevron-right" /><span class="visually-hidden">Next month</span>
				</button>
			</div>
			<div class="date-panel__week" aria-hidden="true">
				<span v-for="day in WEEKDAYS" :key="day">{{ day.charAt(0) }}</span>
			</div>
			<div class="date-panel__grid">
				<button
					v-for="day in days"
					:key="day.iso"
					type="button"
					class="date__day"
					:class="{ 'is-out': day.out, 'is-today': day.today, 'is-chosen': day.chosen }"
					:aria-pressed="day.chosen"
					:aria-label="day.label"
					@click="pickDay(day.date)"
				>{{ day.date.getDate() }}</button>
			</div>
			<div class="date-panel__time">
				<span class="date-panel__label">Time</span>
				<input v-model="hourText" class="date-panel__field mono" type="text" inputmode="numeric" maxlength="2" aria-label="Hour" @change="commitTime" @keydown="timeKey" @focus="($event.target as HTMLInputElement).select()">
				<span aria-hidden="true">:</span>
				<input v-model="minuteText" class="date-panel__field mono" type="text" inputmode="numeric" maxlength="2" aria-label="Minute" @change="commitTime" @keydown="timeKey" @focus="($event.target as HTMLInputElement).select()">
				<span class="date-panel__meridiem" role="group" aria-label="Morning or afternoon">
					<button type="button" :aria-pressed="!afternoon" @click="setMeridiem(false)">AM</button>
					<button type="button" :aria-pressed="afternoon" @click="setMeridiem(true)">PM</button>
				</span>
			</div>
			<div class="date-panel__quick">
				<button type="button" class="button button--small" @click="quick('now')">Now</button>
				<button type="button" class="button button--small" @click="quick('tomorrow')">Tomorrow, 9 am</button>
				<button type="button" class="button button--small" @click="quick('monday')">Next Monday</button>
			</div>
		</div>
	</Teleport>
</template>

<style scoped>
/* The value: accent ink, no box, since it's chosen rather than typed. */
.date {
	display: inline-flex;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: 4px 6px;
	margin-left: -6px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--accent);
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.date:hover,
.date[aria-expanded="true"] {
	background: var(--accent-soft);
}

.date[aria-invalid="true"] {
	color: var(--danger);
}

.date__time-text {
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
}

.date__caret {
	flex: none;
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}
</style>

<style>
.date-panel {
	position: fixed;
	z-index: 70;
	display: grid;
	gap: var(--s-3);
	width: 284px;
	padding: var(--s-3);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
}

.date-panel__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.date-panel__month {
	font-weight: 600;
}

.date-panel__week,
.date-panel__grid {
	display: grid;
	grid-template-columns: repeat(7, 1fr);
	gap: 2px;
	text-align: center;
}

.date-panel__week {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.date__day {
	aspect-ratio: 1;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg);
	font: inherit;
	font-variant-numeric: tabular-nums;
	cursor: pointer;
}

.date__day:hover {
	background: var(--surface-2);
}

.date__day.is-out {
	color: var(--fg-3);
}

.date__day.is-today {
	box-shadow: inset 0 0 0 1px var(--border-strong);
}

/* Soft, not solid: it's only saying "this one". */
.date__day.is-chosen {
	background: var(--accent-soft);
	color: var(--accent);
	font-weight: 600;
}

.date-panel__time {
	display: flex;
	align-items: center;
	gap: 6px;
	padding-top: var(--s-3);
	border-top: 1px solid var(--border);
}

.date-panel__label {
	margin-right: auto;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.date-panel__field {
	width: 3.2em;
	min-height: var(--ctl-sm);
	padding: 0;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg);
	text-align: center;
}

.date-panel__field:focus-visible {
	border-color: var(--accent);
	outline-offset: 0;
}

.date-panel__meridiem {
	display: inline-flex;
	margin-left: 6px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	overflow: hidden;
}

.date-panel__meridiem button {
	min-height: calc(var(--ctl-sm) - 2px);
	padding: 0 9px;
	border: 0;
	background: var(--surface);
	color: var(--fg-2);
	font: inherit;
	font-size: var(--text-sm);
	cursor: pointer;
}

.date-panel__meridiem button[aria-pressed="true"] {
	background: var(--accent-soft);
	color: var(--accent);
	font-weight: 600;
}

.date-panel__quick {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}
</style>
