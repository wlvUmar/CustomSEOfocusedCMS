<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/ProductRequestImage.php';
require_once BASE_PATH . '/models/BotUser.php';
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/RequestAccessToken.php';

class BotController extends Controller {
    private function buildSignedBody($fields) {
        return http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
    }

    private function authorizeBotRequest($bodyForSig) {
        $secret = getenv('BOT_API_SECRET') ?: '';
        $timestamp = $_SERVER['HTTP_X_BOT_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_BOT_SIGNATURE'] ?? '';

        if (!$secret || !$timestamp || !$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $secret . ':' . $timestamp . ':' . $bodyForSig, $secret);
        error_log("BODY=[" . $bodyForSig . "]");
        error_log("TIMESTAMP=[" . $timestamp . "]");
        error_log("EXPECTED=[" . $expected . "]");
        error_log("RECEIVED=[" . $signature . "]");
        error_log("TIME_DIFF=[" . abs(time() - (int)$timestamp) . "]");
        return hash_equals($expected, $signature) && abs(time() - (int)$timestamp) <= 60;
    }

    private function requireBotAuth($bodyForSig) {
        if (!$this->authorizeBotRequest($bodyForSig)) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    // POST /api/bot/requests
    public function createRequest() {
        error_log("[BotController] createRequest() called");

        $telegram_id = $_POST['telegram_id'] ?? null;
        $description = $_POST['description'] ?? '';

        error_log("[BotController] telegram_id=$telegram_id, description_len=" . strlen($description));

        $body_for_sig = $this->buildSignedBody([
            'telegram_id' => $telegram_id,
            'description' => $description,
        ]);
        if (!$this->authorizeBotRequest($body_for_sig)) {
            error_log("[BotController] Authorization failed");
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        error_log("[BotController] Authorization passed");

        $files = $this->normalizeUploadedFiles($_FILES);
        if (empty($files)) {
            error_log("[BotController] No image files uploaded");
            $this->json(['success' => false, 'message' => 'No image uploaded'], 400);
            return;
        }

        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxPhotos = 4;
        if (count($files) > $maxPhotos) {
            error_log("[BotController] Too many images uploaded: " . count($files));
            $this->json(['success' => false, 'message' => 'Too many images uploaded'], 400);
            return;
        }

        // Ensure upload directory exists before writing the uploaded image.
        if (!is_dir(UPLOAD_PATH)) {
            error_log("[BotController] Creating upload directory: " . UPLOAD_PATH);
            if (!mkdir(UPLOAD_PATH, 0777, true)) {
                error_log("[BotController] Failed to create upload directory: " . UPLOAD_PATH);
                $this->json(['success' => false, 'message' => 'Failed to create upload directory'], 500);
                return;
            }
        }
        clearstatcache(true, UPLOAD_PATH);
        error_log("[BotController] upload dir exists=" . (is_dir(UPLOAD_PATH) ? 'YES' : 'NO') . ", writable=" . (is_writable(UPLOAD_PATH) ? 'YES' : 'NO'));

        $requestImageModel = new ProductRequestImage();
        $savedImages = [];

        foreach ($files as $index => $file) {
            error_log("[BotController] Received file #" . ($index + 1) . ": " . $file['name'] . ", type=" . $file['type'] . ", size=" . $file['size']);

            if (!in_array($file['type'], $allowed) || $file['size'] > MAX_UPLOAD_SIZE) {
                error_log("[BotController] Invalid image at index $index: type not allowed or too large");
                $this->json(['success' => false, 'message' => 'Invalid image'], 400);
                return;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('bot_') . '_' . time() . '_' . $index . '.' . $ext;
            $path = UPLOAD_PATH . $filename;

            error_log("[BotController] Moving file to: $path");

            if (!move_uploaded_file($file['tmp_name'], $path)) {
                error_log("[BotController] Failed to move file to: $path");
                $this->json(['success' => false, 'message' => 'Failed to save file'], 500);
                return;
            }

            $savedImages[] = UPLOAD_URL . $filename;
        }

        error_log("[BotController] File saved successfully");

        // Persist product request
        $prModel = new ProductRequest();
        $request_id = $prModel->create([
            'image_path' => $savedImages[0] ?? '',
            'description' => $description
        ]);

        error_log("[BotController] Created product request: id=$request_id");

        foreach (array_slice($savedImages, 1) as $sortOrder => $imagePath) {
            $requestImageModel->create($request_id, $imagePath, $sortOrder);
        }

        // Persist bot user and mapping
        $botUser = new BotUser();
        $botUser->upsert($telegram_id, '', '');

        $mapping = new BotRequestMapping();
        $mapping->create($request_id, $telegram_id);

        error_log("[BotController] Created bot user and mapping");

        $this->json(['success' => true, 'request_id' => (int)$request_id], 201);
    }

    private function normalizeUploadedFiles($files) {
        if (isset($files['images']) && isset($files['images']['name'])) {
            return $this->flattenFileArray($files['images']);
        }

        if (isset($files['image']) && isset($files['image']['name'])) {
            return $this->flattenFileArray($files['image']);
        }

        return [];
    }

    private function flattenFileArray($fileSpec) {
        if (!is_array($fileSpec['name'])) {
            return [$fileSpec];
        }

        $result = [];
        $count = count($fileSpec['name']);
        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'name' => $fileSpec['name'][$i],
                'type' => $fileSpec['type'][$i],
                'tmp_name' => $fileSpec['tmp_name'][$i],
                'error' => $fileSpec['error'][$i],
                'size' => $fileSpec['size'][$i],
            ];
        }
        return $result;
    }

    // POST /api/bot/users
    public function upsertUser() {
        $telegram_id = $_POST['telegram_id'] ?? null;
        $username = $_POST['username'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';

        $body_for_sig = $this->buildSignedBody([
            'telegram_id' => $telegram_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);
        $this->requireBotAuth($body_for_sig);

        if (!$telegram_id) {
            $this->json(['success' => false, 'message' => 'Missing telegram_id'], 400);
            return;
        }

        $botUser = new BotUser();
        $id = $botUser->upsert($telegram_id, $username, $first_name, $last_name);

        $this->json([
            'success' => true,
            'user_id' => (int)$id,
            'user' => $botUser->findByTelegramId($telegram_id),
        ], 201);
    }

    // GET /api/bot/users/{telegram_id}
    public function getUser($telegram_id) {
        $this->requireBotAuth('');

        $botUser = new BotUser();
        $user = $botUser->findByTelegramId($telegram_id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->json(['success' => true, 'user' => $user]);
    }

    // POST /api/bot/users/{telegram_id}/phone
    public function savePhone($telegram_id) {
        $phone = $_POST['phone'] ?? '';
        $body_for_sig = $this->buildSignedBody([
            'telegram_id' => $telegram_id,
            'phone' => $phone,
        ]);
        $this->requireBotAuth($body_for_sig);

        if (!$phone) {
            $this->json(['success' => false, 'message' => 'Missing phone'], 400);
            return;
        }

        $botUser = new BotUser();
        $botUser->savePhone($telegram_id, $phone);
        $this->json(['success' => true, 'phone' => $botUser->findPhoneByTelegramId($telegram_id)]);
    }

    // GET /api/bot/users/{telegram_id}/phone
    public function getPhone($telegram_id) {
        $this->requireBotAuth('');

        $botUser = new BotUser();
        $phone = $botUser->findPhoneByTelegramId($telegram_id);
        if ($phone === null) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->json(['success' => true, 'phone' => $phone]);
    }

    // POST /api/bot/mappings
    public function createMapping() {
        $request_id = $_POST['request_id'] ?? null;
        $telegram_id = $_POST['telegram_id'] ?? null;
        $notification_sent = isset($_POST['notification_sent']) ? (int)$_POST['notification_sent'] : 0;

        $body_for_sig = $this->buildSignedBody([
            'request_id' => $request_id,
            'telegram_id' => $telegram_id,
            'notification_sent' => $notification_sent,
        ]);
        $this->requireBotAuth($body_for_sig);

        if (!$request_id || !$telegram_id) {
            $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
            return;
        }

        $mapping = new BotRequestMapping();
        $id = $mapping->create($request_id, $telegram_id);

        $this->json([
            'success' => true,
            'mapping_id' => (int)$id,
            'mapping' => $mapping->findByRequestId($request_id),
        ], 201);
    }

    // GET /api/bot/mappings/{request_id}
    public function getMapping($request_id) {
        $this->requireBotAuth('');

        $mapping = new BotRequestMapping();
        $row = $mapping->findByRequestId($request_id);
        if (!$row) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->json(['success' => true, 'mapping' => $row]);
    }

    // POST /api/bot/mappings/{request_id}/notified
    public function markMappingNotified($request_id) {
        $body_for_sig = $this->buildSignedBody(['request_id' => $request_id]);
        $this->requireBotAuth($body_for_sig);

        $mapping = new BotRequestMapping();
        $mapping->markNotified($request_id);
        $this->json(['success' => true, 'mapping' => $mapping->findByRequestId($request_id)]);
    }

    // POST /api/bot/mappings/{request_id}/claim
    public function claimMappingNotification($request_id) {
        $body_for_sig = $this->buildSignedBody(['request_id' => $request_id]);
        $this->requireBotAuth($body_for_sig);

        $mapping = new BotRequestMapping();
        $row = $mapping->claimNotification($request_id);
        if (!$row) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'claimed' => (int)$row['notification_sent'] === 2,
            'mapping' => $row,
        ]);
    }

    // POST /api/bot/mappings/{request_id}/release
    public function releaseMappingNotification($request_id) {
        $body_for_sig = $this->buildSignedBody(['request_id' => $request_id]);
        $this->requireBotAuth($body_for_sig);

        $mapping = new BotRequestMapping();
        $row = $mapping->releaseNotification($request_id);
        if (!$row) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->json(['success' => true, 'mapping' => $row]);
    }

    // GET /api/bot/mappings?notification_sent=0
    public function listMappings() {
        $this->requireBotAuth('');

        $notification_sent = isset($_GET['notification_sent']) ? (int)$_GET['notification_sent'] : null;
        $mapping = new BotRequestMapping();

        if ($notification_sent === 0) {
            $this->json(['success' => true, 'mappings' => $mapping->findPending()]);
            return;
        }

        $this->json(['success' => false, 'message' => 'Unsupported query'], 400);
    }

    // GET /api/bot/requests/{id}
    public function getRequest($id) {
        error_log("[BotController] getRequest($id)");
        
        $prModel = new ProductRequest();
        $req = $prModel->getById($id);
        if (!$req) {
            error_log("[BotController] Request $id not found");
            $this->json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }
        
        error_log("[BotController] Request $id found: status=" . $req['status']);
        
        $this->json([
            'request_id' => (int)$req['id'],
            'status' => $req['status'],
            'price' => isset($req['price']) ? (string)$req['price'] : '',
            'admin_notes' => $req['reviewer_notes'] ?? ''
        ]);
    }

    // POST /api/bot/access-token
    public function createAccessToken() {
        error_log("[BotController] createAccessToken() called");

        $request_id = $_POST['request_id'] ?? null;
        $token = $_POST['token'] ?? null;

        error_log("[BotController] request_id=$request_id, token=" . substr($token ?? '', 0, 16) . "...");

        $body_for_sig = $this->buildSignedBody([
            'request_id' => $request_id,
            'token' => $token,
        ]);
        if (!$this->authorizeBotRequest($body_for_sig)) {
            error_log("[BotController] Authorization failed for access token");
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        if (!$request_id || !$token) {
            error_log("[BotController] Missing request_id or token");
            $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
            return;
        }

        try {
            $tokenModel = new RequestAccessToken();
            $tokenModel->create($request_id, $token);
            
            error_log("[BotController] Access token created for request $request_id");
            $this->json(['success' => true, 'request_id' => (int)$request_id, 'token' => $token], 201);
        } catch (Exception $e) {
            error_log("[BotController] Failed to create access token: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Database error'], 500);
        }
    }
}
