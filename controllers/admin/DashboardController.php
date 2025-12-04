<?php
class DashboardController extends Controller {
    public function index() {
        $this->requireAuth();
        
        // Get basic stats
        $stats = [
            'total_pages' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pages")['count'],
            'published_pages' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'],
            'total_media' => $this->db->fetchOne("SELECT COUNT(*) as count FROM media")['count'],
        ];
        
        $this->view('admin/dashboard', ['stats' => $stats]);
    }
}
