<script setup lang="ts">
/**
 * A menu item's fields, opened under its row (from the menus sketch):
 * Label and Link side by side, then Description, Badge and Icon, and
 * Class and Link Relationship. Changes go into the item at once and are
 * saved with the menu.
 *
 * **Link** is `LinkPicker`, as Redirects' To is: one box that takes a
 * path, a page's name, or a whole address, and suggests as it's typed
 * (entries, terms, collections, and named routes). A link to something
 * on the site follows it when its address changes. Without a link, the
 * item is plain text, which needs a label.
 *
 * Text that's a map of locales (`{"en": …, "fr": …}`) is edited in the
 * site's language, and the others are kept.
 */

import { computed, ref } from 'vue';
import { kindIcon, linkOption, searchLinks, setLink, textOf, typedLink, withText, type MenuDetail, type MenuLink, type MenuNode } from '../menus';
import { moreLine } from '../references';
import AdminIcon from './AdminIcon.vue';
import IconField from './IconField.vue';
import LinkPicker, { type LinkOption } from './LinkPicker.vue';

const props = defineProps<{
	node: MenuNode;
	locale: string;
	kinds: MenuDetail['kinds'];
}>();

const emit = defineEmits<{
	change: [];
	done: [];
}>();

const id = computed(() => `menu-item-${props.node.id}`);

function text(key: string): string {
	return textOf(props.node.item[key], props.locale);
}

function write(key: string, value: string): void {
	const item = { ...props.node.item };

	if (value === '' && typeof item[key] !== 'object') {
		delete item[key];
	} else {
		item[key] = withText(item[key], value, props.locale);
	}

	props.node.item = item;
	emit('change');
}

const field = (key: string) => computed({ get: () => text(key), set: (value: string) => write(key, value) });

const label       = field('label');
const description = field('description');
const badge       = field('badge');
const icon        = field('icon');
const cssClass    = field('class');
const rel         = field('rel');

// What the box offers, by key, so a pick finds its link.
const typed  = ref('');
const offers = new Map<string, MenuLink>();

const chosen = computed<LinkOption | null>(() => props.node.link === null ? null : linkOption(props.node.link));

async function search(query: string): Promise<{ options: LinkOption[]; note: string }> {
	const { links, total } = await searchLinks(query);
	const options          = links.map((link) => {
		const option = linkOption(link);

		offers.set(option.key, link);

		return option;
	});

	return { options, note: query !== '' && total > links.length ? moreLine(links.length, total) : '' };
}

function offerTyped(query: string): LinkOption | null {
	const link = typedLink(query);

	if (link === null) {
		return null;
	}

	const option = { ...linkOption(link), typed: true, title: query };

	offers.set(option.key, link);

	return option;
}

function choose(option: LinkOption): void {
	const link = offers.get(option.key);

	if (link !== undefined) {
		setLink(props.node, link, props.kinds);
		typed.value = '';
		emit('change');
	}
}

function clear(): void {
	typed.value = props.node.link?.kind === 'url' ? props.node.link.value : '';
	setLink(props.node, null, props.kinds);
	emit('change');
}

const note = computed(() => {
	const link = props.node.link;

	if (link === null) {
		return null;
	}

	if (link.message !== null) {
		return { icon: 'triangle-alert' as const, text: link.message, warn: true };
	}

	return link.kind === 'url' ? null : { icon: 'info' as const, text: `Follows ${link.title || link.value} if its address changes.`, warn: false };
});

function key(event: KeyboardEvent): void {
	// The icon picker's Escape is its own.
	if (event.key === 'Escape' && !event.defaultPrevented && (event.target as Element).closest('dialog') === null) {
		event.preventDefault();
		emit('done');
	}
}
</script>

<template>
	<div :id="id" class="menu-fields" role="group" :aria-label="`Edit ${label || node.link?.title || 'item'}`" @keydown="key">
		<div class="field-pair">
			<div class="field">
				<label :for="`${id}-label`">Label</label>
				<input :id="`${id}-label`" v-model="label" autocomplete="off" :placeholder="node.link?.title || 'The text to show'" :aria-describedby="`${id}-label-help`">
				<p :id="`${id}-label-help`" class="field__help">{{ node.link ? 'Leave it empty to use the link\'s own title.' : 'Plain text needs a label.' }}</p>
			</div>
			<div class="field">
				<label :for="`${id}-link`">Link</label>
				<LinkPicker
					:id="`${id}-link`"
					v-model="typed"
					:chosen="chosen"
					:search="search"
					:typed="offerTyped"
					label="Link"
					empty="Nothing on the site matches. Type an address that starts with / or https://."
					:described-by="`${id}-link-help`"
					@choose="choose"
					@clear="clear"
				/>
				<p v-if="!node.link" :id="`${id}-link-help`" class="field__help menu-fields__note"><AdminIcon :name="kindIcon(null)" /><span>Without a link, this item is <strong>plain text</strong>, shown but not clickable. Themes often use it as a heading over its sub-items.</span></p>
				<p v-else-if="note" :id="`${id}-link-help`" class="field__help menu-fields__note" :class="{ 'is-warn': note.warn }"><AdminIcon :name="note.icon" /><span>{{ note.text }}</span></p>
			</div>
		</div>

		<div class="field">
			<label :for="`${id}-description`">Description</label>
			<textarea :id="`${id}-description`" v-model="description" rows="2" placeholder="Shown under the label where the theme has room" />
		</div>

		<div class="field-pair">
			<div class="field">
				<label :for="`${id}-badge`">Badge</label>
				<input :id="`${id}-badge`" v-model="badge" autocomplete="off" placeholder="New">
			</div>
			<div class="field">
				<label :for="`${id}-icon`">Icon</label>
				<IconField :id="`${id}-icon`" v-model="icon" :preview="(chosen) => `icon: ${chosen.name}`" />
			</div>
		</div>

		<div class="field-pair">
			<div class="field">
				<label :for="`${id}-class`">Class</label>
				<input :id="`${id}-class`" v-model="cssClass" class="mono" autocomplete="off" spellcheck="false" :aria-describedby="`${id}-class-help`">
				<p :id="`${id}-class-help`" class="field__help">Added to the item in the theme's markup.</p>
			</div>
			<div v-if="node.link" class="field">
				<label :for="`${id}-rel`">Link Relationship</label>
				<input :id="`${id}-rel`" v-model="rel" class="mono" autocomplete="off" spellcheck="false" placeholder="me, nofollow" :aria-describedby="`${id}-rel-help`">
				<p :id="`${id}-rel-help`" class="field__help">How the linked page relates to this site. Written to the link's <code>rel</code>.</p>
			</div>
		</div>

		<div class="menu-fields__foot">
			<span class="field__help">Changes save with the menu.</span>
			<button type="button" class="button button--small" @click="emit('done')">Done</button>
		</div>
	</div>
</template>

<style scoped>
.menu-fields {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x) var(--s-4) calc(var(--pad-x) + var(--menu-indent, 0px));
	border-top: 1px solid var(--border);
	background: var(--surface-2);
}

.menu-fields .field-pair {
	align-items: start;
}

.menu-fields__note {
	display: flex;
	align-items: flex-start;
	gap: 6px;
}

.menu-fields__note .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 2px;
}

.menu-fields__note.is-warn {
	color: var(--warn);
}

.menu-fields__foot {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--s-3);
}

.menu-fields__foot .field__help {
	margin: 0;
}
</style>
