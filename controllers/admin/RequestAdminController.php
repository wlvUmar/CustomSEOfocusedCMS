<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/ProductRequestImage.php';
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/NotificationService.php';
require_once BASE_PATH . '/models/RequestAccessToken.php';

class RequestAdminController extends Controller {
    private $prModel;
    private $imageModel;
    private $mappingModel;
    private $notifier;
    private $tokenModel;

    public function __construct() {
        parent::__construct();
        $this->prModel = new ProductRequest();
        $this->imageModel = new ProductRequestImage();
        $this->mappingModel = new BotRequestMapping();
        $this->notifier = new NotificationService();
        $this->tokenModel = new RequestAccessToken();
    }

    public function index() {
        $this->requireAuth();
        $requests = $this->prModel->getAll(200);
        foreach ($requests as &$request) {
            $request['photo_count'] = $this->imageModel->countByRequestId($request['id']);
            if (!empty($request['image_path'])) {
                $request['photo_count'] += 1;
            }
        }
        unset($request);
        $this->view('admin/requests/index', ['requests' => $requests, 'pageName' => 'requests/index']);
    }

    public function show($id) {
        // Check authentication: either logged in OR has valid token
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_GET['token'] ?? null;
        $hasValidToken = false;
        
        error_log("[RequestAdminController] Accessing request $id, isLoggedIn=$isLoggedIn, hasToken=" . ($token ? 'yes' : 'no'));
        
        if (!$isLoggedIn && $token) {
            // Validate token
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
            error_log("[RequestAdminController] Token validation: validatedRequestId=$validatedRequestId, id=$id, hasValidToken=" . ($hasValidToken ? 'true' : 'false'));
        }
        
        // Require either logged in OR valid token
        if (!$isLoggedIn && !$hasValidToken) {
            error_log("[RequestAdminController] Auth failed, redirecting to login");
            $this->requireAuth();
        }
        
        $req = $this->prModel->getById($id);
        if (!$req) {
            // If accessed via token link but request doesn't exist yet, show waiting message
            if ($hasValidToken) {
                $_SESSION['info'] = 'Request is being processed. Please wait...';
                $this->view('admin/requests/waiting');
                return;
            }
            
            $_SESSION['error'] = 'Request not found';
            $this->redirect('/admin/requests');
        }
        $images = $this->imageModel->getByRequestId($id);
        if (!empty($req['image_path'])) {
            array_unshift($images, [
                'image_path' => $req['image_path'],
                'sort_order' => -1
            ]);
        }
        $req['photo_count'] = count($images);
        $this->view('admin/requests/show', [
            'request' => $req,
            'images' => $images,
            'pageName' => 'requests/show'
        ]);
    }

    public function approve() {
        // Check authentication: either logged in OR has valid token
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        $hasValidToken = false;
        $id = $_POST['id'] ?? 0;
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
        }
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
        }
        $price = $_POST['price'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $contactPhone = $_POST['contact_phone'] ?? '';
        $this->prModel->updateStatus($id, 'approved', $price, $notes, $_SESSION['user_id'] ?? null);
        // Trigger notification
        $this->notifier->notifyReviewResult($id, $contactPhone);
        $_SESSION['success'] = 'Запрос обработан, клиенту отправлено сообщение';
        $this->redirect('/admin/requests');
    }

    public function reject() {
        // Check authentication: either logged in OR has valid token
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        $hasValidToken = false;
        $id = $_POST['id'] ?? 0;
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
        }
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
        }
        $notes = $_POST['notes'] ?? '';
        $this->prModel->updateStatus($id, 'rejected', null, $notes, $_SESSION['user_id'] ?? null);
        $this->notifier->notifyReviewResult($id, '');
        $_SESSION['success'] = 'Запрос обработан, клиенту отправлено сообщение';
        $this->redirect('/admin/requests');
    }
    public function delete(): void
    {
        // Check authentication: either logged in OR has valid token
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        $hasValidToken = false;
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {  // ← match how approve/reject validate CSRF
            $this->redirect('/admin/requests');
        }

        if (!$id) {
            $this->redirect('/admin/requests');
        }

        // 1. Get the main image from the request row itself
        $req = $this->prModel->getById($id);
        if ($req && !empty($req['image_path'])) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($req['image_path'], '/');
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // 2. Get and delete additional images via your existing model
        $images = $this->imageModel->getByRequestId($id);
        foreach ($images as $img) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($img['image_path'], '/');
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // 3. Delete DB records using your models, not $this->db directly
        $this->imageModel->deleteByRequestId($id);  // add this method if it doesn't exist
        $this->prModel->deleteById($id);            // add this method if it doesn't exist

        $_SESSION['success'] = 'Заявка удалена.';  // ← match session flash style used in approve/reject
        $this->redirect('/admin/requests');
    }
}
