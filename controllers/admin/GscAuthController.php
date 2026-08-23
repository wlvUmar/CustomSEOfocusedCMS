<?php
// path: ./controllers/admin/GscAuthController.php
// OAuth2 for Google Search Console (webmasters.readonly). Single connected
// account (id=1) — enough for this property. State is bound to session + CSRF.

require_once BASE_PATH . '/models/GscClient.php';

class GscAuthController extends Controller {

    public function status() {
        $this->requireAuth();
        $this->json(GscClient::getStatus());
    }

    public function authorize() {
        $this->requireAuth();
        if (!GscClient::isConfigured()) {
            $this->json(['success' => false, 'message' => 'GSC_CLIENT_ID / GSC_CLIENT_SECRET not configured in .env'], 400);
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['gsc_oauth_state'] = $state;
        $url = GscClient::getAuthUrl($state);
        // If called via XHR, return JSON; otherwise redirect.
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($accept, 'application/json')) {
            $this->json(['success' => true, 'auth_url' => $url]);
        }
        header('Location: ' . $url);
        exit;
    }

    public function callback() {
        $this->requireAuth();
        $code = trim((string)($_GET['code'] ?? ''));
        $state = (string)($_GET['state'] ?? '');
        $error = (string)($_GET['error'] ?? '');

        if ($error !== '') {
            $_SESSION['error'] = 'GSC OAuth: ' . $error . ' — ' . ($_GET['error_description'] ?? '');
            $this->redirect('/admin/ai-studio');
        }
        if ($code === '') {
            $_SESSION['error'] = 'GSC OAuth: missing code';
            $this->redirect('/admin/ai-studio');
        }
        $expected = (string)($_SESSION['gsc_oauth_state'] ?? '');
        if ($expected === '' || !hash_equals($expected, $state)) {
            $_SESSION['error'] = 'GSC OAuth: state mismatch (try again)';
            $this->redirect('/admin/ai-studio');
        }
        unset($_SESSION['gsc_oauth_state']);

        $data = GscClient::exchangeCode($code);
        if ($data === null || empty($data['refresh_token'])) {
            // refresh_token is only returned on first consent; if missing, reuse stored one.
            $existing = GscClient::getRefreshToken();
            if ($existing !== null && !empty($data['access_token'])) {
                // Already connected previously — just refresh.
                $_SESSION['success'] = 'GSC already connected — access token refreshed.';
                $this->redirect('/admin/ai-studio');
            }
            $_SESSION['error'] = 'GSC OAuth: no refresh_token returned. Revoke access at https://myaccount.google.com/permissions and try again (needs prompt=consent).';
            $this->redirect('/admin/ai-studio');
        }

        GscClient::saveRefreshToken((string)$data['refresh_token'], GscClient::getSiteUrl(), null);
        // Store access_token as well for immediate use.
        if (!empty($data['access_token'])) {
            // Reuse save path via direct DB write (encrypted).
            $db = Database::getInstance();
            // Use internal encrypt via saveRefreshToken already did; now set access_token.
            // Bypass encrypt wrapper: call via reflection-like direct query with encryption.
            // Simplest: let getAccessToken refresh on next call; or store now.
            $tmp = (string)$data['access_token'];
            // Encrypt using same method as GscClient (call via public helper indirectly: save then overwrite).
            // We don't have public encrypt, so store plaintext if no key, else re-encrypt via save path hack:
            // Trigger a second save that sets access_token through private method → just let refresh do it.
            // Instead, directly use the same logic:
            $enc = $tmp;
            $key = (string)(getenv('GSC_ENCRYPTION_KEY') ?: (getenv('BOT_API_SECRET') ?: ''));
            if ($key !== '' && function_exists('openssl_encrypt')) {
                $k = hash('sha256', $key, true);
                $iv = random_bytes(12);
                $ct = openssl_encrypt($tmp, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
                if ($ct !== false) $enc = 'gcm$' . base64_encode($iv . $tag . $ct);
            }
            $expiresIn = (int)($data['expires_in'] ?? 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + max(60, $expiresIn - 30));
            $db->query("UPDATE gsc_tokens SET access_token = ?, expires_at = ? WHERE id = 1", [$enc, $expiresAt]);
        }

        GscClient::clearCache();
        $_SESSION['success'] = 'GSC connected — live Search Console data is now available in AI Studio.';
        $this->redirect('/admin/ai-studio');
    }

    public function disconnect() {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF failed'], 400);
        }
        GscClient::disconnect();
        $this->json(['success' => true]);
    }
}
