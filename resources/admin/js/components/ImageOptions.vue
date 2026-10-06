<script setup lang="ts">
/**
 * The element tab for a Markdown image (admin.md §8, An image is
 * Markdown; D-268). There's no image component: `![alt](src "caption")`
 * is edited in place, with the same panel a component gets.
 *
 * - **Variant**: Default, then the classes the theme offers images
 *   (`GET components`' `image.variants`). A variant is a class in the
 *   image's attributes (`{.stretch-wide}`), so choosing one swaps that
 *   class and leaves the rest.
 * - **The image itself**, not its file name (`ImagePreview`), with
 *   **Replace** and **Remove** on it.
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

import { computed, nextTick, ref } from 'vue';
import ImagePreview from './ImagePreview.vue';
import AttributeFields from './AttributeFields.vue';
import OptionSelect from './OptionSelect.vue';
import OptionsGroup from './OptionsGroup.vue';
import type { ComponentVariant } from '../components';
import type { MediaItem } from '../api';
import { attributeParts, unescaped, withImage, withParts, type Edit, type MarkdownImage } from '../markdown';

const props = defineProps<{
	source: string;
	image: MarkdownImage;
	variants: ComponentVariant[];
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

const variantOptions = computed(() => [{ value: '', label: 'Default' }, ...props.variants.map((item) => ({ value: item.name, label: item.label }))]);

function changeVariant(name: string): void {
	write({ attributes: withParts(body.value, name === '' ? classes.value : [name, ...classes.value], parts.value.id) });
}

function changeParts(next: string[], id: string): void {
	write({ attributes: withParts(body.value, variant.value === '' ? next : [variant.value, ...next], id) });
}

// The library's record for the file, with its alt text and caption.
const file = ref<MediaItem | null>(null);

const libraryAlt = computed(() => file.value?.alt ?? '');
</script>

<template>
	<div class="options">
		<OptionsGroup>
			<p class="options__note">An ordinary Markdown image. The quoted part after its address is its caption.</p>
		</OptionsGroup>

		<OptionsGroup v-if="variants.length" heading="Variant">
			<OptionSelect id="image-variant" label="Variant" :model-value="variant" :options="variantOptions" :help="chosen ? (chosen.description || 'A style the theme provides.') : 'At the width of the text.'" @update:model-value="changeVariant" />
		</OptionsGroup>

		<OptionsGroup heading="Image">
			<ImagePreview :src="image.src" @pick="emit('pick')" @remove="emit('remove')" @resolved="file = $event" />
		</OptionsGroup>

		<OptionsGroup heading="Text">
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
					The library describes it as “{{ libraryAlt }}”. <button type="button" class="lnk" @click="useLibrary">Use the library's</button>
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
		</OptionsGroup>

		<OptionsGroup heading="Attributes">
			<AttributeFields :classes="classes" :id="parts.id" id-prefix="image-" @change="changeParts" />
		</OptionsGroup>

		<OptionsGroup heading="Source">
			<pre class="options__source">{{ source.slice(image.start, image.end) }}</pre>
		</OptionsGroup>
	</div>
</template>
