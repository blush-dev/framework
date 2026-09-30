/**
 * How the editor names and draws each kind of Markdown block (D-268):
 * on the Component tab when the caret is in one, with a sentence saying
 * what it is.
 */

import type { IconName } from './icons';
import type { BlockKind } from './markdown';

export const BLOCK_KINDS: Record<BlockKind, { label: string; icon: IconName; description: string }> = {
	heading: { label: 'Heading', icon: 'heading', description: 'A heading. Its level is the number of hashes.' },
	paragraph: { label: 'Paragraph', icon: 'pilcrow', description: 'A paragraph of prose.' },
	item: { label: 'List Item', icon: 'list', description: 'One item of a list. Its attributes are the item\'s, not the whole list\'s.' },
	quote: { label: 'Quote', icon: 'quote', description: 'A block quotation.' },
	code: { label: 'Code Block', icon: 'code', description: 'A fenced code block. The word after the fence is its language.' },
	table: { label: 'Table', icon: 'table', description: 'A table, written with pipes.' },
	rule: { label: 'Divider', icon: 'minus', description: 'A horizontal rule between sections.' }
};
