# API errors

A request the [API](README.md) can't answer gets an error with a
status, and, for a change, the `field` it's about, when there is one:

```json
{ "error": { "code": "not_found", "message": "No entry has the id \"…\"." } }
```

| Code | Status | When |
|---|---|---|
| `invalid_query` | 400 | A parameter isn't one the address takes, or has a value it can't, or a token is in the address |
| `invalid_body` | 400 | A change isn't a JSON object, or has a key or value it can't take |
| `token_required` | 401 | The API, `status`, or a change needs a token, and there's none |
| `invalid_token` | 401 | The token is wrong, revoked, or expired, or its account is suspended |
| `https_required` | 403 | A token was sent over plain HTTP |
| `forbidden` | 403 | The token or its account may not make the change |
| `not_found` | 404 | Nothing has that id, or nothing is at that address (or, for a change, nothing the token may change) |
| `revision_mismatch` | 412 | The entry changed since that revision |
| `too_large` | 413 | An upload is larger than the site allows |
| `unprocessable` | 422 | The change can't be made as asked: a slug that's taken, a relationship out of its limits, an entry in the trash |
| `revision_required` | 428 | A change to an entry came without its revision in `If-Match` |
| `too_many_attempts` | 429 | Too many tokens that weren't one came from your address; try later |
| `too_many_writes` | 429 | The token made as many changes this hour as the site allows |

The `code` stays the same; the message may change.
