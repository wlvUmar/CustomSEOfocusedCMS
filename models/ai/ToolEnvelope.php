<?php
// path: ./models/ai/ToolEnvelope.php
// Strict tool-result contract: every tool return is normalized to one shape
// so the agent loop and model see consistent status/code/retryable semantics.

class ToolEnvelope {

    public const STATUS_VERIFIED = 'verified';
    public const STATUS_APPLIED_UNVERIFIED = 'applied_unverified';
    public const STATUS_VERIFICATION_FAILED = 'verification_failed';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_NEEDS_APPROVAL = 'needs_approval';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_ERROR = 'error';

    public static function normalizeResult(string $name, string $callId, array $result): array {
        $status = self::STATUS_SUCCESS;
        $retryable = false;
        $code = null;

        if (isset($result['ok']) && $result['ok'] === true) {
            if (array_key_exists('verified', $result)) {
                if ($result['verified'] === true) {
                    $status = self::STATUS_VERIFIED;
                } elseif ($result['verified'] === false) {
                    $status = self::STATUS_VERIFICATION_FAILED;
                    $retryable = true;
                    $code = 'VERIFICATION_FAILED';
                }
            } elseif (self::isMutationTool($name)) {
                $status = self::STATUS_APPLIED_UNVERIFIED;
                $code = 'APPLIED_UNVERIFIED';
            }
            if (isset($result['truncated']) && $result['truncated'] === true) {
                $code = $code ?? 'TRUNCATED';
            }
        }

        if (isset($result['count']) && (int)$result['count'] === 0 && !isset($result['ok'])) {
            $status = self::STATUS_EMPTY;
        }

        $out = [
            'status' => $status,
            'tool' => $name,
            'call_id' => $callId,
            'data' => $result,
            'retryable' => $retryable,
        ];
        if ($code !== null) $out['code'] = $code;
        if (isset($result['_untrusted_tool_output'])) $out['_untrusted_tool_output'] = true;
        return $out;
    }

    public static function normalizeError(string $name, string $callId, string $message, ?Throwable $e = null): array {
        $code = 'TOOL_ERROR';
        $retryable = false;
        $msg = $message;

        if ($e instanceof InvalidArgumentException) {
            $code = 'VALIDATION_ERROR';
            $retryable = false;
        } elseif ($e instanceof RuntimeException) {
            $code = 'RUNTIME_ERROR';
            $retryable = true;
        }

        if (str_contains($msg, 'not found') || str_contains($msg, 'Not found')) {
            $code = 'NOT_FOUND';
        } elseif (str_contains($msg, 'Blocked in PLAN')) {
            $code = 'BLOCKED_PLAN';
            $status = self::STATUS_BLOCKED;
            return ['status' => $status, 'tool' => $name, 'call_id' => $callId, 'error' => ['code' => $code, 'message' => self::sanitizeError($msg), 'retryable' => false]];
        } elseif (str_contains($msg, 'GSC not connected') || str_contains($msg, 'not connected')) {
            $code = 'UNAVAILABLE';
            return ['status' => self::STATUS_UNAVAILABLE, 'tool' => $name, 'call_id' => $callId, 'error' => ['code' => $code, 'message' => self::sanitizeError($msg), 'retryable' => false]];
        } elseif (str_contains($msg, 'STALE_STATE') || str_contains($msg, 'stale')) {
            $code = 'STALE_STATE';
            $retryable = false;
        }

        if (str_contains(strtolower($msg), 'timeout') || str_contains($msg, 'HTTP 500') || str_contains($msg, 'Internal server')) {
            $retryable = true;
            $code = 'RETRYABLE_ERROR';
        }

        return [
            'status' => self::STATUS_ERROR,
            'tool' => $name,
            'call_id' => $callId,
            'error' => ['code' => $code, 'message' => self::sanitizeError($msg), 'retryable' => $retryable],
        ];
    }

    public static function normalizeApproval(string $name, string $callId, string $plan, string $reason, array $extra = []): array {
        return [
            'status' => self::STATUS_NEEDS_APPROVAL,
            'tool' => $name,
            'call_id' => $callId,
            'approval' => ['plan' => $plan, 'reason' => $reason] + $extra,
            'retryable' => false,
            'code' => 'NEEDS_APPROVAL',
        ];
    }

    private static function sanitizeError(string $msg): string {
        $msg = preg_replace('/ in \/.*?:\d+/', '', $msg) ?? $msg;
        $msg = preg_replace('/stack trace.*/is', '', $msg) ?? $msg;
        $msg = trim(preg_replace('/\s+/', ' ', $msg) ?? $msg);
        if (mb_strlen($msg) > 800) $msg = mb_substr($msg, 0, 800) . '…[truncated]';
        return $msg;
    }

    private static function isMutationTool(string $name): bool {
        return in_array($name, ['insert_section','set_section_style','wrap_section','set_custom_css','set_page_theme','set_rotation','create_faq','update_faq','delete_faq','restore_page_revision','str_replace_field','set_field','update_section','patch_section','add_section_marker','auto_sectionize','batch_update'], true);
    }

    public static function toToolContent(array $normalized): array {
        if (($normalized['status'] ?? '') === self::STATUS_NEEDS_APPROVAL) {
            return [
                'status' => 'approval_required',
                'call_id' => $normalized['call_id'],
                'plan' => $normalized['approval']['plan'] ?? '',
                'reason' => $normalized['approval']['reason'] ?? '',
            ];
        }
        if (isset($normalized['error'])) {
            return ['error' => $normalized['error']['message'], 'code' => $normalized['error']['code'], 'retryable' => $normalized['error']['retryable'], '_untrusted_tool_output' => true];
        }
        $data = $normalized['data'] ?? [];
        if (is_array($data)) {
            $data['_untrusted_tool_output'] = true;
            if (isset($normalized['code'])) $data['_status_code'] = $normalized['code'];
            $data['_status'] = $normalized['status'];
        }
        return $data;
    }
}
