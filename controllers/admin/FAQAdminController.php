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
            http_response_code(403);
            die('CSRF token validation failed');
        }
        
        $id = $_POST['id'] ?? null;
        $data = [
            'page_slug' => trim($_POST['page_slug']),
            'question_ru' => trim($_POST['question_ru']),
            'question_uz' => trim($_POST['question_uz']),
            'answer_ru' => trim($_POST['answer_ru']),
            'answer_uz' => trim($_POST['answer_uz']),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($id) {
            $this->faqModel->update($id, $data);
            $_SESSION['success'] = 'FAQ updated successfully';
        } else {
            $this->faqModel->create($data);
            $_SESSION['success'] = 'FAQ created successfully';
        }
        
        $this->redirect('/admin/faqs');
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->faqModel->delete($id);
            $_SESSION['success'] = 'FAQ deleted successfully';
        }
        
        $this->redirect('/admin/faqs');
    }
    public function bulkUpload() {
        $this->requireAuth();
        
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
                
                // Prepare data
                $insertData = [
                    'page_slug' => trim($row['page_slug']),
                    'question_ru' => trim($row['question_ru']),
                    'question_uz' => trim($row['question_uz'] ?? ''),
                    'answer_ru' => trim($row['answer_ru']),
                    'answer_uz' => trim($row['answer_uz'] ?? ''),
                    'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
                    'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1
                ];
                
                if ($this->faqModel->create($insertData)) {
                    $created++;
                } else {
                    $errors[] = "Row " . ($index + 1) . ": Failed to create FAQ";
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