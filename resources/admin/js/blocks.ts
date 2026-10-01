/**
 * How the editor names and draws each kind of Markdown block (D-268):
 * on the element tab when the caret is in one, with a sentence saying
 * what it is; and the three kinds of list.
 */

import type { IconName } from './icons';
import type { BlockKind, ListStyle } from './markdown';

export const BLOCK_KINDS: Record<BlockKind, { label: string; icon: IconName; description: string }> = {
	heading: { label: 'Heading', icon: 'heading', description: 'A heading. Its level is the number of hashes.' },
	paragraph: { label: 'Paragraph', icon: 'pilcrow', description: 'A paragraph of prose.' },
	list: { label: 'List', icon: 'list', description: 'A whole list. Its items are elements of their own, inside it.' },
	item: { label: 'List Item', icon: 'list', description: 'One item of a list. Its attributes are the item\'s, not the whole list\'s.' },
	quote: { label: 'Quote', icon: 'text-quote', description: 'A block quotation.' },
	code: { label: 'Code Block', icon: 'code', description: 'A fenced code block. The word after the fence is its language.' },
	table: { label: 'Table', icon: 'table', description: 'A table, written with pipes.' },
	rule: { label: 'Divider', icon: 'minus', description: 'A horizontal rule between sections.' },
	definitions: { label: 'Definitions', icon: 'book-open', description: 'A definition list: terms, each with one or more definitions.' },
	term: { label: 'Term', icon: 'heading', description: 'A term being defined. Several in a row share the definitions under them.' },
	definition: { label: 'Definition', icon: 'corner-down-right', description: 'One definition of the term above it. A term may have several.' }
};

export const LIST_STYLES: Record<ListStyle, { label: string; icon: IconName; description: string }> = {
	bullet: { label: 'Bulleted', icon: 'list', description: 'Order doesn\'t matter.' },
	number: { label: 'Numbered', icon: 'list-ordered', description: 'Order matters. The numbers follow the order the items are in.' },
	task: { label: 'Task', icon: 'list-checks', description: 'Each item has a checkbox.' }
};
