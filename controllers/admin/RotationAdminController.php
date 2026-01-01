<?php
// path: ./controllers/admin/RotationAdminController.php

require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Page.php';

class RotationAdminController extends Controller {
    private $rotationModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->rotationModel = new ContentRotation();
        $this->pageModel = new Page();
    }

    /**
     * Overview of all pages with rotation status
     */
    public function overview() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(true);
        $rotationStatus = [];
        
        foreach ($pages as $page) {
            if ($page['enable_rotation']) {
                $stats = $this->rotationModel->getCoverageStats($page['id']);
                $rotationStatus[] = [
                    'page' => $page,
                    'stats' => $stats
                ];
            }
        }
        
        // Get pages with incomplete rotation
        $incompletePages = $this->rotationModel->getPagesWithIncompleteRotation();
        
        $this->view('admin/rotations/overview', [
            'rotationStatus' => $rotationStatus,
            'incompletePages' => $incompletePages
        ]);
    }

    public function manage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
        }
        
        $rotations = $this->rotationModel->getByPageId($pageId);
        $months = $this->rotationModel->getMonths();
        $stats = $this->rotationModel->getCoverageStats($pageId);
        
        $this->view('admin/rotations/manage', [
            'page' => $page,
            'rotations' => $rotations,
            'months' => $months,
            'stats' => $stats
        ]);
    }

    public function edit($id = null, $pageId = null) {
        $this->requireAuth();
        
        $rotation = null;
        $page = null;
        $suggestedMonth = $_GET['month'] ?? null;
        
        if ($id) {
            $rotation = $this->rotationModel->getById($id);
            if (!$rotation) {
                $_SESSION['error'] = 'Rotation not found';
                $this->redirect('/admin/pages');
            }
            $page = $this->pageModel->getById($rotation['page_id']);
        } else if ($pageId) {
            $page = $this->pageModel->getById($pageId);
        }
        
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
        }
        
        $months = $this->rotationModel->getMonths();
        $stats = $this->rotationModel->getCoverageStats($page['id']);
        
        $this->view('admin/rotations/edit', [
            'rotation' => $rotation,
            'page' => $page,
            'months' => $months,
            'stats' => $stats,
            'suggestedMonth' => $suggestedMonth
        ]);
    }

    public function save() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $pageId = intval($_POST['page_id']);
        $activeMonth = intval($_POST['active_month']);
        $defaultFromPage = isset($_POST['default_from_page']);
        
        // If "default from page" button was clicked
        if (!$id && $defaultFromPage) {
            $result = $this->rotationModel->createDefaultFromPage($pageId, $activeMonth);
            if ($result) {
                $_SESSION['success'] = 'Content rotation created with page defaults';
            } else {
                $_SESSION['error'] = 'Failed to create rotation with defaults';
            }
            $this->redirect('/admin/rotations/manage/' . $pageId);
            return;
        }
        
        // Check for duplicate month
        if (!$id || ($id && $this->rotationModel->getById($id)['active_month'] != $activeMonth)) {
            if ($this->rotationModel->monthHasContent($pageId, $activeMonth)) {
                $_SESSION['error'] = 'This month already has content. Please edit the existing entry or choose a different month.';
                $this->redirect('/admin/rotations/manage/' . $pageId);
                return;
            }
        }
        
        $data = [
            'page_id' => $pageId,
            'title_ru' => trim($_POST['title_ru'] ?? ''),
            'title_uz' => trim($_POST['title_uz'] ?? ''),
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'description_ru' => trim($_POST['description_ru'] ?? ''),
            'description_uz' => trim($_POST['description_uz'] ?? ''),
            'active_month' => $activeMonth,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'meta_title_ru' => trim($_POST['meta_title_ru'] ?? '') ?: null,
            'meta_title_uz' => trim($_POST['meta_title_uz'] ?? '') ?: null,
            'meta_description_ru' => trim($_POST['meta_description_ru'] ?? '') ?: null,
            'meta_description_uz' => trim($_POST['meta_description_uz'] ?? '') ?: null,
            'meta_keywords_ru' => trim($_POST['meta_keywords_ru'] ?? '') ?: null,
            'meta_keywords_uz' => trim($_POST['meta_keywords_uz'] ?? '') ?: null,
            'og_title_ru' => trim($_POST['og_title_ru'] ?? '') ?: null,
            'og_title_uz' => trim($_POST['og_title_uz'] ?? '') ?: null,
            'og_description_ru' => trim($_POST['og_description_ru'] ?? '') ?: null,
            'og_description_uz' => trim($_POST['og_description_uz'] ?? '') ?: null,
            'og_image' => trim($_POST['og_image'] ?? '') ?: null,
            'jsonld_ru' => trim($_POST['jsonld_ru'] ?? '') ?: null,
            'jsonld_uz' => trim($_POST['jsonld_uz'] ?? '') ?: null
        ];
        
        if ($id) {
            $this->rotationModel->update($id, $data);
            $_SESSION['success'] = 'Content rotation updated successfully';
        } else {
            $this->rotationModel->create($data);
            $_SESSION['success'] = 'Content rotation created successfully';
        }
        
        $this->redirect('/admin/rotations/manage/' . $pageId);
    }

    /**
     * Clone rotation content to another month
     */
    public function clone() {
        $this->requireAuth();
        
        $sourceId = intval($_POST['source_id'] ?? 0);
        $targetMonth = intval($_POST['target_month'] ?? 0);
        
        if (!$sourceId || !$targetMonth) {
            $_SESSION['error'] = 'Invalid parameters';
            $this->redirect('/admin/rotations/overview');
            return;
        }
        
        $source = $this->rotationModel->getById($sourceId);
        if (!$source) {
            $_SESSION['error'] = 'Source rotation not found';
            $this->redirect('/admin/rotations/overview');
            return;
        }
        
        $result = $this->rotationModel->cloneToMonth($sourceId, $targetMonth);
        
        if ($result) {
            $_SESSION['success'] = 'Content cloned successfully to ' . $this->rotationModel->getMonthNameRu($targetMonth);
        } else {
            $_SESSION['error'] = 'Failed to clone content. Target month may already have content.';
        }
        
        $this->redirect('/admin/rotations/manage/' . $source['page_id']);
    }

    /**
     * Bulk operations
     */
    public function bulkAction() {
        $this->requireAuth();
        
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        $pageId = $_POST['page_id'] ?? null;
        
        if (empty($ids) || !is_array($ids)) {
            $_SESSION['error'] = 'No items selected';
            $this->redirect($pageId ? '/admin/rotations/manage/' . $pageId : '/admin/rotations/overview');
            return;
        }
        
        $ids = array_map('intval', $ids);
        
        switch ($action) {
            case 'activate':
                $this->rotationModel->bulkUpdateStatus($ids, 1);
                $_SESSION['success'] = count($ids) . ' rotation(s) activated';
                break;
                
            case 'deactivate':
                $this->rotationModel->bulkUpdateStatus($ids, 0);
                $_SESSION['success'] = count($ids) . ' rotation(s) deactivated';
                break;
                
            case 'delete':
                $this->rotationModel->bulkDelete($ids);
                $_SESSION['success'] = count($ids) . ' rotation(s) deleted';
                break;
                
            default:
                $_SESSION['error'] = 'Invalid action';
        }
        
        $this->redirect($pageId ? '/admin/rotations/manage/' . $pageId : '/admin/rotations/overview');
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $pageId = $_POST['page_id'] ?? null;
        
        if ($id) {
            $this->rotationModel->delete($id);
            $_SESSION['success'] = 'Content rotation deleted successfully';
        }
        
        if ($pageId) {
            $this->redirect('/admin/rotations/manage/' . $pageId);
        } else {
            $this->redirect('/admin/pages');
        }
    }

    /**
     * Preview rotation content without saving
     */
    public function preview() {
        $this->requireAuth();
        
        $contentRu = $_POST['content_ru'] ?? '';
        $contentUz = $_POST['content_uz'] ?? '';
        $lang = $_POST['lang'] ?? 'ru';
        
        // Simple preview rendering
        $content = $lang === 'uz' ? $contentUz : $contentRu;
        
        echo '<html><head><meta charset="UTF-8"><style>
            body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            h1, h2, h3 { color: #303034; }
        </style></head><body>';
        echo $content;
        echo '</body></html>';
        exit;
    }
        public function bulkUpload() {
            $this->requireAuth();
            
            if (!isset($_FILES['file'])) {
                $_SESSION['error'] = 'No file uploaded';
                $this->redirect('/admin/rotations/overview');
                return;
            }
            
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['csv', 'json'])) {
                $_SESSION['error'] = 'Only CSV and JSON files are supported';
                $this->redirect('/admin/rotations/overview');
                return;
            }
            
            try {
                $data = [];
                
                if ($ext === 'csv') {
                    $data = $this->parseCSV($file['tmp_name']);
                } else {
                    $content = file_get_contents($file['tmp_name']);
                    $data = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid JSON format');
                    }
                }
                
                $created = 0;
                $errors = [];
                
                foreach ($data as $index => $row) {
                    // Validate required fields
                    if (empty($row['page_id']) || empty($row['active_month'])) {
                        $errors[] = "Row " . ($index + 1) . ": Missing page_id or active_month";
                        continue;
                    }
                    
                    // Check if month already has content
                    if ($this->rotationModel->monthHasContent($row['page_id'], $row['active_month'])) {
                        $errors[] = "Row " . ($index + 1) . ": Month {$row['active_month']} already has content";
                        continue;
                    }
                    
                    // Prepare data with new fields
                    $insertData = [
                        'page_id' => (int)$row['page_id'],
                        'active_month' => (int)$row['active_month'],
                        'title_ru' => $row['title_ru'] ?? null,
                        'title_uz' => $row['title_uz'] ?? null,
                        'description_ru' => $row['description_ru'] ?? null,
                        'description_uz' => $row['description_uz'] ?? null,
                        'content_ru' => $row['content_ru'] ?? '',
                        'content_uz' => $row['content_uz'] ?? '',
                        'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1,
                        'meta_title_ru' => $row['meta_title_ru'] ?? null,
                        'meta_title_uz' => $row['meta_title_uz'] ?? null,
                        'meta_description_ru' => $row['meta_description_ru'] ?? null,
                        'meta_description_uz' => $row['meta_description_uz'] ?? null,
                        'meta_keywords_ru' => $row['meta_keywords_ru'] ?? null,
                        'meta_keywords_uz' => $row['meta_keywords_uz'] ?? null,
                        'og_title_ru' => $row['og_title_ru'] ?? null,
                        'og_title_uz' => $row['og_title_uz'] ?? null,
                        'og_description_ru' => $row['og_description_ru'] ?? null,
                        'og_description_uz' => $row['og_description_uz'] ?? null,
                        'og_image' => $row['og_image'] ?? null,
                        'jsonld_ru' => $row['jsonld_ru'] ?? null,
                        'jsonld_uz' => $row['jsonld_uz'] ?? null
                    ];
                    
                    if ($this->rotationModel->create($insertData)) {
                        $created++;
                    } else {
                        $errors[] = "Row " . ($index + 1) . ": Failed to create rotation";
                    }
                }
                
                $message = "Created $created rotation(s)";
                if (!empty($errors)) {
                    $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 5));
                }
                
                $_SESSION['success'] = $message;
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Upload failed: ' . $e->getMessage();
            }
            
            $this->redirect('/admin/rotations/overview');
        }

    private function parseCSV($filepath) {
        $data = [];
        $handle = fopen($filepath, 'r');
        
        // Get headers from first row
        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers);
        
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = array_combine($headers, $row);
        }
        
        fclose($handle);
        return $data;
    }

    public function downloadTemplate() {
        $this->requireAuth();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rotation_template.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers with keywords included
        fputcsv($output, [
            'page_id', 'active_month', 'title_ru', 'title_uz', 
            'description_ru', 'description_uz', 'content_ru', 'content_uz', 
            'is_active', 'meta_title_ru', 'meta_title_uz', 
            'meta_description_ru', 'meta_description_uz',
            'meta_keywords_ru', 'meta_keywords_uz'  // ADD THESE
        ]);
        
        // Example row with keywords
        fputcsv($output, [
            '1', '1', 'January Title RU', 'January Title UZ',
            'Brief description RU', 'Brief description UZ',
            'Full content RU', 'Full content UZ',
            '1', 'Meta title RU', 'Meta title UZ',
            'Meta description RU', 'Meta description UZ',
            'keyword1, keyword2, keyword3', 'kalit1, kalit2, kalit3'  // ADD THESE
        ]);
        
        fclose($output);
        exit;
    }
}