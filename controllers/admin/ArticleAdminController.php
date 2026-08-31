<?php
// path: ./controllers/admin/ArticleAdminController.php

require_once BASE_PATH . '/models/Article.php';
require_once BASE_PATH . '/models/ArticleJsonLdGenerator.php';
require_once BASE_PATH . '/models/SEO.php';

class ArticleAdminController extends Controller {
    private $articleModel;
    private $seoModel;

    public function __construct() {
        parent::__construct();
        $this->articleModel = new Article();
        $this->seoModel = new SEO();
    }

    /**
     * List all articles with search and filter
     */
    public function index() {
        $this->requireAuth();
        
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // Get articles
        $publishedOnly = ($status === 'published');
        $articles = $this->articleModel->getAll($publishedOnly, $search, $category);
        
        // Filter by status if needed
        if ($status === 'draft') {
            $articles = array_filter($articles, function($article) {
                return !$article['is_published'];
            });
        }
        
        // Get all categories for filter
        $categories = $this->articleModel->getCategories('ru');
        
        $data = [
            'articles' => $articles,
            'categories' => $categories,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'pageName' => 'articles/list'
        ];
        
        $this->view('admin/articles/list', $data);
    }

    /**
     * Create/Edit article form
     */
    public function edit($id = null) {
        $this->requireAuth();
        
        $article = null;
        if ($id) {
            $article = $this->articleModel->getById($id);
            if (!$article) {
                $_SESSION['error'] = 'Article not found';
                header('Location: ' . BASE_URL . '/admin/articles');
                exit;
            }
        }
        
        // Get categories for dropdown
        $categories = $this->articleModel->getCategories('ru');
        
        // Get pages for related page dropdown
        require_once BASE_PATH . '/models/Page.php';
        $pageModel = new Page();
        $pages = $pageModel->getAll();
        
        $data = [
            'article' => $article,
            'categories' => $categories,
            'pages' => $pages,
            'pageName' => 'articles/edit'
        ];
        
        $this->view('admin/articles/edit', $data);
    }

    /**
     * Save article (create or update)
     */
    public function save() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/articles');
            exit;
        }

        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            header('Location: ' . BASE_URL . '/admin/articles');
            exit;
        }
        
        $id = $_POST['id'] ?? null;
        
        // Prepare data
        $data = [
            'slug' => $_POST['slug'] ?? '',
            'title_ru' => $_POST['title_ru'] ?? '',
            'title_uz' => $_POST['title_uz'] ?? '',
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'excerpt_ru' => $_POST['excerpt_ru'] ?? '',
            'excerpt_uz' => $_POST['excerpt_uz'] ?? '',
            'category_ru' => $_POST['category_ru'] ?? '',
            'category_uz' => $_POST['category_uz'] ?? '',
            'author' => $_POST['author'] ?? 'Admin',
            'image' => $_POST['image'] ?? null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'related_page_id' => !empty($_POST['related_page_id']) ? $_POST['related_page_id'] : null
        ];
        
        // Handle image upload if provided - validated (issue 9 fix)
        if (!empty($_FILES['image_upload']['name'])) {
            $file = $_FILES['image_upload'];
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                $_SESSION['error'] = 'Invalid image upload';
                header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
                exit;
            }
            if ($file['size'] > MAX_UPLOAD_SIZE) {
                $_SESSION['error'] = 'File too large';
                header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
                exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowedExts, true)) {
                $_SESSION['error'] = 'Invalid image type';
                header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
                exit;
            }
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
                if ($finfo) finfo_close($finfo);
                $realMime = $realMime === 'image/jpg' ? 'image/jpeg' : $realMime;
                $allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];
                if (!in_array($realMime, $allowedMimes, true)) {
                    $_SESSION['error'] = 'Invalid image type';
                    header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
                    exit;
                }
            }
            $uploadDir = UPLOAD_PATH;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'article_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                @chmod($targetPath, 0644);
                $data['image'] = $fileName;
            } else {
                $_SESSION['error'] = 'Failed to save image';
                header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
                exit;
            }
        }
        
        // Generate JSON-LD for both languages
        $seoSettings = $this->seoModel->getSettings();
        
        try {
            if ($id) {
                // Update existing article
                $article = $this->articleModel->getById($id);
                $mergedData = array_merge($article, $data);
                
                // Regenerate JSON-LD
                $mergedData['jsonld_ru'] = ArticleJsonLdGenerator::generateArticleGraph(
                    $mergedData,
                    'ru',
                    $seoSettings
                );
                $mergedData['jsonld_uz'] = ArticleJsonLdGenerator::generateArticleGraph(
                    $mergedData,
                    'uz',
                    $seoSettings
                );
                
                $data['jsonld_ru'] = $mergedData['jsonld_ru'];
                $data['jsonld_uz'] = $mergedData['jsonld_uz'];
                
                $this->articleModel->update($id, $data);
                $_SESSION['success'] = 'Article updated successfully';
            } else {
                // Create new article
                $newId = $this->articleModel->create($data);
                
                // Get the created article and generate JSON-LD
                $newArticle = $this->articleModel->getById($newId);
                $newArticle['jsonld_ru'] = ArticleJsonLdGenerator::generateArticleGraph(
                    $newArticle,
                    'ru',
                    $seoSettings
                );
                $newArticle['jsonld_uz'] = ArticleJsonLdGenerator::generateArticleGraph(
                    $newArticle,
                    'uz',
                    $seoSettings
                );
                
                // Update with JSON-LD
                $this->articleModel->update($newId, [
                    'jsonld_ru' => $newArticle['jsonld_ru'],
                    'jsonld_uz' => $newArticle['jsonld_uz']
                ]);
                
                $_SESSION['success'] = 'Article created successfully';
                $id = $newId;
            }
            
            header('Location: ' . BASE_URL . '/admin/articles/edit/' . $id);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error saving article: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/admin/articles' . ($id ? '/edit/' . $id : '/new'));
        }
        
        exit;
    }

    /**
     * Delete article
     */
    public function delete() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request'], 400);
            return;
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Article ID required'], 400);
            return;
        }
        
        try {
            $this->articleModel->delete($id);
            $this->json(['success' => true, 'message' => 'Article deleted successfully']);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => 'Error deleting article: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle publish status
     */
    public function togglePublish() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request'], 400);
            return;
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Article ID required'], 400);
            return;
        }
        
        try {
            $this->articleModel->togglePublish($id);
            $article = $this->articleModel->getById($id);
            $this->json([
                'success' => true,
                'is_published' => (bool)$article['is_published'],
                'message' => $article['is_published'] ? 'Article published' : 'Article unpublished'
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => 'Error toggling publish status: ' . $e->getMessage()], 500);
        }
    }
}
