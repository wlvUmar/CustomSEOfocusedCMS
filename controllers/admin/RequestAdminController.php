<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/ProductRequestImage.php';
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/NotificationService.php';

class RequestAdminController extends Controller {
    private $prModel;
    private $imageModel;
    private $mappingModel;
    private $notifier;

    public function __construct() {
        parent::__construct();
        $this->prModel = new ProductRequest();
        $this->imageModel = new ProductRequestImage();
        $this->mappingModel = new BotRequestMapping();
        $this->notifier = new NotificationService();
    }

    public function index() {
        $this->requireAuth();
        $requests = $this->prModel->getPending(200);
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
        $this->requireAuth();
        $req = $this->prModel->getById($id);
        if (!$req) {
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
        $this->requireAuth();
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
        }
        $id = $_POST['id'] ?? 0;
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
        $this->requireAuth();
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF'], 403);
        }
        $id = $_POST['id'] ?? 0;
        $notes = $_POST['notes'] ?? '';
        $this->prModel->updateStatus($id, 'rejected', null, $notes, $_SESSION['user_id'] ?? null);
        $this->notifier->notifyReviewResult($id, '');
        $_SESSION['success'] = 'Запрос обработан, клиенту отправлено сообщение';
        $this->redirect('/admin/requests');
    }
}
