/**
 * Opening a field group a level down (D-768, D-770). A group or repeating
 * group draws in place at the first level; one inside another is a row
 * that opens its own level, in place of what's in the panel, with one
 * back. A screen that can do that (the editor's Document tab) provides
 * `OPEN_LEVEL`; on one that doesn't, every level draws in place.
 */

import type { InjectionKey } from 'vue';
import type { FieldStep } from './fields';

/**
 * Where a field is: the form field it's inside (`root`), the steps down
 * to it, and the labels on the way, for the path back.
 */
export interface FieldLevel {
	root: string;
	steps: FieldStep[];
	trail: string[];
}

export const OPEN_LEVEL: InjectionKey<(level: FieldLevel) => void> = Symbol('open-level');
