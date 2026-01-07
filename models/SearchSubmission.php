<?php
// path: ./models/SearchSubmission.php

class SearchSubmission {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Log a new submission attempt
     */
    public function log($slug, $url, $engine, $type, $status, $code, $message, $rotationMonth = null, $userId = null) {
        $sql = "INSERT INTO search_submissions 
                (page_slug, url, search_engine, submission_type, status, 
                 response_code, response_message, rotation_month, user_id, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $slug, $url, $engine, $type, $status, 
            $code, $message, $rotationMonth, $userId
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update or create the status record for a page/engine
     */
    public function updateStatus($slug, $engine, $status, $message) {
        $sql = "INSERT INTO search_submission_status 
                (page_slug, search_engine, last_submitted_at, last_success_at, 
                 total_submissions, successful_submissions, failed_submissions,
                 last_status, last_response, can_resubmit_at)
                VALUES (?, ?, NOW(), ?, 1, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                last_submitted_at = NOW(),
                last_success_at = IF(? = 'success', NOW(), last_success_at),
                total_submissions = total_submissions + 1,
                successful_submissions = successful_submissions + IF(? = 'success', 1, 0),
                failed_submissions = failed_submissions + IF(? = 'failed', 1, 0),
                last_status = VALUES(last_status),
                last_response = VALUES(last_response),
                can_resubmit_at = VALUES(can_resubmit_at)";
        
        $lastSuccess = $status === 'success' ? date('Y-m-d H:i:s') : null;
        $successInc = $status === 'success' ? 1 : 0;
        $failInc = $status === 'failed' ? 1 : 0;
        
        // Calculate next allowed submission time (1 hour cooldown)
        $canResubmit = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->db->query($sql, [
            $slug, $engine, $lastSuccess, 
            $successInc, $failInc, $status, $message, $canResubmit,
            $status, $status, $status
        ]);
    }

    /**
     * Get submission history for a page
     */
    public function getHistory($slug, $limit = 20) {
        $sql = "SELECT * FROM search_submissions 
                WHERE page_slug = ? 
                ORDER BY submitted_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$slug, $limit]);
    }

    /**
     * Get current status for a page across all engines
     */
    public function getStatus($slug) {
        $sql = "SELECT * FROM search_submission_status WHERE page_slug = ?";
        $results = $this->db->fetchAll($sql, [$slug]);
        
        $status = [];
        foreach ($results as $row) {
            $status[$row['search_engine']] = $row;
        }
        
        return $status;
    }

    /**
     * Check if a page was recently submitted successfully
     */
    public function wasRecentlySubmitted($slug, $engine, $seconds = 3600) {
        $sql = "SELECT COUNT(*) as count 
                FROM search_submissions 
                WHERE page_slug = ? 
                AND search_engine = ? 
                AND status = 'success'
                AND submitted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $result = $this->db->fetchOne($sql, [$slug, $engine, $seconds]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get recent submissions globally
     */
    public function getRecent($limit = 50) {
        // Try view first, then fallback
        try {
            return $this->db->fetchAll("SELECT * FROM v_recent_submissions LIMIT " . (int)$limit);
        } catch (Exception $e) {
            return $this->db->fetchAll("
                SELECT 
                    s.id,
                    s.page_slug,
                    COALESCE(p.title_ru, p.title_uz, '') AS page_title,
                    s.search_engine,
                    s.submission_type,
                    s.status,
                    s.submitted_at
                FROM search_submissions s
                LEFT JOIN pages p ON p.slug = s.page_slug
                ORDER BY s.submitted_at DESC
                LIMIT " . (int)$limit
            );
        }
    }

    /**
     * Get unsubmitted pages
     */
    public function getUnsubmitted($limit = 20) {
        try {
            return $this->db->fetchAll("SELECT * FROM v_unsubmitted_pages LIMIT " . (int)$limit);
        } catch (Exception $e) {
            return $this->db->fetchAll("
                SELECT 
                    p.id,
                    p.slug,
                    p.title_ru,
                    p.title_uz,
                    p.published,
                    p.created_at
                FROM pages p
                WHERE p.published = 1
                AND NOT EXISTS (
                    SELECT 1 FROM search_submissions s 
                    WHERE s.page_slug = p.slug 
                    AND s.status = 'success'
                )
                ORDER BY p.created_at DESC
                LIMIT " . (int)$limit
            );
        }
    }

    /**
     * Get pages due for resubmission
     */
    public function getDueResubmit($limit = 20) {
        try {
            return $this->db->fetchAll("SELECT * FROM v_pages_due_resubmit LIMIT " . (int)$limit);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get aggregate statistics by engine
     */
    public function getStatsByEngine() {
        try {
            return $this->db->fetchAll("SELECT * FROM v_submission_stats");
        } catch (Exception $e) {
             return $this->db->fetchAll("
                SELECT 
                    c.engine,
                    c.enabled,
                    c.submissions_today,
                    c.rate_limit_per_day,
                    COALESCE(COUNT(s.id), 0) as total_all_time,
                    COALESCE(SUM(CASE WHEN s.status = 'success' THEN 1 ELSE 0 END), 0) as total_success,
                    COALESCE(SUM(CASE WHEN s.status = 'failed' THEN 1 ELSE 0 END), 0) as total_failed,
                    CASE WHEN COALESCE(COUNT(s.id),0) > 0 THEN ROUND(COALESCE(SUM(CASE WHEN s.status = 'success' THEN 1 ELSE 0 END),0) / COALESCE(COUNT(s.id),0) * 100, 2) ELSE 0 END AS success_rate_percent
                FROM search_engine_config c
                LEFT JOIN search_submissions s ON s.search_engine = c.engine
                GROUP BY c.engine, c.enabled, c.submissions_today, c.rate_limit_per_day
            ");
        }
    }
}
