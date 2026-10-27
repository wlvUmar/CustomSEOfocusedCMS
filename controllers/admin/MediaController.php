<?php
require_once BASE_PATH . '/models/Media.php';
require_once BASE_PATH . '/models/PageMedia.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/MediaService.php';

class MediaController extends Controller {
    private $mediaModel;
    private $pageMediaModel;
    private $mediaService;

    public function __construct() {
        parent::__construct();
        $this->mediaModel = new Media();
        $this->pageMediaModel = new PageMedia();
        $this->mediaService = new MediaService($this->mediaModel, $this->pageMediaModel);
    }

    private function getCsrfToken() {
        return $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
    }

    private function thumbUrlFor($filename) {
        return $this->mediaService->thumbUrlFor($filename);
    }

    public function listJson() {
        $this->requireAuth();
        $filter = $_GET['filter'] ?? 'all';
        $pageId = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
        $q = trim($_GET['q'] ?? $_GET['search'] ?? '');
        $limit = max(1, min(200, intval($_GET['limit'] ?? 100)));
        $offset = max(0, intval($_GET['offset'] ?? 0));

        if ($filter === 'unused') {
            $all = $this->pageMediaModel->getUnusedMedia();
        } elseif ($filter === 'used') {
            $all = $this->mediaModel->getAll();
            $all = array_values(array_filter($all, function($item){
                $c = Database::getInstance()->fetchOne("SELECT COUNT(*) as cnt FROM page_media WHERE media_id=?", [$item['id']]);
                return intval($c['cnt'] ?? 0) > 0;
            }));
        } elseif ($filter === 'attached' && $pageId) {
            $all = $this->pageMediaModel->getPageMedia($pageId);
            // normalize: getPageMedia returns pm.* + m.* with media_id, map to media shape
            $norm = [];
            foreach ($all as $r) {
                $norm[] = [
                    'id' => intval($r['media_id']),
                    'media_id' => intval($r['media_id']),
                    'filename' => $r['filename'],
                    'original_name' => $r['original_name'],
                    'file_size' => $r['file_size'],
                    'mime_type' => $r['mime_type'],
                    'uploaded_at' => $r['uploaded_at'] ?? null,
                    'section' => $r['section'] ?? null,
                ];
            }
            $all = $norm;
        } else {
            $all = $this->mediaModel->getAll();
        }

        // Enrich
        $attachedIds = [];
        if ($pageId) {
            $rows = $this->pageMediaModel->getPageMedia($pageId);
            foreach ($rows as $r) $attachedIds[intval($r['media_id'])] = $r['section'] ?? 'content';
        }
        $out = [];
        foreach ($all as $item) {
            $mid = intval($item['media_id'] ?? $item['id'] ?? 0);
            if (!$mid) continue;
            if ($q !== '' && stripos($item['original_name'] ?? '', $q) === false && stripos($item['filename'] ?? '', $q) === false) continue;
            $cntRow = Database::getInstance()->fetchOne("SELECT COUNT(*) as cnt FROM page_media WHERE media_id=?", [$mid]);
            $usage = intval($cntRow['cnt'] ?? 0);
            $pages = $usage > 0 ? $this->pageMediaModel->getMediaPages($mid) : [];
            $dims = function_exists('getImageDimensions') ? getImageDimensions($item['filename']) : null;
            $out[] = [
                'id' => $mid,
                'filename' => $item['filename'],
                'original_name' => $item['original_name'] ?? $item['filename'],
                'file_size' => intval($item['file_size'] ?? 0),
                'mime_type' => $item['mime_type'] ?? '',
                'uploaded_at' => $item['uploaded_at'] ?? null,
                'usage_count' => $usage,
                'pages' => $pages,
                'is_attached_to_page' => isset($attachedIds[$mid]),
                'attached_section' => $attachedIds[$mid] ?? null,
                'thumb_url' => $this->thumbUrlFor($item['filename']),
                'url' => UPLOAD_URL . $item['filename'],
                'width' => $dims['width'] ?? null,
                'height' => $dims['height'] ?? null,
            ];
        }

        // Search already filtered, paginate
        $total = count($out);
        $out = array_slice($out, $offset, $limit);

        $this->json(['success'=>true, 'media'=>$out, 'total'=>$total, 'filter'=>$filter, 'page_id'=>$pageId ?: null]);
    }

    public function index() {
        $this->requireAuth();
        
        // Get filter parameters
        $filter = $_GET['filter'] ?? 'all';
        $pageId = $_GET['page_id'] ?? null;
        $attachSlot = $_GET['attach_slot'] ?? $_GET['slot'] ?? null;
        $format = $_GET['format'] ?? null;
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        // JSON mode for inline picker: ?format=json or Accept: application/json with explicit json flag
        if ($format === 'json' || (strpos($accept,'application/json') !== false && isset($_GET['json']))) {
            return $this->listJson();
        }

        // When coming from page editor (page_id present), show ALL media by default
        // but annotate which are attached to that page. User can still filter.
        if ($pageId) {
            $pageModelTmp = new Page();
            $page = $pageModelTmp->getById($pageId);
            // Build attached set for annotation
            $attachedRows = $this->pageMediaModel->getPageMedia($pageId);
            $attachedMap = [];
            foreach ($attachedRows as $r) {
                $attachedMap[intval($r['media_id'])] = $r;
            }

            if ($filter === 'unused') {
                $media = $this->pageMediaModel->getUnusedMedia();
            } elseif ($filter === 'attached') {
                // Only media attached to this page
                $media = [];
                foreach ($attachedRows as $r) {
                    $media[] = [
                        'id' => intval($r['media_id']),
                        'media_id' => intval($r['media_id']),
                        'filename' => $r['filename'],
                        'original_name' => $r['original_name'],
                        'file_size' => $r['file_size'],
                        'mime_type' => $r['mime_type'],
                        'uploaded_at' => $r['uploaded_at'] ?? null,
                        'section' => $r['section'] ?? null,
                        'is_attached_to_page' => true,
                    ];
                }
            } else {
                // Normal: all media
                $media = $this->mediaModel->getAll();
                if ($filter === 'used') {
                    // filter to used only
                    $media = array_values(array_filter($media, function($item){
                        $c = Database::getInstance()->fetchOne("SELECT COUNT(*) as cnt FROM page_media WHERE media_id=?", [$item['id']]);
                        return intval($c['cnt'] ?? 0) > 0;
                    }));
                }
            }

            // Enrich usage + annotation
            foreach ($media as &$item) {
                $mid = intval($item['media_id'] ?? $item['id'] ?? 0);
                $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ?";
                $result = Database::getInstance()->fetchOne($sql, [$mid]);
                $item['usage_count'] = intval($result['count'] ?? 0);
                $item['is_attached_to_page'] = isset($attachedMap[$mid]);
                if (isset($attachedMap[$mid])) {
                    $item['attached_section'] = $attachedMap[$mid]['section'] ?? null;
                }
                if (intval($item['usage_count']) > 0) {
                    $item['pages'] = $this->pageMediaModel->getMediaPages($mid);
                } elseif (!empty($page) && isset($attachedMap[$mid])) {
                    // Ensure at least current page shown for attached items
                    $slug = $page['slug'] ?? ($page['title_ru'] ?? '');
                    if ($slug !== '') {
                        $item['pages'] = [[
                            'page_id' => $page['id'] ?? intval($pageId),
                            'slug' => $slug,
                            'section' => $attachedMap[$mid]['section'] ?? null
                        ]];
                    }
                }
                // Thumb url for fast admin grid
                $item['thumb_url'] = $this->thumbUrlFor($item['filename'] ?? '');
                if (function_exists('getImageDimensions')) {
                    $dims = getImageDimensions($item['filename'] ?? '');
                    if ($dims) { $item['thumb_width'] = $dims['width']; $item['thumb_height'] = $dims['height']; }
                }
            }
            unset($item);
        } else {
            if ($filter === 'unused') {
                $media = $this->pageMediaModel->getUnusedMedia();
            } else {
                $media = $this->mediaModel->getAll();
                // Add usage count
                foreach ($media as &$item) {
                    $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ?";
                    $result = Database::getInstance()->fetchOne($sql, [$item['id']]);
                    $item['usage_count'] = intval($result['count'] ?? 0);
                    if (($item['usage_count'] ?? 0) > 0) {
                        $item['pages'] = $this->pageMediaModel->getMediaPages($item['id']);
                    }
                }
                
                if ($filter === 'used') {
                    $media = array_filter($media, function($item) {
                        return $item['usage_count'] > 0;
                    });
                }
            }
            // Add thumb urls for non-page_id view as well
            foreach ($media as &$item) {
                $item['thumb_url'] = $this->thumbUrlFor($item['filename'] ?? '');
                if (function_exists('getImageDimensions')) {
                    $dims = getImageDimensions($item['filename'] ?? '');
                    if ($dims) { $item['thumb_width'] = $dims['width']; $item['thumb_height'] = $dims['height']; }
                }
            }
            unset($item);
            $page = null;
        }
        
        $pageModel = new Page();
        $allPages = $pageModel->getAll(true);
        
        $this->view('admin/media/index', [
            'media' => $media,
            'allPages' => $allPages,
            'currentPage' => $page,
            'filter' => $filter,
            'attachSlot' => $attachSlot,
            'attachPageId' => $pageId ? intval($pageId) : null,
            'pageName' => 'media/index'
        ]);
    }

    public function upload() {
        $this->requireAuth();
        
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        if (!isset($_FILES['file'])) {
            $postMax = ini_get('post_max_size');
            $uploadMax = ini_get('upload_max_filesize');
            error_log('Media upload: missing $_FILES["file"]; post_max_size=' . $postMax . ' upload_max_filesize=' . $uploadMax);
            $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }
        
        $result = $this->mediaService->uploadSingle(
            $_FILES['file'],
            !empty($_POST['page_id']) ? $_POST['page_id'] : null,
            $_POST['section'] ?? 'content',
            $_POST['position'] ?? 0
        );

        if (empty($result['success'])) {
            $code = $result['error'] === 'Failed to save file' ? 500 : 400;
            $this->json(['success' => false, 'message' => $result['error']], $code);
        }

        $this->json([
            'success' => true,
            'media_id' => $result['media_id'],
            'filename' => $result['filename'],
            'url' => UPLOAD_URL . $result['filename']
        ]);
    }

    // ── Quick add: many images → matched to pages → uploaded + attached with RU/UZ alt text in one go ──

    // GET /admin/media/quick
    public function quick() {
        $this->requireAuth();
        $pages = array_map(static fn($p) => [
            'id' => (int)$p['id'],
            'slug' => (string)$p['slug'],
            'title_ru' => (string)($p['title_ru'] ?? ''),
            'title_uz' => (string)($p['title_uz'] ?? ''),
        ], (new Page())->getAll(true));
        require_once BASE_PATH . '/models/ImageGenerator.php';
        $genReady = true;
        $genUsed = 0;
        try {
            $genUsed = ImageGenerator::usedToday();
        } catch (Throwable $e) {
            $genReady = false; // image_generations table missing: migration 019 not applied yet
        }
        $this->view('admin/media/quick', [
            'pageName' => 'media-quick',
            'quickPages' => $pages,
            'maxUploadMb' => round(MAX_UPLOAD_SIZE / 1048576, 1),
            'gen' => [
                'configured' => ImageGenerator::isConfigured(),
                'ready' => $genReady,
                'models' => ImageGenerator::MODELS,
                'aspects' => ImageGenerator::ASPECTS,
                'limit' => ImageGenerator::dailyLimit(),
                'used' => $genUsed,
            ],
        ]);
    }

    // POST /admin/media/generate — ONE AI image per request. Returns base64; nothing is saved until the admin
    // reviews it in the Quick add list and uploads it (then it goes through quick-add like any other photo).
    public function generate() {
        $this->requireAuth();
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }
        require_once BASE_PATH . '/models/ImageGenerator.php';

        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($pageId <= 0 || !(new Page())->getById($pageId)) {
            $this->json(['success' => false, 'message' => 'Страница не найдена'], 400);
        }
        $model = (string)($_POST['model'] ?? array_key_first(ImageGenerator::MODELS));
        $aspect = (string)($_POST['aspect_ratio'] ?? '4:3');
        $prompt = (string)($_POST['prompt'] ?? '');

        // Generation can take a while: release the session lock and raise the time limit (ignored by hosts that forbid it).
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @set_time_limit(180);

        try {
            $r = ImageGenerator::generate($model, $prompt, $aspect, $pageId);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => 'Неверный параметр: ' . $e->getMessage()], 400);
        } catch (Throwable $e) {
            $code = (int)$e->getCode();
            $detail = mb_substr(trim(strip_tags($e->getMessage())), 0, 200);
            if ($code === 402) $msg = 'Недостаточно средств на балансе OpenRouter.';
            elseif ($code === 429) $msg = $detail !== '' ? $detail : 'Слишком много запросов — подождите минуту.';
            elseif ($code === 400) $msg = 'Модель отклонила запрос: ' . $detail;
            elseif ($code === 500) $msg = $detail;
            else $msg = 'Не удалось создать изображение' . ($detail !== '' ? ': ' . $detail : '.');
            error_log('[ImageGenerator] ' . $code . ' ' . $detail);
            $this->json(['success' => false, 'message' => $msg], $code >= 400 && $code < 600 ? $code : 502);
        }

        $used = 0;
        try { $used = ImageGenerator::usedToday(); } catch (Throwable $e) {}
        $this->json([
            'success' => true,
            'image' => $r['b64'],
            'media_type' => $r['media_type'],
            'cost' => $r['cost'],
            'model' => $r['model'],
            'used_today' => $used,
            'limit' => ImageGenerator::dailyLimit(),
        ]);
    }

    // POST /admin/media/quick-add  (one image per request; the page's JS sends them one by one)
    public function quickAdd() {
        $this->requireAuth();
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }
        if (!isset($_FILES['file'])) {
            $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }
        $pageId = (int)($_POST['page_id'] ?? 0);
        $page = $pageId > 0 ? (new Page())->getById($pageId) : null;
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found'], 400);
        }
        $section = (string)($_POST['section'] ?? 'gallery');
        if (!in_array($section, ['hero', 'banner', 'content', 'gallery'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid section'], 400);
        }
        $clean = static fn($v) => mb_substr(trim(strip_tags((string)$v)), 0, 255);

        $result = $this->mediaService->uploadSingle($_FILES['file'], null, $section, 0);
        if (empty($result['success'])) {
            $this->json(['success' => false, 'message' => $result['error']], $result['error'] === 'Failed to save file' ? 500 : 400);
        }
        $position = count($this->pageMediaModel->getPageMedia($pageId, $section));
        $this->mediaService->attach($pageId, $result['media_id'], [
            'section' => $section,
            'position' => $position,
            'alt_text_ru' => $clean($_POST['alt_text_ru'] ?? ''),
            'alt_text_uz' => $clean($_POST['alt_text_uz'] ?? ''),
            'caption_ru' => '',
            'caption_uz' => '',
            'width' => null,
            'alignment' => 'center',
            'css_class' => '',
            'lazy_load' => 1,
        ]);
        $this->json([
            'success' => true,
            'media_id' => $result['media_id'],
            'url' => UPLOAD_URL . $result['filename'],
            'page' => $page['slug'],
            'section' => $section,
            'position' => $position,
        ]);
    }

    public function delete() {
        $this->requireAuth();
        
        $tok = $this->getCsrfToken();
        if (empty($tok) || !validateCSRFToken($tok)) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        
        // Check if media is in use
        if ($this->pageMediaModel->isMediaUsed($id) && empty($_POST['force'])) {
            $pages = $this->pageMediaModel->getMediaPages($id);
            $this->json([
                'success' => false,
                'message' => 'Media is used on ' . count($pages) . ' page(s)',
                'usage_count' => count($pages)
            ], 400);
        }
        
        // If force delete, remove all page_media relationships first
        if (!empty($_POST['force'])) {
            Database::getInstance()->query(
                "DELETE FROM page_media WHERE media_id = ?",
                [$id]
            );
        }
        
        if ($this->mediaModel->delete($id)) {
            $_SESSION['success'] = 'Media deleted successfully';
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete'], 500);
        }
    }

    public function attachToPage() {
        $this->requireAuth();
        
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $pageId = $_POST['page_id'] ?? 0;
        $mediaId = $_POST['media_id'] ?? 0;
        
        if (!$pageId || !$mediaId) {
            $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        $data = [
            'section' => $_POST['section'] ?? 'content',
            'position' => $_POST['position'] ?? 0,
            'alt_text_ru' => $_POST['alt_text_ru'] ?? '',
            'alt_text_uz' => $_POST['alt_text_uz'] ?? '',
            'caption_ru' => $_POST['caption_ru'] ?? '',
            'caption_uz' => $_POST['caption_uz'] ?? '',
            'width' => $_POST['width'] ?? null,
            'alignment' => $_POST['alignment'] ?? 'center',
            'css_class' => $_POST['css_class'] ?? '',
            'lazy_load' => $_POST['lazy_load'] ?? 1
        ];

        $this->mediaService->attach($pageId, $mediaId, $data);
        
        $_SESSION['success'] = 'Media attached to page successfully';
        $this->json(['success' => true]);
    }

    public function detachFromPage() {
        $this->requireAuth();
        
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $pageId = $_POST['page_id'] ?? 0;
        $mediaId = $_POST['media_id'] ?? 0;
        $section = $_POST['section'] ?? null;
        
        $this->mediaService->detach($pageId, $mediaId, $section);
        
        $_SESSION['success'] = 'Media detached from page';
        $this->json(['success' => true]);
    }

    public function getMediaInfo() {
        $this->requireAuth();
        
        $mediaId = $_GET['id'] ?? 0;
        $media = Database::getInstance()->fetchOne("SELECT * FROM media WHERE id = ?", [$mediaId]);
        
        if (!$media) {
            $this->json(['success' => false, 'message' => 'Media not found'], 404);
        }
        
        $stats = $this->pageMediaModel->getUsageStats($mediaId);
        $pages = $this->pageMediaModel->getMediaPages($mediaId);
        
        $this->json([
            'success' => true,
            'media' => $media,
            'stats' => $stats,
            'pages' => $pages
        ]);
    }

    public function getAttachment() {
        $this->requireAuth();
        
        $mediaId = $_GET['media_id'] ?? 0;
        $pageId = $_GET['page_id'] ?? null;
        
        if (!$mediaId) {
            $this->json(['success' => false, 'message' => 'Missing media_id'], 400);
        }
        
        if ($pageId) {
            $attachment = $this->pageMediaModel->getAttachment($mediaId, $pageId);
        } else {
            $attachment = $this->pageMediaModel->getLastAttachment($mediaId);
        }
        
        if (!$attachment) {
            $this->json(['success' => false, 'message' => 'Attachment not found'], 404);
        }
        
        $this->json([
            'success' => true,
            'attachment' => [
                'page_id' => $attachment['page_id'] ?? null,
                'page_slug' => $attachment['slug'] ?? ($attachment['title_ru'] ?? ''),
                'section' => $attachment['section'] ?? 'content',
                'alt_text_ru' => $attachment['alt_text_ru'] ?? '',
                'alt_text_uz' => $attachment['alt_text_uz'] ?? '',
                'alignment' => $attachment['alignment'] ?? 'center',
                'width' => $attachment['width'] ?? ''
            ]
        ]);
    }

    public function bulkUpload() {
        $this->requireAuth();
        
        if (!validateCSRFToken($this->getCsrfToken())) {
            $isAjax = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            } else {
                $_SESSION['error'] = 'CSRF token validation failed';
                $this->redirect('/admin/media');
            }
            return;
        }
        
        $isAjax = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if (!isset($_FILES['files'])) {
            $postMax = ini_get('post_max_size');
            $uploadMax = ini_get('upload_max_filesize');
            error_log('Media bulk upload: missing $_FILES["files"]; post_max_size=' . $postMax . ' upload_max_filesize=' . $uploadMax);
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'No files uploaded'], 400);
            }
            $_SESSION['error'] = 'No files uploaded';
            $this->redirect('/admin/media');
        }
        
        $uploaded = 0;
        $failed = 0;
        $errors = [];
        $pageId = $_POST['page_id'] ?? null;
        $section = $_POST['section'] ?? 'content';
        $result = $this->mediaService->uploadBulk($_FILES['files'], $pageId, $section);
        $uploaded = $result['uploaded'];
        $failed = $result['failed'];
        $errors = $result['errors'];
        
        if ($isAjax) {
            $this->json([
                'success' => $uploaded > 0,
                'uploaded' => $uploaded,
                'failed' => $failed,
                'errors' => $errors,
                'message' => "Uploaded {$uploaded} files" . ($failed ? ", {$failed} failed" : '')
            ]);
        }
        
        $_SESSION['success'] = "Uploaded {$uploaded} files" . ($failed ? ", {$failed} failed" : '');
        $this->redirect('/admin/media' . ($pageId ? '?page_id=' . $pageId : ''));
    }

    public function bulkAction() {
        $this->requireAuth();
        
        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $action = $_POST['action'] ?? '';
        $mediaIds = $_POST['media_ids'] ?? [];
        
        if (empty($mediaIds)) {
            $this->json(['success' => false, 'message' => 'No media selected'], 400);
        }
        
        switch ($action) {
            case 'attach':
                $pageId = $_POST['page_id'] ?? 0;
                if (!$pageId) {
                    $this->json(['success' => false, 'message' => 'No page selected'], 400);
                }
                
                $section = $_POST['section'] ?? 'content';
                $count = $this->mediaService->bulkAttach($mediaIds, $pageId, $section);
                $_SESSION['success'] = $count . ' media items attached';
                break;
                
            case 'delete':
                $count = $this->mediaService->bulkDelete($mediaIds);
                $_SESSION['success'] = $count . ' media items deleted';
                break;
                
            default:
                $this->json(['success' => false, 'message' => 'Invalid action'], 400);
        }
        
        $this->json(['success' => true]);
    }

    public function regenerateVariants() {
        $this->requireAuth();

        if (!validateCSRFToken($this->getCsrfToken())) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $result = $this->mediaService->regenerate();
        if (empty($result['success'])) {
            $this->json($result, 500);
        }

        $this->json($result);
    }
}
