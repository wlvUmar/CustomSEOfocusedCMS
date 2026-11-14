// MCP server for the kuplyu-tashkent.uz CMS.
// Exposes AI Studio's tool set (models/ai/AiToolRegistry.php) through /api/mcp/v1/*.
//
// Env:
//   KUPLYU_API_TOKEN  Bearer token with scope "ai_tools" (required). Never commit it.
//   KUPLYU_API_BASE   default https://kuplyu-tashkent.uz
//
// Calls run in BUILD mode on the server; approvals are granted automatically there.

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { ListToolsRequestSchema, CallToolRequestSchema } from '@modelcontextprotocol/sdk/types.js';

const BASE = (process.env.KUPLYU_API_BASE || 'https://kuplyu-tashkent.uz').replace(/\/+$/, '');
const TOKEN = process.env.KUPLYU_API_TOKEN || '';

if (!TOKEN) {
  console.error('kuplyu-mcp: KUPLYU_API_TOKEN is not set');
  process.exit(1);
}
if (!BASE.startsWith('https://')) {
  console.error('kuplyu-mcp: KUPLYU_API_BASE must be https://');
  process.exit(1);
}

async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = { raw: text.slice(0, 2000) }; }
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${JSON.stringify(data).slice(0, 1000)}`);
  }
  return data;
}

// Tool definitions come from the server, so the MCP tool list always matches AI Studio's.
async function loadTools() {
  const data = await api('/api/mcp/v1/tools');
  return (data.tools || []).map((t) => ({
    name: t.function.name,
    description: t.function.description || '',
    inputSchema: t.function.parameters || { type: 'object', properties: {} },
  }));
}

const server = new Server(
  { name: 'kuplyu-cms', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

let toolCache = null;

server.setRequestHandler(ListToolsRequestSchema, async () => {
  if (!toolCache) toolCache = await loadTools();
  return { tools: toolCache };
});

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;
  try {
    const out = await api('/api/mcp/v1/call', { method: 'POST', body: { name, args: args || {} } });
    const isError = out.type === 'error';
    return {
      content: [{ type: 'text', text: JSON.stringify(out, null, 2) }],
      isError,
    };
  } catch (err) {
    return { content: [{ type: 'text', text: String(err.message || err) }], isError: true };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
