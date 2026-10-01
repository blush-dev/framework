/**
 * The field types the site has (`GET fields/types`, D-337), built-in and
 * from extensions, loaded once and shared by the field definition editor
 * and the list of a type's fields.
 */

import { ref } from 'vue';
import { request, type FieldDescription, type FieldTypeCatalog, type FieldTypeDescription } from './api';

export const catalog = ref<FieldTypeCatalog | null>(null);

let loading: Promise<FieldTypeCatalog> | null = null;

/**
 * Loads the catalog, once.
 */
export function loadFieldTypes(): Promise<FieldTypeCatalog> {
	loading ??= request<FieldTypeCatalog>('GET', '/fields/types').then((loaded) => {
		catalog.value = loaded;

		return loaded;
	}).catch((error: unknown) => {
		loading = null;

		throw error;
	});

	return loading;
}

/**
 * A field type's description, if the catalog has it.
 */
export function fieldType(type: string): FieldTypeDescription | undefined {
	return catalog.value?.types.find((item) => item.type === type);
}

/**
 * A field type's name for people: "Formatted text", or "List of text"
 * for a list. Before the catalog loads, or for a type it doesn't have,
 * the type's key.
 */
export function typeName(field: FieldDescription): string {
	const name = fieldType(field.type)?.label ?? field.type;

	if (field.item !== undefined) {
		return `${name} of ${(fieldType(field.item.type)?.label ?? field.item.type).toLowerCase()}`;
	}

	return name;
}
