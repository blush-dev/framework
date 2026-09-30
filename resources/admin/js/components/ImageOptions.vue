<script setup lang="ts">
/**
 * The Component tab for a Markdown image (admin.md §8, An image is
 * Markdown; D-268). There's no image component: `![alt](src "caption")`
 * is edited in place, with the same panel a component gets.
 *
 * - **Variant**: Default, then the classes the theme offers images
 *   (`GET components`' `image.variants`). A variant is a class in the
 *   image's attributes (`{.stretch-wide}`), so choosing one swaps that
 *   class and leaves the rest.
 * - **The image itself**, not its file name: the library's 4:3 crop, its
 *   address and size on a line under it, and **Replace** and **Remove**
 *   on it, over a veil, on hover or keyboard focus. A file that isn't
 *   there says so rather than showing a broken frame. A file named
 *   without a path is one beside the entry, in its page bundle.
 * - **Decorative**, then **Alt text** and **Caption** (the quoted title,
 *   which the site shows under it; an empty one writes nothing). Empty
 *   brackets are `alt=""` on the page, so an image without alt text is
 *   decorative (D-272): the toggle is on then, and the field is hidden.
 *   Turning it off shows the field to describe the image; turning it on
 *   clears the alt text. Both are this image's, here: editing them never
 *   changes the library's, which the image can take with **Use the
 *   library's** when it has none.
 * - **Classes** and **ID**, as every object has; the variant's class
 *   isn't repeated there.
 */

import { computed, nextTick, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AttributeFields from './AttributeFields.vue';
import type { ComponentVariant } from '../components';
import type { MediaItem } from '../api';
import { mediaFile } from '../media';
import { attributeParts, unescaped, withImage, withParts, type Edit, type MarkdownImage } from '../markdown';

const props = defineProps<{
	source: string;
	image: MarkdownImage;
	variants: ComponentVariant[];
	// The entry, for a file beside it.
	entry?: string;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
	remove: [];
	// Replace, or Choose when there's no file yet: the editor opens the
	// media picker.
	pick: [];
}>();

const body     = computed(() => props.image.attributes?.text ?? '');
const parts    = computed(() => attributeParts(body.value));
const names    = computed(() => new Set(props.variants.map((item) => item.name)));
const variant  = computed(() => parts.value.classes.find((name) => names.value.has(name)) ?? '');
const chosen   = computed(() => props.variants.find((item) => item.name === variant.value));
const classes  = computed(() => parts.value.classes.filter((name) => !names.value.has(name)));
const alt      = computed(() => unescaped(props.image.alt));
const caption  = computed(() => unescaped(props.image.title ?? ''));
// Decorative is empty alt text. Turning it off shows the field until
// there's something in it; turning it on clears it.
const describing = ref(false);
const decorative = computed(() => alt.value === '' && !describing.value);
const altField   = ref<HTMLTextAreaElement | null>(null);

async function changeDecorative(event: Event): Promise<void> {
	if ((event.target as HTMLInputElement).checked) {
		describing.value = false;

		if (alt.value !== '') {
			write({ alt: '' });
		}

		return;
	}

	describing.value = true;
	await nextTick();
	altField.value?.focus();
}

function useLibrary(): void {
	describing.value = true;
	write({ alt: libraryAlt.value });
}

function write(changes: Parameters<typeof withImage>[1]): void {
	emit('edit', withImage(props.image, changes));
}

function changeVariant(event: Event): void {
	const name = (event.target as HTMLSelectElement).value;

	write({ attributes: withParts(body.value, name === '' ? classes.value : [name, ...classes.value], parts.value.id) });
}

function changeParts(next: string[], id: string): void {
	write({ attributes: withParts(body.value, variant.value === '' ? next : [variant.value, ...next], id) });
}

// Where the image is shown from: its library or bundle file, or else its
// address; `missing` once it fails to load. `file` has the library's alt
// text and caption.
const url     = ref<string | null>(null);
const file    = ref<MediaItem | null>(null);
const missing = ref(false);
const size    = ref('');

async function resolve(): Promise<void> {
	const src = props.image.src;

	missing.value = false;
	size.value    = '';
	file.value    = null;

	if (src === '') {
		url.value = null;

		return;
	}

	const found = await mediaFile(src, props.entry);

	if (src !== props.image.src) {
		return;
	}

	file.value = found;

	if (found !== null) {
		url.value = found.url;
	} else if (/^([a-z][a-z0-9+.-]*:)?\/\//i.test(src) || src.startsWith('/') || props.entry === undefined) {
		url.value = src;
	} else {
		url.value     = null;
		missing.value = true;
	}
}

const libraryAlt = computed(() => file.value?.alt ?? '');

watch([() => props.image.src, () => props.entry], () => void resolve(), { immediate: true });

function loaded(event: Event): void {
	const element = event.target as HTMLImageElement;

	size.value = `${element.naturalWidth} × ${element.naturalHeight}`;
}
</script>

<template>
	<div class="options">
		<div class="options__group">
			<p class="options__note">An ordinary Markdown image. The quoted part after its address is its caption.</p>
		</div>

		<div v-if="variants.length" class="options__group">
			<p class="options__heading">Variant</p>
			<div class="field">
				<label class="visually-hidden" for="image-variant">Variant</label>
				<select id="image-variant" :value="variant" aria-describedby="image-variant-help" @change="changeVariant">
					<option value="">Default</option>
					<option v-for="item in variants" :key="item.name" :value="item.name">{{ item.label }}</option>
				</select>
				<p id="image-variant-help" class="field__help">{{ chosen ? (chosen.description || 'A style the theme provides.') : 'At the width of the text.' }}</p>
			</div>
		</div>

		<div class="options__group">
			<p class="options__heading">Image</p>
			<div v-if="image.src" class="image-preview">
				<span class="image-preview__frame">
					<img v-if="url && !missing" :src="url" alt="" @load="loaded" @error="missing = true">
					<span v-else class="image-preview__missing">
						<AdminIcon name="image-off" />
						<span>{{ url === null && !missing ? 'Loading…' : 'Not found' }}</span>
					</span>
				</span>
				<span class="image-preview__veil" aria-hidden="true" />
				<span class="image-preview__actions">
					<button type="button" class="button button--small" @click="emit('pick')">Replace<span class="visually-hidden"> the image</span></button>
					<button type="button" class="button button--small button--danger" @click="emit('remove')">Remove<span class="visually-hidden"> the image</span></button>
				</span>
			</div>
			<div v-else class="image-preview image-preview--empty">
				<button type="button" class="button" @click="emit('pick')"><AdminIcon name="image" />Choose an image</button>
			</div>
			<p v-if="image.src" class="field__help mono image-preview__about">
				{{ image.src }}<template v-if="missing"> · not found</template><template v-else-if="size"> · {{ size }}</template>
			</p>
		</div>

		<div class="options__group">
			<p class="options__heading">Text</p>
			<div class="field">
				<label class="checkbox">
					<input type="checkbox" :checked="decorative" aria-describedby="image-decorative-help" @change="changeDecorative">
					Decorative
				</label>
				<p id="image-decorative-help" class="field__help">
					<template v-if="decorative">No alt text, so screen readers skip it. Turn this off to describe it.</template>
					<template v-else>For an image that adds nothing a reader needs, such as an ornament. Turning it on clears the alt text.</template>
				</p>
				<p v-if="decorative && libraryAlt" class="field__help">
					The library describes it as “{{ libraryAlt }}”. <button type="button" class="image-library" @click="useLibrary">Use the library's</button>
				</p>
			</div>
			<div v-if="!decorative" class="field">
				<label for="image-alt">Alt text</label>
				<textarea id="image-alt" ref="altField" :value="alt" rows="3" placeholder="What the image shows, for anyone who can't see it." aria-describedby="image-alt-help" @input="write({ alt: ($event.target as HTMLTextAreaElement).value })" />
				<p id="image-alt-help" class="field__help">For this image here; the library's own doesn't change.</p>
			</div>
			<div class="field">
				<label for="image-caption">Caption</label>
				<input id="image-caption" :value="caption" autocomplete="off" aria-describedby="image-caption-help" @input="write({ title: ($event.target as HTMLInputElement).value })">
				<p id="image-caption-help" class="field__help">Shown under the image on the site. It's the quoted part after the address.</p>
			</div>
		</div>

		<div class="options__group">
			<p class="options__heading">Attributes</p>
			<AttributeFields :classes="classes" :id="parts.id" id-prefix="image-" @change="changeParts" />
		</div>

		<div class="options__group">
			<p class="options__heading">Source</p>
			<pre class="options__source">{{ source.slice(image.start, image.end) }}</pre>
		</div>
	</div>
</template>

<style scoped>
/* The same 4:3 crop the library uses, so a file looks the same wherever
   it appears. */
.image-preview {
	position: relative;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface-2);
}

.image-preview__frame {
	display: block;
	aspect-ratio: 4 / 3;
}

.image-preview__frame img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.image-preview__missing {
	display: grid;
	place-content: center;
	justify-items: center;
	gap: var(--s-2);
	height: 100%;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.image-preview__missing :deep(svg) {
	width: 24px;
	height: 24px;
}

/* The actions sit on the thing they act on, over a veil it still shows
   through, on hover or keyboard focus. */
.image-preview__veil,
.image-preview__actions {
	position: absolute;
	inset: 0;
	opacity: 0;
	transition: opacity 120ms;
}

.image-preview__veil {
	background: var(--surface);
}

.image-preview__actions {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
}

.image-preview:hover .image-preview__veil,
.image-preview:focus-within .image-preview__veil {
	opacity: .8;
}

.image-preview:hover .image-preview__actions,
.image-preview:focus-within .image-preview__actions {
	opacity: 1;
}

/* A button that reads as a link, inside a line of help. */
.image-library {
	padding: 0;
	border: 0;
	background: none;
	color: var(--accent);
	font: inherit;
	cursor: pointer;
}

.image-library:hover {
	text-decoration: underline;
}

/* Without hover, the actions stay in view. */
@media (hover: none) {
	.image-preview__actions {
		align-items: flex-end;
		padding: var(--s-3);
		opacity: 1;
	}
}

.image-preview--empty {
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	border-style: dashed;
	background: var(--bg);
}

.image-preview__about {
	overflow-wrap: anywhere;
}

@media (prefers-reduced-motion: reduce) {
	.image-preview__veil,
	.image-preview__actions {
		transition: none;
	}
}
</style>
