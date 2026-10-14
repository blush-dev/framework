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
import MessageText from './MessageText.vue';
import { t } from '../messages';
import { MARKDOWN_BLOCK_KINDS, LIST_STYLES } from '../markdown-blocks';
import {
	attributeParts,
	codeLanguage,
	headingLevel,
	listStyle,
	taskState,
	withMarkdownBlockParts,
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
	markdownBlocks: MarkdownBlock[];
	markdownBlock: MarkdownBlock;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
}>();

const kind     = computed(() => MARKDOWN_BLOCK_KINDS[props.markdownBlock.kind]);
const parts    = computed(() => attributeParts(props.markdownBlock.attributes?.text ?? ''));
const level    = computed(() => headingLevel(props.markdown, props.markdownBlock));
const language = computed(() => codeLanguage(props.markdown, props.markdownBlock));
const task     = computed(() => taskState(props.markdown, props.markdownBlock));
const style    = computed(() => listStyle(props.markdown, props.markdownBlocks, props.markdownBlock));


const levels = computed(() => Array.from({ length: 6 }, (_, index) => ({ value: String(index + 1), label: t('markdown_block_options.heading_level', 'Heading {level}', { level: index + 1 }) })));
const styles = computed(() => (Object.keys(LIST_STYLES) as ListStyle[]).map((key) => ({ value: key, label: LIST_STYLES[key].label })));

// The block's first line, as a reminder of which one this is.
const firstLine = computed(() => props.markdown.lines[props.markdownBlock.first]?.text ?? '');

function send(edit: Edit | null): void {
	if (edit !== null) {
		emit('edit', edit);
	}
}

function changeParts(classes: string[], id: string): void {
	send(withMarkdownBlockParts(props.source, props.markdown, props.markdownBlock, classes, id));
}
</script>

<template>
	<div class="options">
		<OptionsGroup>
			<p class="options__note">{{ kind.description }}</p>
		</OptionsGroup>

		<OptionsGroup v-if="markdownBlock.kind === 'heading'" :heading="t('markdown_block_options.level', 'Level')">
			<OptionSelect id="markdown-block-level" :label="t('markdown_block_options.level', 'Level')" :model-value="String(level)" :options="levels" :help="t('markdown_block_options.level_help', 'The entry\'s title is the page\'s heading 1, so a body usually starts at 2.')" @update:model-value="send(withHeadingLevel(markdown, markdownBlock, Number($event)))" />
		</OptionsGroup>

		<OptionsGroup v-else-if="markdownBlock.kind === 'code'" :heading="t('markdown_block_options.language', 'Language')">
			<div class="field">
				<label class="visually-hidden" for="markdown-block-language">{{ t('markdown_block_options.language', 'Language') }}</label>
				<input id="markdown-block-language" class="mono" :value="language" placeholder="php" autocomplete="off" spellcheck="false" aria-describedby="markdown-block-language-help" @input="send(withCodeLanguage(markdown, markdownBlock, ($event.target as HTMLInputElement).value))">
				<p id="markdown-block-language-help" class="field__help">{{ t('markdown_block_options.language_help', 'Sets the highlighting on the site. Leave it empty for none.') }}</p>
			</div>
		</OptionsGroup>

		<OptionsGroup v-else-if="markdownBlock.kind === 'item'" :heading="t('markdown_block_options.item', 'Item')">
			<label class="checkbox">
				<input type="checkbox" :checked="task.task" @change="send(withTask(markdown, markdownBlock, ($event.target as HTMLInputElement).checked))">
				{{ t('markdown_block_options.task', 'A task, with a checkbox') }}
			</label>
			<label v-if="task.task" class="checkbox">
				<input type="checkbox" :checked="task.done" @change="send(withTask(markdown, markdownBlock, true, ($event.target as HTMLInputElement).checked))">
				{{ t('common.done', 'Done') }}
			</label>
		</OptionsGroup>

		<OptionsGroup v-else-if="markdownBlock.kind === 'list'" :heading="t('markdown_block_options.list_type_heading', 'List Type')">
			<OptionSelect id="markdown-block-list-style" :label="t('markdown_block_options.list_type', 'List type')" :model-value="style" :options="styles" :help="LIST_STYLES[style].description" @update:model-value="send(withListStyle(source, markdown, markdownBlocks, markdownBlock, $event as ListStyle))" />
		</OptionsGroup>

		<OptionsGroup :heading="t('markdown_block_options.attributes', 'Attributes')">
			<AttributeFields :classes="parts.classes" :id="parts.id" id-prefix="markdown-block-" @change="changeParts" />
			<p v-if="markdownBlock.kind === 'list' || markdownBlock.kind === 'definitions'" class="field__help"><MessageText :text="t('markdown_block_options.list_attributes', 'Written on a line of their own above the list, {example}, which is where a list carries them.')"><template #example><code>{.wide}</code></template></MessageText></p>
		</OptionsGroup>

		<slot />

		<OptionsGroup v-if="markdownBlock.kind !== 'list' && markdownBlock.kind !== 'definitions'" :heading="t('markdown_block_options.source', 'Source')">
			<pre class="options__source">{{ firstLine }}</pre>
		</OptionsGroup>
	</div>
</template>
