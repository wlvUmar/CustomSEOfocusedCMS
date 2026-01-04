<?php
class DashboardController extends Controller {
    public function index() {
        $this->requireAuth();
        
        // Get basic stats
        $stats = [
            'total_pages' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pages")['count'],
            'published_pages' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'],
            'total_media' => $this->db->fetchOne("SELECT COUNT(*) as count FROM media")['count'],
            'total_faqs' => $this->db->fetchOne("SELECT COUNT(*) as count FROM faqs")['count'],
            'total_rotations' => $this->db->fetchOne("SELECT COUNT(*) as count FROM content_rotations")['count'],
            'active_rotations' => $this->db->fetchOne("SELECT COUNT(*) as count FROM content_rotations WHERE is_active = 1")['count'],
            'total_pages_with_rotation' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE enable_rotation = 1")['count'],
            'today_visits' => $this->db->fetchOne("SELECT COALESCE(SUM(visits), 0) as visits FROM analytics WHERE date = CURDATE()")['visits'],
            'today_clicks' => $this->db->fetchOne("SELECT COALESCE(SUM(clicks), 0) as clicks FROM analytics WHERE date = CURDATE()")['clicks'],
            'week_visits' => $this->db->fetchOne("SELECT COALESCE(SUM(visits), 0) as visits FROM analytics WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")['visits'],
        ];
        
        $this->view('admin/dashboard', ['stats' => $stats]);
    }
}
