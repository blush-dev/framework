<script setup lang="ts">
/**
 * A menu's settings, in a dialog over its screen (from the menus
 * sketch): its label, its name, the block that shows it in an
 * entry, and the theme's locations that show it. Nothing is saved here:
 * Done hands the choices to the screen, which saves them with the items
 * (Publish or Update); Cancel keeps what was there.
 *
 * Renaming is allowed: the locations follow the new name on their own,
 * but an entry that shows the menu by its old name doesn't, which the
 * dialog says.
 */

import { computed, ref } from 'vue';
import { copyText } from '../toast';
import { menuSlug, MENU_NAME, type MenuLocation } from '../menus';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import MessageText from './MessageText.vue';
import { t } from '../messages';

export interface MenuSettingsValue {
	label: string;
	name: string;
	locations: string[];
}

const props = defineProps<{
	value: MenuSettingsValue;
	// The name it's saved under, or `null` for a draft.
	saved: string | null;
	locations: MenuLocation[];
	menus: { name: string; label: string }[];
}>();

const emit = defineEmits<{
	done: [value: MenuSettingsValue];
	close: [];
}>();

const label  = ref(props.value.label);
const name   = ref(props.value.name);
const chosen = ref<string[]>([...props.value.locations]);

const snippet = computed(() => `::menu{name=${name.value || '…'}}`);

const problem = computed(() => {
	if (name.value === '') {
		return t('menu_settings.errors.name_needed', 'A menu needs a name.');
	}

	if (name.value !== props.saved && props.menus.some((menu) => menu.name === name.value)) {
		return t('menu_settings.errors.name_taken', 'There\'s already a menu named {name}. Pick another name.', { name: name.value });
	}

	return MENU_NAME.test(name.value) ? '' : t('menu_settings.errors.name_start', 'Start the name with a letter or digit.');
});

const renamed = computed(() => props.saved !== null && name.value !== props.saved);

function typeName(event: Event): void {
	name.value = menuSlug((event.target as HTMLInputElement).value);
}

function labelOf(menu: string): string {
	return props.menus.find((item) => item.name === menu)?.label || menu;
}

function toggle(location: string, on: boolean): void {
	chosen.value = on ? [...chosen.value, location] : chosen.value.filter((item) => item !== location);
}

// What a location will show once the menu's saved.
function state(location: MenuLocation): string {
	const on   = chosen.value.includes(location.name);
	const mine = props.saved !== null && location.menu === props.saved;
	const none = location.defaults > 0 ? 'default' : 'nothing';

	if (on) {
		return mine ? t('menu_settings.state.shows_this', 'Shows this menu.') : location.menu !== null ? t('menu_settings.state.replaces', 'Replaces {menu} when saved.', { menu: labelOf(location.menu) }) : t('menu_settings.state.shows_once_saved', 'Shows this menu once saved.');
	}

	if (mine) {
		return t('menu_settings.state.goes_back', '{none, select, default {Goes back to the theme\'s default when saved.} other {Goes back to nothing when saved.}}', { none });
	}

	return location.menu !== null ? t('menu_settings.state.shows', 'Shows {menu}.', { menu: labelOf(location.menu) }) : t('menu_settings.state.shows_none', '{none, select, default {Shows the theme\'s default.} other {Shows nothing.}}', { none });
}

function depthOf(location: MenuLocation): string {
	return location.depth === null ? t('menu_settings.depth.any', 'Any depth') : location.depth === 1 ? t('menu_settings.depth.one', 'One level') : t('menu_settings.depth.up_to', 'Up to {depth, plural, one {# level} other {# levels}}', { depth: location.depth });
}

function done(): void {
	if (problem.value === '') {
		emit('done', { label: label.value.trim(), name: name.value, locations: props.locations.map((location) => location.name).filter((location) => chosen.value.includes(location)) });
	}
}
</script>

<template>
	<AdminModal open :title="t('menu_settings.title', 'Menu Settings')" @close="emit('close')">
		<form id="menu-settings" class="form-stack" novalidate @submit.prevent="done">
			<p class="menu-settings__lead">{{ saved === null ? t('menu_settings.lead_publish', 'Saved with the items when you press Publish.') : t('menu_settings.lead_update', 'Saved with the items when you press Update.') }}</p>

			<div class="field">
				<label for="menu-settings-label">{{ t('menu_settings.label', 'Label') }}</label>
				<input id="menu-settings-label" v-model="label" autocomplete="off" autofocus aria-describedby="menu-settings-label-help">
				<p id="menu-settings-label-help" class="field__help">{{ t('menu_settings.label_help', 'Names the navigation for screen readers. Without one, the theme\'s name for the location is used.') }}</p>
			</div>

			<div class="field">
				<label for="menu-settings-name">{{ t('menu_settings.name', 'Name') }}</label>
				<input id="menu-settings-name" :value="name" class="mono" autocomplete="off" spellcheck="false" :aria-invalid="problem !== ''" aria-describedby="menu-settings-name-help" @input="typeName">
				<p v-if="problem" id="menu-settings-name-help" class="field__error"><AdminIcon name="triangle-alert" />{{ problem }}</p>
				<p v-else id="menu-settings-name-help" class="field__help">{{ t('menu_settings.name_help', 'Themes and the block find the menu by its name.') }}</p>
				<p v-if="renamed && !problem" class="notice notice--warn notice--small" role="status">
					<AdminIcon name="triangle-alert" />
					<span><strong>{{ t('menu_settings.renaming', 'Renaming changes how entries find this menu.') }}</strong> <MessageText :text="t('menu_settings.renaming_help', 'Its locations follow the new name on their own, but an entry that shows it with {snippet} shows nothing there until it\'s changed.')"><template #snippet><code>::menu{name={{ saved }}}</code></template></MessageText></span>
				</p>
			</div>

			<div class="field">
				<span class="field__label">{{ t('menu_settings.in_entry', 'In an Entry') }}</span>
				<div class="menu-settings__snippet">
					<code class="mono">{{ snippet }}</code>
					<button type="button" class="button button--small" :disabled="name === ''" @click="copyText(snippet, t('menu_settings.the_block', 'the block'))"><AdminIcon name="copy" />{{ t('menu_settings.copy', 'Copy') }}</button>
				</div>
			</div>

			<fieldset class="fieldset">
				<legend>{{ t('menu_settings.locations', 'Locations') }}</legend>
				<p v-if="!locations.length" class="field__help">{{ t('menu_settings.no_locations', 'The active theme has no menu locations.') }}</p>
				<div v-else class="menu-settings__locations">
					<label v-for="location in locations" :key="location.name" class="checkbox menu-settings__location">
						<input type="checkbox" :checked="chosen.includes(location.name)" @change="toggle(location.name, ($event.target as HTMLInputElement).checked)">
						<span>
							<span class="menu-settings__location-name">{{ location.label }}</span>
							<span class="field__help">{{ t('menu_settings.location_help', '{depth}. {state}', { depth: depthOf(location), state: state(location) }) }}</span>
						</span>
					</label>
				</div>
			</fieldset>
		</form>

		<template #footer>
			<button type="button" class="button" @click="emit('close')">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="menu-settings" class="button button--primary" :disabled="problem !== ''">{{ t('common.done', 'Done') }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.menu-settings__lead {
	margin: 0;
	font-size: var(--font-size-small);
}

.menu-settings__snippet {
	display: flex;
	align-items: center;
	gap: var(--s-2);
}

.menu-settings__snippet code {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
}

.menu-settings__locations {
	display: grid;
	gap: var(--s-3);
}

.menu-settings__location {
	align-items: flex-start;
}

.menu-settings__location input {
	margin-top: 3px;
}

.menu-settings__location > span {
	display: grid;
	gap: 2px;
}

.menu-settings__location-name {
	color: var(--color-text);
	font-weight: 500;
}

.menu-settings__location .field__help {
	margin: 0;
}
</style>
