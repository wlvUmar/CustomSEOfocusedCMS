<?php
class Controller {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    protected function view($file, $data = []) {
        // Auto-detect pageName if not explicitly passed
        if (!isset($data['pageName'])) {
            $data['pageName'] = str_replace('admin/', '', $file);
        }

        foreach ($data as $key => $value) {
            $$key = $value;
        }
        require BASE_PATH . '/views/' . $file . '.php';
    }

    protected function redirect($url) {
        header("Location: " . BASE_URL . $url);
        exit;
    }

    protected function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/admin/login');
        }
    }
}
