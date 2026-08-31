    <?php

require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/Page.php';

class FAQAdminController extends Controller {
    private $faqModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->faqModel = new FAQ();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();
        
        $faqs = $this->faqModel->getAll();
        $pages = $this->pageModel->getAll(true);
        
        $this->view('admin/faqs/list', ['faqs' => $faqs, 'pages' => $pages]);
    }

    public function edit($id = null) {
        $this->requireAuth();
        
        $faq = null;
        if ($id) {
            $faq = $this->faqModel->getById($id);
            if (!$faq) {
                $_SESSION['error'] = 'FAQ not found';
                $this->redirect('/admin/faqs');
            }
        }
        
        $pages = $this->pageModel->getAll(true);
        $this->view('admin/faqs/edit', ['faq' => $faq, 'pages' => $pages]);
    }

    public function save() {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/faqs');
        }
        
        $id = $_POST['id'] ?? null;
        // 06-05: UZ fallback — empty UZ falls back to RU to keep bilingual parity
        $qRu = trim($_POST['question_ru'] ?? '');
        $aRu = trim($_POST['answer_ru'] ?? '');
        $qUz = trim($_POST['question_uz'] ?? '');
        $aUz = trim($_POST['answer_uz'] ?? '');
        if ($qUz === '') $qUz = $qRu;
        if ($aUz === '') $aUz = $aRu;
        $data = [
            'page_slug' => trim($_POST['page_slug']),
            'question_ru' => $qRu,
            'question_uz' => $qUz,
            'answer_ru' => $aRu,
            'answer_uz' => $aUz,
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        try {
            if ($id) {
                $this->faqModel->update($id, $data);
                $_SESSION['success'] = 'FAQ updated successfully';
            } else {
                $this->faqModel->create($data);
                $_SESSION['success'] = 'FAQ created successfully';
            }
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        $this->redirect('/admin/faqs');
    }

    public function delete() {
        $this->requireAuth();
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/faqs');
            return;
        }
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->faqModel->delete($id);
            $_SESSION['success'] = 'FAQ deleted successfully';
        }
        
        $this->redirect('/admin/faqs');
    }
    public function bulkUpload() {
        $this->requireAuth();
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/faqs');
            return;
        }
        
        if (!isset($_FILES['file'])) {
            $_SESSION['error'] = 'No file uploaded';
            $this->redirect('/admin/faqs');
            return;
        }
        
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['csv', 'json'])) {
            $_SESSION['error'] = 'Only CSV and JSON files are supported';
            $this->redirect('/admin/faqs');
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
                if (empty($row['page_slug']) || empty($row['question_ru']) || empty($row['answer_ru'])) {
                    $errors[] = "Row " . ($index + 1) . ": Missing required fields";
                    continue;
                }
                
                // Prepare data — 06-05 UZ fallback
                $qRu = trim($row['question_ru']);
                $aRu = trim($row['answer_ru']);
                $qUz = trim($row['question_uz'] ?? '');
                $aUz = trim($row['answer_uz'] ?? '');
                if ($qUz === '') $qUz = $qRu;
                if ($aUz === '') $aUz = $aRu;
                $insertData = [
                    'page_slug' => trim($row['page_slug']),
                    'question_ru' => $qRu,
                    'question_uz' => $qUz,
                    'answer_ru' => $aRu,
                    'answer_uz' => $aUz,
                    'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
                    'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1
                ];
                
                try {
                    if ($this->faqModel->create($insertData)) {
                        $created++;
                    } else {
                        $errors[] = "Row " . ($index + 1) . ": Failed to create FAQ";
                    }
                } catch (InvalidArgumentException $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            $message = "Created $created FAQ(s)";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 5));
            }
            
            $_SESSION['success'] = $message;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Upload failed: ' . $e->getMessage();
        }
        
        $this->redirect('/admin/faqs');
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
        header('Content-Disposition: attachment; filename="faq_template.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'page_slug', 'question_ru', 'question_uz', 
            'answer_ru', 'answer_uz', 'sort_order', 'is_active'
        ]);
        
        // Example row
        fputcsv($output, [
            'home', 
            'Какой вопрос?', 
            'Qanday savol?',
            'Это ответ на русском языке', 
            'Bu o\'zbekcha javob',
            '0', 
            '1'
        ]);
        
        fclose($output);
        exit;
    }
}