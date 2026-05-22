<?php
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/TelegramNotifier.php';

class NotificationService {
    private $mappingModel;
    private $requestModel;
    private $notifier;

    public function __construct() {
        $this->mappingModel = new BotRequestMapping();
        $this->requestModel = new ProductRequest();
        $this->notifier = new TelegramNotifier();
    }

    public function notifyReviewResult($request_id, $contact_phone = '') {
        $mapping = $this->mappingModel->findByRequestId($request_id);
        if (!$mapping) return false;

        $request = $this->requestModel->getById($request_id);
        if (!$request) return false;

        $payload = [
            'request_id' => (int)$request_id,
            'status' => $request['status'],
            'price' => isset($request['price']) ? (string)$request['price'] : '',
            'notes' => $request['reviewer_notes'] ?? '',
            'contact_phone' => $contact_phone
        ];

        $sent = $this->notifier->sendCallback($payload);
        if ($sent) {
            $this->mappingModel->markNotified($request_id);
        }
        return $sent;
    }
}
