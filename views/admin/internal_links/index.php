<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="git-branch"></i> Менеджер внутренних ссылок</h1>
    <div class="btn-group">
        <button type="button" data-action="expand" class="btn btn-secondary" aria-label="Развернуть всё">
            <i data-feather="maximize-2"></i>
        </button>
        <button type="button" data-action="collapse" class="btn btn-secondary" aria-label="Свернуть всё">
            <i data-feather="minimize-2"></i>
        </button>
        <button type="button" data-action="auto" class="btn btn-primary">
            <i data-feather="zap"></i> Авто-связка
        </button>
        <button type="button" data-action="bulk" class="btn btn-secondary">
            <i data-feather="layers"></i> Массово
        </button>
    </div>
</div>

<!-- Network Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon c-si-blue">
            <i data-feather="file-text"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_pages'] ?></h3>
            <p>Всего страниц</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-si-grn">
            <i data-feather="link"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_links'] ?></h3>
            <p>Всего ссылок</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-si-amb">
            <i data-feather="activity"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['density_percentage'] ?>%</h3>
            <p>Плотность сети</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-si-pur">
            <i data-feather="folder"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_root_pages'] ?? 0 ?></h3>
            <p>Корневых страниц</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-si-cyan">
            <i data-feather="layers"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['hierarchy_depth'] ?? 0 ?></h3>
            <p>Макс. глубина</p>
        </div>
    </div>

    <div class="stat-card <?= ($stats['orphan_pages'] ?? 0) > 0 ? 'stat-warning' : '' ?>">
        <div class="stat-icon c-si-red">
            <i data-feather="alert-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['orphan_pages'] ?></h3>
            <p>Орфаны</p>
        </div>
    </div>
</div>

<div class="info-banner">
    <i data-feather="info"></i>
    <div>
        <strong>Плотность сети:</strong> <?= $stats['density_percentage'] ?>%
        (<?= $stats['total_links'] ?> из <?= $stats['max_possible_links'] ?> возможных связей)
        <br>
        <strong>В среднем:</strong> <?= $stats['avg_links_per_page'] ?> ссылок на страницу
    </div>
</div>

<!-- Filter Buttons -->
<div class="section-header">
    <h2>Страницы и статус ссылок</h2>
    <div class="filter-buttons">
        <button type="button" class="filter-btn active" data-filter="all">Все</button>
        <button type="button" class="filter-btn" data-filter="root">Корни</button>
        <button type="button" class="filter-btn" data-filter="orphan">Орфаны</button>
    </div>
</div>

<!-- Pages Table -->
<table class="data-table">
    <thead>
        <tr>
            <th class="c-w40">
                <input type="checkbox" id="select-all">
            </th>
            <th>Страница</th>
            <th>Иерархия</th>
            <th class="c-th-c">Исходящие</th>
            <th class="c-th-c">Входящие</th>
            <th class="c-th-c">Виджет</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pages as $page):
            $isRoot = empty($page['parent_id']);
            $hasChildren = !empty($page['children']);
            $isOrphan = $page['outgoing_links'] == 0 && $page['incoming_links'] == 0 && $isRoot;
            $depth = $page['level'] ?? 0;
            $indent = $depth * 25;
        ?>
        <tr class="page-row depth-<?= $depth ?>"
            data-page-id="<?= $page['id'] ?>"
            data-is-root="<?= $isRoot ? '1' : '0' ?>"
            data-is-child="<?= !$isRoot ? '1' : '0' ?>"
            data-is-orphan="<?= $isOrphan ? '1' : '0' ?>">
            <td>
                <input type="checkbox" class="page-checkbox" value="<?= $page['id'] ?>">
            </td>
            <td>
                <div class="c-flex" data-indent="<?= $indent ?>">
                    <?php if ($depth > 0): ?>
                        <span class="c-arrow">↳</span>
                    <?php endif; ?>

                    <?php if ($hasChildren): ?>
                        <button class="toggle-children" data-page-toggle="<?= $page['id'] ?>" type="button" aria-label="Показать/скрыть дочерние">
                            <i data-feather="chevron-down"></i>
                        </button>
                    <?php else: ?>
                        <span class="toggle-placeholder"></span>
                    <?php endif; ?>

                    <div>
                        <strong><?= e($page['title_ru']) ?></strong>
                        <?php if ($isOrphan): ?>
                        <span class="badge badge-danger c-ml8">Орфан</span>
                        <?php endif; ?>
                        <div class="c-sub2">
                            <?= e($page['slug']) ?>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="hierarchy-info">
                    <?php if ($page['parent']): ?>
                        <div class="hierarchy-badge">
                            <i data-feather="corner-down-right"></i>
                            Подстраница: <?= e($page['parent']['title_ru']) ?>
                        </div>
                    <?php else: ?>
                        <span class="hierarchy-badge root">
                            <i data-feather="folder"></i> Корень
                        </span>
                    <?php endif; ?>

                    <?php if ($hasChildren): ?>
                        <span class="hierarchy-badge children">
                            <i data-feather="layers"></i> <?= count($page['children']) ?> подстраниц
                        </span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="c-td-c">
                <span class="badge c-badge-blue">
                    <?= $page['outgoing_links'] ?>
                </span>
            </td>
            <td class="c-td-c">
                <span class="badge c-badge-grn">
                    <?= $page['incoming_links'] ?>
                </span>
            </td>
            <td class="c-td-c">
                <?php if ($page['show_link_widget']): ?>
                <span class="badge badge-success">ВКЛ</span>
                <?php else: ?>
                <span class="badge badge-secondary">ВЫКЛ</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="action-buttons">
                    <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $page['id'] ?>"
                       class="btn btn-sm" title="Управлять ссылками">
                        <i data-feather="edit"></i> Управлять
                    </a>
                    <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>"
                       class="btn btn-sm btn-secondary" title="Редактировать страницу">
                        <i data-feather="file-text"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Auto-Connect Modal -->
<div id="auto-connect-modal" class="modal c-hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="zap"></i> Авто-связка страниц</h2>
            <button type="button" data-action="close-auto" class="close-btn" aria-label="Закрыть"><i data-feather="x"></i></button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-connect">
            <?= csrfField() ?>
            <div class="form-group">
                <label><strong>Стратегия:</strong></label>
                <select name="strategy" required>
                    <option value="hierarchy-aware" selected>С учётом иерархии (рекомендуется)</option>
                    <option value="all-to-all">Все-со-всеми: связать каждую страницу с каждой</option>
                    <option value="related">Умная: связать похожие по контенту</option>
                    <option value="popular-to-all">Хаб: связать всё с популярными</option>
                </select>
                <small>
                    <strong>С учётом иерархии:</strong> связывает родителя↔детей, детей↔соседей и похожие страницы по иерархии.
                </small>
            </div>

            <div class="form-group">
                <label><strong>Макс. ссылок на страницу:</strong></label>
                <input type="number" name="max_links" value="5" min="1" max="20">
            </div>

            <div class="alert alert-warning">
                <i data-feather="alert-triangle"></i>
                <strong>Внимание:</strong> будет создано много ссылок сразу. Существующие дублироваться не будут.
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="zap"></i> Связать
                </button>
                <button type="button" data-action="close-auto" class="btn btn-secondary">
                    <i data-feather="x-circle"></i> Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div id="bulk-modal" class="modal c-hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="layers"></i> Массовые действия</h2>
            <button type="button" data-action="close-bulk" class="close-btn" aria-label="Закрыть"><i data-feather="x"></i></button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/bulk-action" id="bulkForm">
            <?= csrfField() ?>
            <div class="form-group">
                <label><strong>Действие:</strong></label>
                <select name="action" id="bulk-action" required>
                    <option value="">Выберите действие...</option>
                    <option value="add-links">Добавить ссылку на выбранные страницы</option>
                    <option value="remove-links">Убрать ссылку с выбранных страниц</option>
                    <option value="enable-widget">Включить виджет ссылок</option>
                    <option value="disable-widget">Выключить виджет ссылок</option>
                </select>
            </div>

            <div class="form-group c-hidden" id="target-page-group">
                <label><strong>Целевая страница:</strong></label>
                <select name="target_page_id" id="target-page">
                    <option value="">Выберите страницу...</option>
                    <?php foreach ($pages as $page): ?>
                    <option value="<?= $page['id'] ?>"><?= e($page['title_ru']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Выбранные страницы будут ссылаться на неё / отвязаны от неё</small>
            </div>

            <input type="hidden" id="bulk-page-ids">

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="check"></i> Применить к <span id="selected-count">0</span> стр.
                </button>
                <button type="button" data-action="close-bulk" class="btn btn-secondary">
                    <i data-feather="x-circle"></i> Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<style>
:root { --teal: #0e9f8a; }
.c-hidden { display: none !important; }
.c-w40 { width: 40px; }
.c-th-c, .c-td-c { text-align: center; }
.c-flex { display: flex; align-items: center; }
.c-arrow { color: #9ca3af; margin-right: 4px; }
.c-ml8 { margin-left: 8px; }
.c-sub2 { font-size: 0.85em; color: #6b7280; }
.c-si-blue { background: #3b82f6; }
.c-si-grn { background: var(--teal); }
.c-si-amb { background: #f59e0b; }
.c-si-pur { background: #8b5cf6; }
.c-si-cyan { background: #06b6d4; }
.c-si-red { background: #ef4444; }
.c-badge-blue { background: #3b82f6; }
.c-badge-grn { background: var(--teal); }
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    margin-top: 30px;
}

.filter-buttons {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f3f4f6;
}

.filter-btn.active {
    background: var(--teal);
    color: white;
    border-color: var(--teal);
}

.hierarchy-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.hierarchy-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    background: #e5e7eb;
    color: #374151;
    width: fit-content;
}

.hierarchy-badge.root {
    background: #dbeafe;
    color: #1e40af;
}

.hierarchy-badge.children {
    background: #d1fae5;
    color: #065f46;
}

.hierarchy-badge svg {
    width: 14px;
    height: 14px;
}

.page-row[data-is-orphan="1"] {
    opacity: 0.7;
}

/* Collapsible Hierarchy Styles */
.page-row.hidden {
    display: none !important;
}

.toggle-children {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: #6b7280;
    transition: transform 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    margin-right: 4px;
}

.toggle-children:hover {
    color: #111827;
    background: #f3f4f6;
    border-radius: 4px;
}

.toggle-children.collapsed {
    transform: rotate(-90deg);
}

.toggle-placeholder {
    display: inline-block;
    width: 20px;
    height: 20px;
    margin-right: 4px;
}
</style>

<script src="<?= BASE_URL ?>/js/admin/internal-links.js"></script>

<script src="<?= BASE_URL ?>/js/admin/features/internal-links.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
