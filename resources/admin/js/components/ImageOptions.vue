<script setup lang="ts">
/**
 * The element tab for a Markdown image (admin.md §8, An image is
 * Markdown; D-268). There's no image block: `![alt](src "caption")`
 * is edited in place, with the same panel a block gets.
 *
 * - **Variant**: Default, then the classes the theme offers images
 *   (`GET blocks`' `image.variants`). A variant is a class in the
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
import { t } from '../messages';
import ImagePreview from './ImagePreview.vue';
import AttributeFields from './AttributeFields.vue';
import OptionSelect from './OptionSelect.vue';
import OptionsGroup from './OptionsGroup.vue';
import type { BlockVariant } from '../blocks';
import type { MediaItem } from '../api';
import { attributeParts, unescaped, withImage, withParts, type Edit, type MarkdownImage } from '../markdown';

const props = defineProps<{
	source: string;
	image: MarkdownImage;
	variants: BlockVariant[];
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

const variantOptions = computed(() => [{ value: '', label: t('image_options.default', 'Default') }, ...props.variants.map((item) => ({ value: item.name, label: item.label }))]);

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
			<p class="options__note">{{ t('image_options.note', 'An ordinary Markdown image. The quoted part after its address is its caption.') }}</p>
		</OptionsGroup>

		<OptionsGroup v-if="variants.length" :heading="t('image_options.variant', 'Variant')">
			<OptionSelect id="image-variant" :label="t('image_options.variant', 'Variant')" :model-value="variant" :options="variantOptions" :help="chosen ? (chosen.description || t('image_options.variant_theme', 'A style the theme provides.')) : t('image_options.variant_default', 'At the width of the text.')" @update:model-value="changeVariant" />
		</OptionsGroup>

		<OptionsGroup :heading="t('image_options.image', 'Image')">
			<ImagePreview :src="image.src" @pick="emit('pick')" @remove="emit('remove')" @resolved="file = $event" />
		</OptionsGroup>

		<OptionsGroup :heading="t('image_options.text', 'Text')">
			<div class="field">
				<label class="checkbox">
					<input type="checkbox" :checked="decorative" aria-describedby="image-decorative-help" @change="changeDecorative">
					{{ t('image_options.decorative', 'Decorative') }}
				</label>
				<p id="image-decorative-help" class="field__help">
					<template v-if="decorative">{{ t('image_options.decorative_on', 'No alt text, so screen readers skip it. Turn this off to describe it.') }}</template>
					<template v-else>{{ t('image_options.decorative_off', 'For an image that adds nothing a reader needs, such as an ornament. Turning it on clears the alt text.') }}</template>
				</p>
				<p v-if="decorative && libraryAlt" class="field__help">
					{{ t('image_options.library_alt', 'The library describes it as “{alt}”.', { alt: libraryAlt }) }} <button type="button" class="lnk" @click="useLibrary">{{ t('image_options.use_library', 'Use the library\'s') }}</button>
				</p>
			</div>
			<div v-if="!decorative" class="field">
				<label for="image-alt">{{ t('image_options.alt', 'Alt text') }}</label>
				<textarea id="image-alt" ref="altField" :value="alt" rows="3" :placeholder="t('image_options.alt_placeholder', 'What the image shows, for anyone who can\'t see it.')" aria-describedby="image-alt-help" @input="write({ alt: ($event.target as HTMLTextAreaElement).value })" />
				<p id="image-alt-help" class="field__help">{{ t('image_options.alt_help', 'For this image here; the library\'s own doesn\'t change.') }}</p>
			</div>
			<div class="field">
				<label for="image-caption">{{ t('image_options.caption', 'Caption') }}</label>
				<input id="image-caption" :value="caption" autocomplete="off" aria-describedby="image-caption-help" @input="write({ title: ($event.target as HTMLInputElement).value })">
				<p id="image-caption-help" class="field__help">{{ t('image_options.caption_help', 'Shown under the image on the site. It\'s the quoted part after the address.') }}</p>
			</div>
		</OptionsGroup>

		<OptionsGroup :heading="t('image_options.attributes', 'Attributes')">
			<AttributeFields :classes="classes" :id="parts.id" id-prefix="image-" @change="changeParts" />
		</OptionsGroup>

		<OptionsGroup :heading="t('image_options.source', 'Source')">
			<pre class="options__source">{{ source.slice(image.start, image.end) }}</pre>
		</OptionsGroup>
	</div>
</template>
