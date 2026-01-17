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

    public function index() {
        $this->requireAuth();
        
        // Get filter parameters
        $filter = $_GET['filter'] ?? 'all';
        $pageId = $_GET['page_id'] ?? null;
        
        if ($pageId) {
            $media = $this->pageMediaModel->getPageMedia($pageId);
            $pageModel = new Page();
            $page = $pageModel->getById($pageId);
            
            // Add usage count for page-specific media too
            foreach ($media as &$item) {
                $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ?";
                $result = Database::getInstance()->fetchOne($sql, [$item['media_id']]);
                $item['usage_count'] = $result['count'] ?? 0;
                if (!empty($page)) {
                    $slug = $page['slug'] ?? ($page['title_ru'] ?? '');
                    if ($slug !== '') {
                        $item['pages'] = [[
                            'page_id' => $page['id'] ?? ($pageId ?? null),
                            'slug' => $slug,
                            'section' => $item['section'] ?? null
                        ]];
                    }
                }
            }
        } else {
            if ($filter === 'unused') {
                $media = $this->pageMediaModel->getUnusedMedia();
            } else {
                $media = $this->mediaModel->getAll();
                // Add usage count
                foreach ($media as &$item) {
                    $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ?";
                    $result = Database::getInstance()->fetchOne($sql, [$item['id']]);
                    $item['usage_count'] = $result['count'] ?? 0;
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
            $page = null;
        }
        
        $pageModel = new Page();
        $allPages = $pageModel->getAll(true);
        
        $this->view('admin/media/index', [
            'media' => $media,
            'allPages' => $allPages,
            'currentPage' => $page,
            'filter' => $filter,
            'pageName' => 'media/index'
        ]);
    }

    public function upload() {
        $this->requireAuth();
        
        if (!isset($_FILES['file'])) {
            $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->json(['success' => false, 'message' => 'Invalid file type'], 400);
        }
        
        if ($file['size'] > MAX_UPLOAD_SIZE) {
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
            $this->json(['success' => false, 'message' => 'Failed to save file'], 500);
        }
    }

    public function delete() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
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
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
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
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
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
        
        $isAjax = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if (!isset($_FILES['files'])) {
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
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
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
}
