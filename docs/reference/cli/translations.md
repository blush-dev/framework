# Translations

Collecting an extension's text for translators.

| Command | What it does |
|---|---|
| `lang:extract <name> [--locale] [--dry-run]` | Write the text a theme's, plugin's, or icon pack's code and manifest write (`t()` calls with text beside the key, and the manifest's [label, description, menu locations, and settings](../../themes/translations.md) under `manifest.` keys) to its `lang/en.json` (or `--locale`'s), for translators: new keys are added, text changed in the code is replaced (translators should recheck it), each `context:` is written beside its message, and everything else is kept. `core` does Blush's own. Its scripts' `t()` calls (`.ts`, `.js`, and `.vue` files) go to the [named catalog](../../features/scripts-and-styles.md) of their folder, `lang/{catalog}/en.json`, each written once it has text. Warns about a key or text that isn't plain text, a `key:` or `text:` parameter beside the key and text (which PHP refuses), one key with two texts, text that isn't a valid ICU message, a script that reads another extension's messages, and a folder in `catalogs` that doesn't exist. See [Translations](../../features/translations.md). |
