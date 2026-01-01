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
    public function bulkUpload() {
        $this->requireAuth();
        
        if (!isset($_FILES['files'])) {
            $this->json(['success' => false, 'message' => 'No files uploaded'], 400);
        }
        
        $files = $_FILES['files'];
        $uploaded = 0;
        $errors = [];
        
        // Allowed types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Create upload directory if not exists
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
        
        // Handle multiple files
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $files['name'][$i];
            $fileTmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            $fileError = $files['error'][$i];
            
            // Skip if upload error
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "$fileName: Upload error";
                continue;
            }
            
            // Validate file type
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "$fileName: Invalid file type";
                continue;
            }
            
            // Validate file size
            if ($fileSize > MAX_UPLOAD_SIZE) {
                $errors[] = "$fileName: File too large";
                continue;
            }
            
            // Generate unique filename
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '_' . $i . '.' . $ext;
            $filepath = UPLOAD_PATH . $newFileName;
            
            // Move file
            if (move_uploaded_file($fileTmpName, $filepath)) {
                $data = [
                    'filename' => $newFileName,
                    'original_name' => $fileName,
                    'file_size' => $fileSize,
                    'mime_type' => $fileType
                ];
                
                if ($this->mediaModel->create($data)) {
                    $uploaded++;
                } else {
                    $errors[] = "$fileName: Database insert failed";
                }
            } else {
                $errors[] = "$fileName: Failed to move file";
            }
        }
        
        $message = "Uploaded $uploaded file(s)";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 5));
        }
        
        $_SESSION['success'] = $message;
        header('Location: ' . BASE_URL . '/admin/media');
        exit;
    }
}
