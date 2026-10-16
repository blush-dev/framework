# Faster media on a live site

By default, Blush's PHP code streams each media file. On a live site, let
your web server hand them out directly instead.

## Publishing media

```sh
bin/blush media:publish          # link user/media into public/
bin/blush media:publish --copy   # copy the files, for hosts without symlinks
```

With `--copy`, run it again whenever you add media.

## Apache

Either way, `media:publish` writes an `.htaccess` in the folder the web
server serves (`user/media/.htaccess` when linked, `public/media/.htaccess`
when copied), so Apache serves media as Blush does: no script ever runs
there, even one named like `shell.php.jpg`; files are sent with
`X-Content-Type-Options: nosniff`; and SVGs are sandboxed. It keeps the
file current each time it runs. If the folder already has an `.htaccess`
of your own, it's left alone and you're warned; to take over the one
`media:publish` wrote, remove its first line.

## nginx and other servers

On nginx, or another server that doesn't read `.htaccess`, add the same
rules to its config. For nginx, with media at `/media`:

```nginx
location ^~ /media/ {
	add_header X-Content-Type-Options nosniff always;

	location ~* \.(php\d*|pht|phtml|phar|phps|cgi|pl|py|sh|shtml|asp|aspx|jsp)(\.|$) {
		deny all;
	}

	location ~* \.svg$ {
		add_header X-Content-Type-Options nosniff always;
		add_header Content-Security-Policy sandbox always;
	}
}
```
