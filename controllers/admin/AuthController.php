<?php
require_once BASE_PATH . '/models/User.php';

class AuthController extends Controller {
    private $userModel;

    public function __construct() {
        parent::__construct();
        $this->userModel = new User();
    }

    public function showLogin() {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/admin/dashboard');
        }
        $this->view('admin/login');
    }

    public function login() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = $this->userModel->authenticate($username, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $this->redirect('/admin/dashboard');
        } else {
            $_SESSION['error'] = 'Invalid credentials';
            $this->redirect('/admin/login');
        }
    }

    public function logout() {
        session_destroy();
        $this->redirect('/admin/login');
    }
}