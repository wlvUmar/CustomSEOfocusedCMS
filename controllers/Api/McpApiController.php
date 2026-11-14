<?php
// path: ./controllers/Api/McpApiController.php
// Bearer API used by the MCP server (tools/mcp-kuplyu). Runs AI Studio's own tool set.
//   GET  /api/mcp/v1/tools   -> tool definitions
//   POST /api/mcp/v1/call    -> {"name": "...", "args": {...}} -> tool result
// Auth: seo_api_tokens with scope "ai_tools" (hashed token, HTTPS only, 120 req/min).
// Calls always run in BUILD mode. Anything AI Studio would hold for approval is approved
// here automatically, so MCP work is never blocked. Page writes still snapshot to
// page_revisions, so they can be restored.

require_once BASE_PATH . '/models/SeoApiTokenStore.php';
require_once BASE_PATH . '/models/ai/AiToolRegistry.php';

class McpApiController {
    private const SCOPE = 'ai_tools';
    private const RATE_LIMIT_PER_MIN = 120;
    private const MAX_APPROVAL_ROUNDS = 3;

    private $token;

    public function tools(): void {
        $this->authenticate();
        $this->send(['tools' => AiToolRegistry::definitions(), 'mode' => 'build']);
    }

    public function call(): void {
        $this->authenticate();
        $body = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($body) || !isset($body['name']) || !is_string($body['name']) || $body['name'] === '') {
            $this->send(['error' => 'invalid_body', 'expected' => '{"name": "tool_name", "args": {...}}'], 400);
        }
        $name = $body['name'];
        $args = (isset($body['args']) && is_array($body['args'])) ? $body['args'] : [];

        $approved = [];
        $out = null;
        for ($round = 0; $round <= self::MAX_APPROVAL_ROUNDS; $round++) {
            $out = AiToolRegistry::execute($name, $args, $approved, 'build');
            if (($out['type'] ?? '') !== 'approval') break;
            // BUILD is not blocked: approve what the tool asked for, then run it again.
            $ids = !empty($out['call_ids']) ? $out['call_ids'] : [$out['call_id']];
            $approved = array_values(array_unique(array_merge($approved, $ids)));
        }

        error_log(sprintf('[mcp] token=%d tool=%s result=%s', (int)$this->token['id'], $name, $out['type'] ?? 'unknown'));
        $this->send($out ?? ['type' => 'error', 'message' => 'no result']);
    }

    private function authenticate(): void {
        header('Cache-Control: no-store');
        if (!$this->isHttps()) {
            $this->send(['error' => 'https_required'], 403);
        }
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/', trim($auth), $m)) {
            header('WWW-Authenticate: Bearer realm="kuplyu-mcp"');
            $this->send(['error' => 'unauthorized'], 401);
        }
        $row = SeoApiTokenStore::findActiveByToken($m[1]);
        if (!$row) {
            header('WWW-Authenticate: Bearer realm="kuplyu-mcp", error="invalid_token"');
            $this->send(['error' => 'unauthorized'], 401);
        }
        if (!in_array(self::SCOPE, explode(',', $row['scopes']), true)) {
            $this->send(['error' => 'insufficient_scope', 'required' => self::SCOPE], 403);
        }
        if (!SeoApiTokenStore::rateLimitOk($row['id'], self::RATE_LIMIT_PER_MIN)) {
            header('Retry-After: 60');
            $this->send(['error' => 'rate_limited'], 429);
        }
        SeoApiTokenStore::touch($row['id']);
        $this->token = $row;
    }

    private function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (str_contains($_SERVER['HTTP_CF_VISITOR'] ?? '', '"scheme":"https"'))
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
    }

    private function send($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
}
