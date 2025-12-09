<?php
/**
 * Database Helper for Toast Management
 */

class ToastDB {
    private $db;
    
    public function __construct() {
        $dbPath = __DIR__ . '/toasts.db';
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    }
    
    /**
     * Add toast to history
     */
    public function addHistory($data) {
        $stmt = $this->db->prepare("
            INSERT INTO toast_history (toast_subject, toast_body, toast_picture, toast_color, toast_time, toast_volume, raw_json)
            VALUES (:subject, :body, :picture, :color, :time, :volume, :json)
        ");
        
        return $stmt->execute([
            ':subject' => $data['ToastSubject'] ?? null,
            ':body' => $data['ToastBody'] ?? null,
            ':picture' => $data['ToastPicture'] ?? null,
            ':color' => $data['ToastColor'] ?? null,
            ':time' => $data['ToastTime'] ?? null,
            ':volume' => $data['ToastVolume'] ?? null,
            ':json' => json_encode($data)
        ]);
    }
    
    /**
     * Get history with pagination
     */
    public function getHistory($limit = 100, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM toast_history 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total history count
     */
    public function getHistoryCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM toast_history");
        return $stmt->fetch()->count;
    }
    
    /**
     * Add toast to favorites
     */
    public function addFavorite($data) {
        $stmt = $this->db->prepare("
            INSERT INTO toast_favorites (toast_subject, toast_body, toast_picture, toast_color, toast_time, toast_volume, raw_json)
            VALUES (:subject, :body, :picture, :color, :time, :volume, :json)
        ");
        
        return $stmt->execute([
            ':subject' => $data['ToastSubject'] ?? null,
            ':body' => $data['ToastBody'] ?? null,
            ':picture' => $data['ToastPicture'] ?? null,
            ':color' => $data['ToastColor'] ?? null,
            ':time' => $data['ToastTime'] ?? null,
            ':volume' => $data['ToastVolume'] ?? null,
            ':json' => json_encode($data)
        ]);
    }
    
    /**
     * Get all favorites
     */
    public function getFavorites() {
        $stmt = $this->db->query("
            SELECT * FROM toast_favorites 
            ORDER BY created_at DESC
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Delete favorite by ID
     */
    public function deleteFavorite($id) {
        $stmt = $this->db->prepare("DELETE FROM toast_favorites WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Delete favorite by JSON match
     */
    public function deleteFavoriteByJson($jsonData) {
        $stmt = $this->db->prepare("DELETE FROM toast_favorites WHERE raw_json = :json");
        return $stmt->execute([':json' => json_encode($jsonData)]);
    }
    
    /**
     * Search history
     */
    public function searchHistory($query, $limit = 100) {
        $stmt = $this->db->prepare("
            SELECT * FROM toast_history 
            WHERE toast_subject LIKE :query 
               OR toast_body LIKE :query
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Add YouTube URL to history
     */
    public function addYouTubeHistory($url) {
        // Check if URL already exists
        $stmt = $this->db->prepare("SELECT id FROM youtube_history WHERE url = :url");
        $stmt->execute([':url' => $url]);
        
        if ($existing = $stmt->fetch()) {
            // URL exists - update timestamp to move it to top
            $updateStmt = $this->db->prepare("
                UPDATE youtube_history 
                SET created_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            return $updateStmt->execute([':id' => $existing->id]);
        }
        
        // New URL - insert it
        $stmt = $this->db->prepare("
            INSERT INTO youtube_history (url, video_id)
            VALUES (:url, :video_id)
        ");
        
        // Extract video ID
        $videoId = $this->extractYouTubeVideoId($url);
        
        return $stmt->execute([
            ':url' => $url,
            ':video_id' => $videoId
        ]);
    }
    
    /**
     * Get YouTube history with pagination
     */
    public function getYouTubeHistory($limit = 100, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM youtube_history 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total YouTube history count
     */
    public function getYouTubeCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM youtube_history");
        return $stmt->fetch()->count;
    }
    
    /**
     * Extract YouTube video ID from various URL formats
     */
    private function extractYouTubeVideoId($url) {
        // YouTube Shorts format: https://www.youtube.com/shorts/VIDEO_ID
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/i', $url, $matches)) {
            return $matches[1];
        }
        
        // Standard format: watch?v=VIDEO_ID
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        if (isset($params['v'])) {
            return trim($params['v'], "_ \t\n\r\0\x0B");
        }
        
        // Short URL format: youtu.be/VIDEO_ID
        $path = parse_url($url, PHP_URL_PATH);
        $potentialId = trim($path, '/');
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $potentialId)) {
            return $potentialId;
        }
        
        return null;
    }
}
