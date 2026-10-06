<script setup lang="ts">
/**
 * The element tab for a block of plain Markdown (admin.md §8, Every
 * element is an object; D-268): the heading, paragraph, list, list item,
 * quote, code block, table, divider, or definition list (or one of its
 * terms or definitions) the caret is in, or the one above a blank line
 * it's on. Every one takes classes and an id (D-282 for a definition
 * list's), so they have the same two fields; above them is whatever else that block
 * has: a heading's level, a code block's language, a list's type, and
 * whether a list item is a task and done. Each change is written back
 * into the text as it's made (`markdown.ts`): attributes go where the
 * site reads them, at the end of a heading's, paragraph's, or list
 * item's, term's, or definition's line, or on a line of their own above
 * the rest (a list's and a definition list's always). What's in a list or
 * definition list is passed
 * in as the default slot, before the source.
 */

import { computed } from 'vue';
import AttributeFields from './AttributeFields.vue';
import OptionSelect from './OptionSelect.vue';
import OptionsGroup from './OptionsGroup.vue';
import { BLOCK_KINDS, LIST_STYLES } from '../blocks';
import {
	attributeParts,
	codeLanguage,
	headingLevel,
	listStyle,
	taskState,
	withBlockParts,
	withCodeLanguage,
	withHeadingLevel,
	withListStyle,
	withTask,
	type Edit,
	type ListStyle,
	type MarkdownBlock,
	type MarkdownOutline
} from '../markdown';

const props = defineProps<{
	source: string;
	markdown: MarkdownOutline;
	blocks: MarkdownBlock[];
	block: MarkdownBlock;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
}>();

const kind     = computed(() => BLOCK_KINDS[props.block.kind]);
const parts    = computed(() => attributeParts(props.block.attributes?.text ?? ''));
const level    = computed(() => headingLevel(props.markdown, props.block));
const language = computed(() => codeLanguage(props.markdown, props.block));
const task     = computed(() => taskState(props.markdown, props.block));
const style    = computed(() => listStyle(props.markdown, props.blocks, props.block));


const levels = computed(() => Array.from({ length: 6 }, (_, index) => ({ value: String(index + 1), label: `Heading ${index + 1}` })));
const styles = computed(() => (Object.keys(LIST_STYLES) as ListStyle[]).map((key) => ({ value: key, label: LIST_STYLES[key].label })));

// The block's first line, as a reminder of which one this is.
const firstLine = computed(() => props.markdown.lines[props.block.first]?.text ?? '');

function send(edit: Edit | null): void {
	if (edit !== null) {
		emit('edit', edit);
	}
}

function changeParts(classes: string[], id: string): void {
	send(withBlockParts(props.source, props.markdown, props.block, classes, id));
}
</script>

<template>
	<div class="options">
		<OptionsGroup>
			<p class="options__note">{{ kind.description }}</p>
		</OptionsGroup>

		<OptionsGroup v-if="block.kind === 'heading'" heading="Level">
			<OptionSelect id="block-level" label="Level" :model-value="String(level)" :options="levels" help="The entry's title is the page's heading 1, so a body usually starts at 2." @update:model-value="send(withHeadingLevel(markdown, block, Number($event)))" />
		</OptionsGroup>

		<OptionsGroup v-else-if="block.kind === 'code'" heading="Language">
			<div class="field">
				<label class="visually-hidden" for="block-language">Language</label>
				<input id="block-language" class="mono" :value="language" placeholder="php" autocomplete="off" spellcheck="false" aria-describedby="block-language-help" @input="send(withCodeLanguage(markdown, block, ($event.target as HTMLInputElement).value))">
				<p id="block-language-help" class="field__help">Sets the highlighting on the site. Leave it empty for none.</p>
			</div>
		</OptionsGroup>

		<OptionsGroup v-else-if="block.kind === 'item'" heading="Item">
			<label class="checkbox">
				<input type="checkbox" :checked="task.task" @change="send(withTask(markdown, block, ($event.target as HTMLInputElement).checked))">
				A task, with a checkbox
			</label>
			<label v-if="task.task" class="checkbox">
				<input type="checkbox" :checked="task.done" @change="send(withTask(markdown, block, true, ($event.target as HTMLInputElement).checked))">
				Done
			</label>
		</OptionsGroup>

		<OptionsGroup v-else-if="block.kind === 'list'" heading="List Type">
			<OptionSelect id="block-list-style" label="List type" :model-value="style" :options="styles" :help="LIST_STYLES[style].description" @update:model-value="send(withListStyle(source, markdown, blocks, block, $event as ListStyle))" />
		</OptionsGroup>

		<OptionsGroup heading="Attributes">
			<AttributeFields :classes="parts.classes" :id="parts.id" id-prefix="block-" @change="changeParts" />
			<p v-if="block.kind === 'list' || block.kind === 'definitions'" class="field__help">Written on a line of their own above the list, <code>{.wide}</code>, which is where a list carries them.</p>
		</OptionsGroup>

		<slot />

		<OptionsGroup v-if="block.kind !== 'list' && block.kind !== 'definitions'" heading="Source">
			<pre class="options__source">{{ firstLine }}</pre>
		</OptionsGroup>
	</div>
</template>
