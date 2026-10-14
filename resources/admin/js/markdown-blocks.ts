/**
 * How the editor names and draws each kind of Markdown block (D-268):
 * on the element tab when the caret is in one, with a sentence saying
 * what it is; and the three kinds of list.
 */

import type { IconName } from './icons';
import { t } from './messages';
import type { MarkdownBlockKind, ListStyle } from './markdown';

export const MARKDOWN_BLOCK_KINDS: Record<MarkdownBlockKind, { label: string; icon: IconName; description: string }> = {
	heading: { label: t('markdown_blocks.kinds.heading.label', 'Heading'), icon: 'heading', description: t('markdown_blocks.kinds.heading.description', 'A heading. Its level is the number of hashes.') },
	paragraph: { label: t('markdown_blocks.kinds.paragraph.label', 'Paragraph'), icon: 'pilcrow', description: t('markdown_blocks.kinds.paragraph.description', 'A paragraph of prose.') },
	list: { label: t('markdown_blocks.kinds.list.label', 'List'), icon: 'list', description: t('markdown_blocks.kinds.list.description', 'A whole list. Its items are elements of their own, inside it.') },
	item: { label: t('markdown_blocks.kinds.item.label', 'List Item'), icon: 'list', description: t('markdown_blocks.kinds.item.description', "One item of a list. Its attributes are the item's, not the whole list's.") },
	quote: { label: t('markdown_blocks.kinds.quote.label', 'Quote'), icon: 'text-quote', description: t('markdown_blocks.kinds.quote.description', 'A block quotation.') },
	code: { label: t('markdown_blocks.kinds.code.label', 'Code Block'), icon: 'code', description: t('markdown_blocks.kinds.code.description', 'A fenced code block. The word after the fence is its language.') },
	table: { label: t('markdown_blocks.kinds.table.label', 'Table'), icon: 'table', description: t('markdown_blocks.kinds.table.description', 'A table, written with pipes.') },
	rule: { label: t('markdown_blocks.kinds.rule.label', 'Divider'), icon: 'minus', description: t('markdown_blocks.kinds.rule.description', 'A horizontal rule between sections.') },
	definitions: { label: t('markdown_blocks.kinds.definitions.label', 'Definitions'), icon: 'book-open', description: t('markdown_blocks.kinds.definitions.description', 'A definition list: terms, each with one or more definitions.') },
	term: { label: t('markdown_blocks.kinds.term.label', 'Term'), icon: 'heading', description: t('markdown_blocks.kinds.term.description', 'A term being defined. Several in a row share the definitions under them.') },
	definition: { label: t('markdown_blocks.kinds.definition.label', 'Definition'), icon: 'corner-down-right', description: t('markdown_blocks.kinds.definition.description', 'One definition of the term above it. A term may have several.') }
};

export const LIST_STYLES: Record<ListStyle, { label: string; icon: IconName; description: string }> = {
	bullet: { label: t('markdown_blocks.list_styles.bullet.label', 'Bulleted'), icon: 'list', description: t('markdown_blocks.list_styles.bullet.description', "Order doesn't matter.") },
	number: { label: t('markdown_blocks.list_styles.number.label', 'Numbered'), icon: 'list-ordered', description: t('markdown_blocks.list_styles.number.description', 'Order matters. The numbers follow the order the items are in.') },
	task: { label: t('markdown_blocks.list_styles.task.label', 'Task'), icon: 'list-checks', description: t('markdown_blocks.list_styles.task.description', 'Each item has a checkbox.') }
};
