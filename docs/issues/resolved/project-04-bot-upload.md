# Project 04 — Security: Bot API & File Upload

> Whole-project audit — no fixes.

## 1. Bot HMAC uses secret as both data and key + encoding mismatch
- **Location:** `controllers/BotController.php:23` `hash_hmac('sha256', $secret.':'.$timestamp.':'.$bodyForSig, $secret)` — `bodyForSig` built via `http_build_query` RFC1738 (`+` for spaces) but `$_POST` already decoded → signature mismatch, auth bypass via `+`/`%` ambiguity.

## 2. No nonce — 60s replay window
- **Location:** `controllers/BotController.php:24` `abs(time()-timestamp)<=60` without storing used timestamps → replay any request within window.

## 3. Image validation uses client MIME
- **Location:** `controllers/BotController.php:87,93` `$file['type']` (client-supplied) not `finfo_file`; `evil.php` with `image/jpeg` type passes.

## 4. Extension not validated, 0777 upload dir
- **Location:** `controllers/BotController.php:93-98` `pathinfo($file['name'])` unchecked, `uniqid('bot_')` still ends `.php`; `mkdir(UPLOAD_PATH,0777,true)` world-writable.

## 5. `getRequest($id)` has no auth
- **Location:** `controllers/BotController.php:346` — logs but returns product request JSON unauthenticated → IDOR enumeration.

## 6. Bot token client-supplied not generated
- **Location:** `controllers/BotController.php:366-401` + `models/RequestAccessToken.php:14-24` — attacker controls `token` value for arbitrary `request_id`, then accesses `RequestAdminController::show?token=`.

## 7. Token fixation — `ON DUPLICATE KEY UPDATE` doesn't update token
- **Location:** `models/RequestAccessToken.php:21` — old token remains valid forever while caller thinks new overrides.

## 8. Media delete path traversal
- **Location:** `models/Media.php:29-39` `UPLOAD_PATH . $media['filename']` without `realpath` check — `../../config.php` unlink outside upload dir.

## 9. Article image upload no validation
- **Location:** `controllers/admin/ArticleAdminController.php:119-130` — `basename($_FILES['image_upload']['name'])` with `time().'_'` no MIME/`validateUpload`, no `is_uploaded_file` — arbitrary file write.

## 10. Request upload path uses DOCUMENT_ROOT unsanitized
- **Location:** `controllers/admin/RequestAdminController.php:246-258` `$_SERVER['DOCUMENT_ROOT']` + `parse_url(...,PATH)` not stripping `..`.
