# AI settings

**Settings → AI** sets what the site offers AI tools: Markdown copies,
`llms.txt`, what AI crawlers are asked, the MCP server, and the tools
connected apps can use.

## Markdown copies and llms.txt

- Whether every page has a Markdown copy and the site has `llms.txt`,
  with a link to view it and how many pages it lists.
- Whether the site also has `llms-full.txt` (off by default, locked
  while the Markdown copies are off, and, once served, with its size
  and a warning when it's too big for the page cache).
- The types it lists (each content type chooses with **Listed in
  `llms.txt`** on its screen; new types of terms and profiles start
  off), and the description it uses.

## AI crawlers

Which kinds of AI crawler `robots.txt` asks to stay away: training
crawlers, AI search crawlers, and fetchers acting for a person, each a
switch (**Asked to Stay Away** or **Allowed**) saying what its crawlers
do and naming its bots (see
[Configuration](../../reference/configuration/README.md)). It warns
when the choices aren't used: outside production, or with your own
`robots.txt`.

## MCP Server

Under **MCP Server**: whether AI apps can connect (off by default), the
server's address with **Copy**, and which roles may connect apps.

## Tools

Last, **Tools**: what a connected app can be asked to do, as one list
grouped by who added each tool (Core, or a plugin by name), with what
each needs; select a tool to read what it does, or **Show
Descriptions** to open them all.

See [AI apps and MCP](../../features/mcp/README.md).
