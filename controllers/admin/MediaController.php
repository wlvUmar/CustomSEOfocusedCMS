<?php
require_once BASE_PATH . '/models/Media.php';

class MediaController extends Controller {
    private $mediaModel;

    public function __construct() {
        parent::__construct();
        $this->mediaModel = new Media();
    }

    public function index() {
        $this->requireAuth();
        $media = $this->mediaModel->getAll();
        $this->view('admin/media/manager', ['media' => $media]);
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
            
            $id = $this->mediaModel->create($data);
            
            $this->json([
                'success' => true,
                'id' => $id,
                'filename' => $filename,
                'url' => UPLOAD_URL . $filename
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Upload failed'], 500);
        }
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        if ($id && $this->mediaModel->delete($id)) {
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Delete failed'], 400);
        }
    }
}
