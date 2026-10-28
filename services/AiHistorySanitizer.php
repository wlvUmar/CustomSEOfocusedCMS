<?php
// path: ./services/AiHistorySanitizer.php
// AI Studio inbound transcript validators. Extracted from AiStudioController — no behavior change.

class AiHistorySanitizer {

    // Fallback only (used when the DB has no history for the session); the turn-based window is applied by AiSessionStore.
    private const MAX_HISTORY_MESSAGES = 90;
    private const MAX_INPUT_MESSAGES = 400;

    public static function sanitizeHistory($history): array {
        $decoded = json_decode((string)$history, true);
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $turn) {
                if (!is_array($turn)) continue;
                $role = in_array($turn['role'] ?? '', ['user', 'assistant', 'tool'], true) ? $turn['role'] : null;
                if ($role === 'tool' && empty($turn['tool_call_id'])) continue;
                $content = (string)($turn['content'] ?? '');
                $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
                // Only real injection shapes ("system:", "assistant (system)", "ignore previous instructions") — plain prompts that merely start with the word "system" pass through.
                if ($role === 'user' && preg_match('/^\s*(system\s*:|assistant\s*\(system\)|ignore\s+previous\s+instructions)/i', $content)) {
                    $content = '[filtered system-like prefix] ' . ltrim(preg_replace('/^\s*(system\s*:|assistant\s*\(system\)|ignore\s+previous\s+instructions)[:\-]*/i', '', $content));
                }
                if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000) . "\n…[truncated]";
                $hasCalls = $role === 'assistant' && !empty($turn['tool_calls']) && is_array($turn['tool_calls']);
                if ($role && ($content !== '' || $hasCalls)) {
                    $entry = ['role' => $role, 'content' => $content];
                    if ($role === 'tool' && !empty($turn['tool_call_id'])) {
                        $tcid = substr((string)$turn['tool_call_id'], 0, 64);
                        $entry['tool_call_id'] = $tcid;
                    }
                    if ($role === 'assistant' && isset($turn['tool_calls']) && is_array($turn['tool_calls'])) {
                        $validCalls = [];
                        foreach ($turn['tool_calls'] as $tc) {
                            if (!is_array($tc) || !isset($tc['function'])) continue;
                            $id = (string)($tc['id'] ?? '');
                            $name = (string)($tc['function']['name'] ?? '');
                            $args = (string)($tc['function']['arguments'] ?? '{}');
                            if ($id === '' || $name === '' || strlen($id) > 64 || strlen($name) > 64 || strlen($args) > 65536) continue;
                            if (json_decode($args) === null && $args !== '{}' && $args !== '') continue;
                            $validCalls[] = ['id'=>$id,'type'=>'function','function'=>['name'=>$name,'arguments'=>$args]];
                        }
                        if (!empty($validCalls)) $entry['tool_calls'] = $validCalls;
                    }
                    $hashParts = $role . ':' . ($entry['tool_call_id'] ?? '') . ':' . sha1($content);
                    if (isset($entry['tool_calls'])) $hashParts .= ':' . sha1(json_encode($entry['tool_calls']));
                    $lastHash = null;
                    if (!empty($messages)) {
                        $last = end($messages);
                        $lastHash = ($last['role'] ?? '') . ':' . ($last['tool_call_id'] ?? '') . ':' . sha1($last['content'] ?? '');
                        if (isset($last['tool_calls'])) $lastHash .= ':' . sha1(json_encode($last['tool_calls']));
                    }
                    if ($hashParts === $lastHash) continue;
                    // assistant message whose tool_calls all failed validation and has no text: nothing to keep
                    if ($content === '' && !isset($entry['tool_calls'])) continue;
                    $messages[] = $entry;
                }
                if (count($messages) >= self::MAX_INPUT_MESSAGES) break;
            }
            // Keep the NEWEST messages (the old `break` at 48 kept the oldest).
            if (count($messages) > self::MAX_HISTORY_MESSAGES) {
                $messages = array_slice($messages, -self::MAX_HISTORY_MESSAGES);
            }
        }
        return $messages;
    }

    public static function sanitizeApproved($approved): array {
        $decoded = json_decode((string)$approved, true);
        if (!is_array($decoded)) return [];
        $out = [];
        $seen = [];
        foreach ($decoded as $id) {
            if (count($out) >= 20) break; // cap array size (C)
            if (is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id) && !isset($seen[$id])) {
                $seen[$id] = true;
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Validate the interrupted tool context a follow-up run (Approve/Deny)
     * sends back: an assistant message carrying exactly one tool_call,
     * immediately followed by its tool result. Strictly data — never
     * auto-executed; it just gives the model the exact call to re-issue.
     */
    public static function sanitizePending($pending): array {
        $decoded = json_decode((string)$pending, true);
        if (!is_array($decoded) || count($decoded) < 2) return [];
        $out = [];

        $assistant = $decoded[0] ?? null;
        if (is_array($assistant) && ($assistant['role'] ?? '') === 'assistant') {
            $toolCalls = $assistant['tool_calls'] ?? null;
            if (is_array($toolCalls) && count($toolCalls) === 1) {
                $tc = $toolCalls[0];
                $fn = is_array($tc) ? ($tc['function'] ?? null) : null;
                if (is_array($tc) && is_array($fn)) {
                    $id = (string)($tc['id'] ?? '');
                    $name = (string)($fn['name'] ?? '');
                    $arguments = (string)($fn['arguments'] ?? '{}');
                    // 06-06: reject oversize IDs instead of silent substr truncation (prevents ID collision pairing wrong tool)
                    if (strlen($id) > 64 || strlen($name) > 64) return [];
                    if ($id !== '' && $name !== '' && strlen($arguments) <= 65536
                        && json_decode($arguments) !== null) {
                        $out[] = [
                            'role' => 'assistant',
                            'content' => (string)($assistant['content'] ?? ''),
                            'tool_calls' => [[
                                'id' => $id,
                                'type' => 'function',
                                'function' => [
                                    'name' => $name,
                                    'arguments' => $arguments,
                                ],
                            ]],
                        ];
                    }
                }
            }
        }

        $tool = $decoded[1] ?? null;
        if (is_array($tool) && ($tool['role'] ?? '') === 'tool' && $out !== []) {
            $callId = (string)($tool['tool_call_id'] ?? '');
            $content = (string)($tool['content'] ?? '');
            if (strlen($callId) > 64) return [];
            if ($callId !== '' && $content !== '' && strlen($content) <= 65536
                && $callId === ($out[0]['tool_calls'][0]['id'] ?? '')) {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => $content,
                ];
            } else {
                return [];
            }
        } else {
            return [];
        }

        return $out;
    }
}
