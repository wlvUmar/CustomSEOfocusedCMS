<?php
// path: ./controllers/admin/AuditLogController.php

class AuditLogController extends Controller {
    private const PER_PAGE = 50;

    public function index() {
        $this->requireAuth();

        $filters = [];
        foreach (['entity_type', 'action', 'source', 'q'] as $k) {
            $filters[$k] = trim((string)($_GET[$k] ?? ''));
        }
        $filters['entity_id'] = (int)($_GET['entity_id'] ?? 0) ?: '';
        foreach (['from', 'to'] as $k) {
            $v = trim((string)($_GET[$k] ?? ''));
            $filters[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        }

        $page = max(1, (int)($_GET['p'] ?? 1));
        $total = AuditLog::count($filters);
        $rows = AuditLog::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        foreach ($rows as &$r) {
            $decoded = $r['changes'] !== null ? json_decode($r['changes'], true) : null;
            $r['changes'] = is_array($decoded) ? $decoded : [];
        }
        unset($r);

        $this->view('admin/audit-log/index', [
            'pageName' => 'audit-log',
            'rows' => $rows,
            'filters' => $filters,
            'page' => $page,
            'pages' => max(1, (int)ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }
}
