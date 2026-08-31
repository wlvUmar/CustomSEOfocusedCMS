<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/ProductRequest.php';
require_once BASE_PATH . '/models/ProductRequestImage.php';
require_once BASE_PATH . '/models/BotUser.php';
require_once BASE_PATH . '/models/BotRequestMapping.php';
require_once BASE_PATH . '/models/RequestAccessToken.php';

class BotController extends Controller {
    private function buildSignedBody($fields) {
        return http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }

    private function authorizeBotRequest($bodyForSig) {
        $secret = getenv('BOT_API_SECRET') ?: '';
        $timestamp = $_SERVER['HTTP_X_BOT_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_BOT_SIGNATURE'] ?? '';

        if (!$secret || !$timestamp || !$signature) {
            return false;
        }

        if (abs(time() - (int)$timestamp) > 60) {
            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        $nonceKey = hash('sha256', $timestamp . ':' . $signature);
        if ($this->isNonceUsed($nonceKey)) {
            return false;
        }

        // Canonical data is timestamp:body (secret is key only). Dual-accept RFC3986 primary + RFC1738 fallback during rollout.
        $expected3986 = hash_hmac('sha256', $timestamp . ':' . $bodyForSig, $secret);
        $ok = hash_equals($expected3986, $signature);
        if (!$ok) {
            $legacyBody = http_build_query($this->parseBodyFields($bodyForSig), '', '&', PHP_QUERY_RFC1738);
            $expected1738 = hash_hmac('sha256', $timestamp . ':' . $legacyBody, $secret);
            // also accept legacy format secret:ts:body for one release window
            $expectedLegacySecret = hash_hmac('sha256', $secret . ':' . $timestamp . ':' . $legacyBody, $secret);
            if (hash_equals($expected1738, $signature) || hash_equals($expectedLegacySecret, $signature)) {
                error_log('[BotController] legacy RFC1738 signature accepted - bot should upgrade to RFC3986');
                $ok = true;
            }
        }

        if ($ok) {
            $this->markNonce($nonceKey);
        }

        return $ok;
    }

    private function parseBodyFields($bodyForSig) {
        $out = [];
        parse_str($bodyForSig, $out);
        return $out;
    }

    private function isNonceUsed($nonceKey) {
        $dir = BASE_PATH . '/storage/bot_nonces';
        $file = $dir . '/' . $nonceKey . '.json';
        if (!is_file($file)) {
            return false;
        }
        $data = @json_decode(@file_get_contents($file), true);
        if (!$data || !isset($data['expires'])) {
            return false;
        }
        if (time() > (int)$data['expires']) {
            @unlink($file);
            return false;
        }
        return true;
    }

    private function markNonce($nonceKey) {
        $dir = BASE_PATH . '/storage/bot_nonces';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . $nonceKey . '.json';
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $payload = json_encode(['expires' => time() + 120]);
        $fh = @fopen($tmp, 'wb');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $payload);
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            @rename($tmp, $file);
        } else {
            @file_put_contents($file, $payload, LOCK_EX);
        }
        // lazy prune expired nonces (1% chance to avoid per-request scan)
        if (mt_rand(1, 100) === 1) {
            foreach (@glob($dir . '/*.json') ?: [] as $f) {
                $d = @json_decode(@file_get_contents($f), true);
                if ($d && isset($d['expires']) && time() > (int)$d['expires']) {
                    @unlink($f);
                }
            }
        }
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
            if (!mkdir(UPLOAD_PATH, 0755, true)) {
                error_log("[BotController] Failed to create upload directory: " . UPLOAD_PATH);
                $this->json(['success' => false, 'message' => 'Failed to create upload directory'], 500);
                return;
            }
        }
        clearstatcache(true, UPLOAD_PATH);
        error_log("[BotController] upload dir exists=" . (is_dir(UPLOAD_PATH) ? 'YES' : 'NO') . ", writable=" . (is_writable(UPLOAD_PATH) ? 'YES' : 'NO'));

        $requestImageModel = new ProductRequestImage();
        $savedImages = [];
        $allowedExts = ['jpg','jpeg','png','webp','gif'];

        foreach ($files as $index => $file) {
            error_log("[BotController] Received file #" . ($index + 1) . ": " . $file['name'] . ", type=" . $file['type'] . ", size=" . $file['size']);

            if (!isset($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                error_log("[BotController] Upload error at index $index: " . ($file['error'] ?? 'missing'));
                $this->json(['success' => false, 'message' => 'Invalid image upload'], 400);
                return;
            }

            if (!is_uploaded_file($file['tmp_name'])) {
                error_log("[BotController] Not an uploaded file at index $index");
                $this->json(['success' => false, 'message' => 'Invalid image upload'], 400);
                return;
            }

            if ($file['size'] > MAX_UPLOAD_SIZE) {
                error_log("[BotController] File too large at index $index");
                $this->json(['success' => false, 'message' => 'File too large'], 400);
                return;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === '') {
                error_log("[BotController] Missing extension at index $index");
                $this->json(['success' => false, 'message' => 'Invalid image type'], 400);
                return;
            }
            if (!in_array($ext, $allowedExts, true)) {
                error_log("[BotController] Extension not allowed at index $index: $ext");
                $this->json(['success' => false, 'message' => 'Invalid image type'], 400);
                return;
            }

            if (!function_exists('finfo_open')) {
                error_log("[BotController] finfo missing, rejecting upload");
                $this->json(['success' => false, 'message' => 'Server upload validation unavailable'], 500);
                return;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
            if ($finfo) finfo_close($finfo);
            // normalize jpg
            $normalizedMime = $realMime === 'image/jpg' ? 'image/jpeg' : $realMime;
            if (!in_array($normalizedMime, $allowed, true)) {
                error_log("[BotController] MIME mismatch at index $index: real=$realMime ext=$ext");
                $this->json(['success' => false, 'message' => 'Invalid image type'], 400);
                return;
            }
            // double-extension guard: ensure mime matches ext family
            $extToMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
            if (isset($extToMime[$ext]) && $extToMime[$ext] !== $normalizedMime) {
                // allow jpg/jpeg interchange
                if (!($ext==='jpg' && $normalizedMime==='image/jpeg') && !($ext==='jpeg' && $normalizedMime==='image/jpeg')) {
                    error_log("[BotController] Ext vs MIME mismatch at index $index: $ext vs $normalizedMime");
                    $this->json(['success' => false, 'message' => 'Invalid image type'], 400);
                    return;
                }
            }

            $filename = 'bot_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $path = UPLOAD_PATH . $filename;

            error_log("[BotController] Moving file to: $path");

            if (!move_uploaded_file($file['tmp_name'], $path)) {
                error_log("[BotController] Failed to move file to: $path");
                $this->json(['success' => false, 'message' => 'Failed to save file'], 500);
                return;
            }
            @chmod($path, 0644);

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

    // GET /api/bot/requests/{id} - now requires bot auth (IDOR fix)
    public function getRequest($id) {
        $bodyForAuth = $this->buildSignedBody(['id' => $id]);
        // empty body fallback for legacy callers during rollout - still requires valid timestamp/signature over empty string
        if (!$this->authorizeBotRequest($bodyForAuth) && !$this->authorizeBotRequest('')) {
            error_log("[BotController] getRequest auth failed for $id");
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        error_log("[BotController] getRequest($id) auth passed");
        
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

    // POST /api/bot/access-token - server generates token (client value ignored)
    public function createAccessToken() {
        error_log("[BotController] createAccessToken() called");

        $request_id = $_POST['request_id'] ?? null;
        $clientToken = $_POST['token'] ?? null;

        error_log("[BotController] request_id=$request_id, clientTokenPresent=" . ($clientToken ? 'yes' : 'no'));

        // Prefer signing with request_id alone (new bots); fallback to legacy request_id+token for rollout
        $bodyPrimary = $this->buildSignedBody(['request_id' => $request_id]);
        $authorized = $this->authorizeBotRequest($bodyPrimary);
        if (!$authorized && $clientToken !== null) {
            $bodyLegacy = $this->buildSignedBody(['request_id' => $request_id, 'token' => $clientToken]);
            $authorized = $this->authorizeBotRequest($bodyLegacy);
            if ($authorized) {
                error_log('[BotController] access-token legacy token-in-body signature accepted');
            }
        }

        if (!$authorized) {
            error_log("[BotController] Authorization failed for access token");
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        if (!$request_id) {
            error_log("[BotController] Missing request_id");
            $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
            return;
        }

        try {
            $tokenModel = new RequestAccessToken();
            $generated = $tokenModel->create($request_id);
            
            error_log("[BotController] Access token generated for request $request_id");
            $this->json(['success' => true, 'request_id' => (int)$request_id, 'token' => $generated], 201);
        } catch (Exception $e) {
            error_log("[BotController] Failed to create access token: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Database error'], 500);
        }
    }
}
