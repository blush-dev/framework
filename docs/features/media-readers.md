# Reading more from media files

Blush reads XMP, IPTC, and EXIF from images, and MP3, MP4, Ogg, WAV,
and WebM files (see [Media](../concepts/media/README.md)). A plugin can
teach it to read another format.

Implement `Blush\Media\Embedded\EmbeddedReader`
(`read(string $path, string $mime): EmbeddedMetadata`, returning empty
metadata for a file it can't read) and register it in the plugin's
[provider](../plugins/service-providers.md):

```php
$this->container->resolving(EmbeddedReaderRegistry::class, static function (EmbeddedReaderRegistry $registry): void {
	$registry->register('heic', HeicReader::class);
});
```

Readers' values are merged in the registry's order, the first value for
a key winning, so yours fills in what the built-in readers leave out.

Run `bin/blush media:index` afterwards: the index reads every file again
when its readers change.
