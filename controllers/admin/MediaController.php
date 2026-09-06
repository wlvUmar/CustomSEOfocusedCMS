<?php
require_once BASE_PATH . '/models/Media.php';
require_once BASE_PATH . '/models/PageMedia.php';
require_once BASE_PATH . '/models/Page.php';

class MediaController extends Controller {
    private $mediaModel;
    private $pageMediaModel;

    public function __construct() {
        parent::__construct();
        $this->mediaModel = new Media();
        $this->pageMediaModel = new PageMedia();
    }

    private function getCsrfToken() {
        return $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
    }

    private function thumbUrlFor($filename) {
        if (empty($filename)) return UPLOAD_URL . $filename;
        // Try derived 480 variant first (generated on upload via generateResponsiveImageVariants)
        if (function_exists('findExistingDerivedSources')) {
            $srcs = findExistingDerivedSources($filename, [480]);
            if (!empty($srcs['fallback'])) {
                // srcs fallback is like "/uploads/derived/name_w480.jpg 480w" — take first URL
                if (preg_match('/^(\S+)/', $srcs['fallback'], $m)) {
                    $derived = $m[1]; // e.g. /uploads/derived/xxx_w480.jpg
                    // findExistingDerivedSources returns path relative to site root; prefix with BASE_URL if needed
                    if (defined('BASE_URL') && BASE_URL !== '' && strpos($derived, '://') === false && $derived[0] === '/') {
                        return rtrim(BASE_URL, '/') . $derived;
                    }
                    return $derived;
                }
            }
        }
        // Fallback to original
        return UPLOAD_URL . $filename;
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
        
        $file = $_FILES['file'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            error_log('Media upload: invalid type ' . ($file['type'] ?? ''));
            $this->json(['success' => false, 'message' => 'Invalid file type'], 400);
        }
        
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            error_log('Media upload: file too large size=' . ($file['size'] ?? 0));
            $this->json(['success' => false, 'message' => 'File too large'], 400);
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $filepath = UPLOAD_PATH . $filename;
        
        // Create upload directory if not exists
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
        
        // Move file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $data = [
                'filename' => $filename,
                'original_name' => $file['name'],
                'file_size' => $file['size'],
                'mime_type' => $file['type']
            ];
            
            $mediaId = $this->mediaModel->create($data);
            generateResponsiveImageVariants($filename, getResponsiveImageWidths());
            
            // If page_id is provided, attach immediately
            if (!empty($_POST['page_id'])) {
                $this->pageMediaModel->attachMedia($_POST['page_id'], $mediaId, [
                    'section' => $_POST['section'] ?? 'content',
                    'position' => $_POST['position'] ?? 0
                ]);
            }
            
            $this->json([
                'success' => true,
                'media_id' => $mediaId,
                'filename' => $filename,
                'url' => UPLOAD_URL . $filename
            ]);
        } else {
            error_log('Media upload: move_uploaded_file failed to ' . $filepath);
            $this->json(['success' => false, 'message' => 'Failed to save file'], 500);
        }
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
        
        $this->pageMediaModel->attachMedia($pageId, $mediaId, $data);
        
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
        
        $this->pageMediaModel->detachMedia($pageId, $mediaId, $section);
        
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
        foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
                $failed++;
                $errors[] = $_FILES['files']['name'][$key] . ': Upload error';
                error_log('Media bulk upload: upload error code=' . $_FILES['files']['error'][$key]);
                continue;
            }
            
            $file = [
                'name' => $_FILES['files']['name'][$key],
                'type' => $_FILES['files']['type'][$key],
                'size' => $_FILES['files']['size'][$key]
            ];
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes) || $file['size'] > MAX_UPLOAD_SIZE) {
                $failed++;
                $errors[] = $file['name'] . ': Invalid file type or size';
                error_log('Media bulk upload: invalid type/size name=' . $file['name'] . ' type=' . ($file['type'] ?? '') . ' size=' . ($file['size'] ?? 0));
                continue;
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '_' . $key . '.' . $ext;
            $filepath = UPLOAD_PATH . $filename;
            
            if (move_uploaded_file($tmpName, $filepath)) {
                $mediaId = $this->mediaModel->create([
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ]);
                generateResponsiveImageVariants($filename, getResponsiveImageWidths());
                if ($pageId) {
                    $this->pageMediaModel->attachMedia($pageId, $mediaId, [
                        'section' => $section,
                        'position' => $uploaded
                    ]);
                }
                
                $uploaded++;
            } else {
                $failed++;
                $errors[] = $file['name'] . ': Failed to save file';
                error_log('Media bulk upload: move_uploaded_file failed to ' . $filepath);
            }
        }
        
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
                foreach ($mediaIds as $mediaId) {
                    $this->pageMediaModel->attachMedia($pageId, $mediaId, ['section' => $section]);
                }
                $_SESSION['success'] = count($mediaIds) . ' media items attached';
                break;
                
            case 'delete':
                foreach ($mediaIds as $mediaId) {
                    $this->mediaModel->delete($mediaId);
                }
                $_SESSION['success'] = count($mediaIds) . ' media items deleted';
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

        $result = regenerateImageVariants();
        if (empty($result['success'])) {
            $this->json($result, 500);
        }

        $this->json($result);
    }
}
