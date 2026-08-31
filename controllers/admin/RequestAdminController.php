<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/ProductRequestImage.php';
require_once BASE_PATH . '/models/BotUser.php';
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/NotificationService.php';
require_once BASE_PATH . '/models/RequestAccessToken.php';

class RequestAdminController extends Controller {
    private $prModel;
    private $imageModel;
    private $userModel;
    private $mappingModel;
    private $notifier;
    private $tokenModel;

    public function __construct() {
        parent::__construct();
        $this->prModel = new ProductRequest();
        $this->imageModel = new ProductRequestImage();
        $this->userModel = new BotUser();
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
        $mapping = $this->mappingModel->findByRequestId($id);
        $phone = null;
        if ($mapping && !empty($mapping['telegram_id'])) {
            $phone = $this->userModel->findPhoneByTelegramId($mapping['telegram_id']);
        }
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
            'phone' => $phone,
            'token' => $token,  
            'pageName' => 'requests/show'
        ]);
    }

    public function approve() {
        // Auth: logged-in requires CSRF; token-only requires valid POST token (no CSRF)
        $isLoggedIn = isset($_SESSION['user_id']);
        // Prefer POST token to avoid GET logging in proxy/Referer; fallback to GET for BC links
        $token = $_POST['token'] ?? $_GET['token'] ?? null;
        $hasValidToken = false;
        $id = $_POST['id'] ?? 0;
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
            return;
        }
        
        // Logged-in path must always pass CSRF even when token is also present (no bypass)
        if ($isLoggedIn && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
            return;
        }

        $priceRaw = $_POST['price'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $contactPhone = $_POST['contact_phone'] ?? '';

        // Sanitize price: allow digits and remove spaces, commas, dots
        $priceClean = preg_replace('/[^0-9]/', '', (string)$priceRaw);
        if ($priceClean === '') {
            // Price is required for approval — do not proceed
            $_SESSION['error'] = 'Цена обязательна при отправке оценки. Укажите сумму в поле "Цена".';
            $redirectUrl = '/admin/requests/' . $id;
            if (!empty($token)) {
                $redirectUrl .= '?token=' . urlencode($token);
            }
            $this->redirect($redirectUrl);
            return;
        }

        // Persist status with sanitized price
        $this->prModel->updateStatus($id, 'approved', $priceClean, $notes, $_SESSION['user_id'] ?? null);
        // Trigger notification
        $success = $this->notifier->notifyReviewResult($id, $contactPhone);

        if ($hasValidToken && !$isLoggedIn) {
            if ($success) {
                $_SESSION['success'] = 'Запрос обработан, клиенту отправлено сообщение';
            } else {
                $_SESSION['error'] = 'Статус обновлён, но уведомление не отправлено';
            }
            $this->redirect('/admin/requests/' . $id . '?token=' . urlencode($token));
        } else {
            $_SESSION['success'] = $success
                ? 'Запрос обработан, клиенту отправлено сообщение'
                : 'Статус обновлён, но уведомление не отправлено';
            $this->redirect('/admin/requests');
        }
    }

    public function reject() {
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_POST['token'] ?? $_GET['token'] ?? null;
        $hasValidToken = false;
        $id = $_POST['id'] ?? 0;
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
            return;
        }
        
        if ($isLoggedIn && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
            return;
        }
        $notes = $_POST['notes'] ?? '';
        $this->prModel->updateStatus($id, 'rejected', null, $notes, $_SESSION['user_id'] ?? null);
        $success = $this->notifier->notifyReviewResult($id, '');

        if ($hasValidToken && !$isLoggedIn) {
            if ($success) {
                $_SESSION['success'] = 'Запрос обработан, клиенту отправлено сообщение';
            } else {
                $_SESSION['error'] = 'Статус обновлён, но уведомление не отправлено';
            }
            $this->redirect('/admin/requests/' . $id . '?token=' . urlencode($token));
        } else {
            $_SESSION['success'] = $success
                ? 'Запрос обработан, клиенту отправлено сообщение'
                : 'Статус обновлён, но уведомление не отправлено';
            $this->redirect('/admin/requests');
        }
    }
    
    public function delete(): void
    {
        $isLoggedIn = isset($_SESSION['user_id']);
        $token = $_POST['token'] ?? $_GET['token'] ?? null;
        $hasValidToken = false;
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$isLoggedIn && $token) {
            $validatedRequestId = $this->tokenModel->validateToken($token);
            $hasValidToken = ($validatedRequestId === (int)$id);
        }
        
        if (!$isLoggedIn && !$hasValidToken) {
            $this->requireAuth();
            return;
        }

        // Logged-in must pass CSRF; token-only skips CSRF (no session) but token already validated
        if ($isLoggedIn && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/requests');
            return;
        }

        if (!$id) {
            $this->redirect('/admin/requests');
        }

        $req = $this->prModel->getById($id);
        if ($req && !empty($req['image_path'])) {
            $path = $this->resolveUploadFilesystemPath($req['image_path']);
            error_log('[Delete] Main image path: ' . $path . ' exists=' . (file_exists($path) ? 'yes' : 'no'));
            if (file_exists($path)) {
                $deleted = unlink($path);
                error_log('[Delete] unlink result: ' . ($deleted ? 'ok' : 'FAILED'));
            }
        }

        $images = $this->imageModel->getByRequestId($id);
        error_log('[Delete] Additional images count: ' . count($images));
        foreach ($images as $img) {
            $path = $this->resolveUploadFilesystemPath($img['image_path']);
            error_log('[Delete] Additional image path: ' . $path . ' exists=' . (file_exists($path) ? 'yes' : 'no'));
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

    private function resolveUploadFilesystemPath($imagePath) {
        if (!$imagePath) {
            return '';
        }

        $path = parse_url($imagePath, PHP_URL_PATH);
        if (!$path) {
            $path = $imagePath;
        }
        // Strip traversal sequences before joining
        $path = ltrim($path, '/\\');
        // Handle absolute URLs stored as /uploads/file or uploads/file; extract filename if needed
        $baseName = basename($path);
        // If path contains uploads/, prefer the segment after uploads/
        if (strpos($path, 'uploads/') !== false) {
            $path = substr($path, strpos($path, 'uploads/') + strlen('uploads/'));
            $path = ltrim($path, '/\\');
        } else {
            // fallback to basename to avoid directory traversal via crafted path
            $path = $baseName;
        }
        if (strpos($path, '..') !== false || strpos($path, '/') !== false || strpos($path, '\\') !== false) {
            return '';
        }

        $candidate = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . $path;
        $realBase = realpath(UPLOAD_PATH) ?: rtrim(UPLOAD_PATH, '/\\');
        $realCandidate = realpath($candidate);
        if ($realCandidate !== false) {
            if (strpos($realCandidate, $realBase) !== 0) {
                return '';
            }
            return $realCandidate;
        }
        // file already deleted - return candidate if inside base
        if (strpos($candidate, $realBase) !== 0) {
            return '';
        }
        return $candidate;
    }
}
