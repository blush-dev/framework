# Caching and other sites

The [API](README.md)'s answers are kept until your content changes,
like your pages, and browsers and caches may keep them for a minute
(`maxAge`). Each has an `ETag`, so a client that asks again with
`If-None-Match` gets a quick `304` when nothing changed. Answers to a
request with a [token](tokens.md) are never cached or shared.

Any site may call the API from a browser
(`Access-Control-Allow-Origin: *`): an answer without a token is
public, and a browser never sends a token on its own, only a script
you give one to. Keep a token out of pages anyone can read; a front
end should use it on its server.
