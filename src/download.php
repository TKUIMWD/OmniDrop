<?php
session_start();
require 'db.php';

if (isset($_GET['file']) && isset($_GET['share'])) {
    $uuid = $_GET['file'];
    $share = $_GET['share'];

    try {
        $stmt_file = $pdo->prepare('SELECT id, real_path FROM files WHERE uuid = ?');
        $stmt_file->execute([$uuid]);
        $result = $stmt_file;
        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $real_path = $row['real_path'];
            $file_id = $row['id'];
            
            // Validate the share token matches the queried file_id (IDOR prevention)
            $stmt_share = $pdo->prepare('SELECT id, password FROM shares WHERE share_uuid = ? AND file_id = ?');
            $stmt_share->execute([$share, $file_id]);
            $share_record = $stmt_share->fetch();
            if (!$share_record) {
                die("Invalid share token for this file.");
            }

            // Check if share requires a password, enforce that the user unlocked it in their session
            if ($share_record['password'] !== null) {
                if (!isset($_SESSION['authenticated_share_' . $share]) || $_SESSION['authenticated_share_' . $share] !== true) {
                    die("Unauthorized access to password-protected share.");
                }
            }

            // Check share limits (only increment and enforce for the specific share link used)
            $stmt_limit = $pdo->prepare('
                SELECT 1 FROM shares 
                WHERE share_uuid = ? AND 
                ((max_downloads > 0 AND downloads >= max_downloads) OR 
                 (expires_at IS NOT NULL AND expires_at < NOW()))
            ');
            $stmt_limit->execute([$share]);
            if ($stmt_limit->fetch()) {
                die("The share link for this file has expired or reached its limit.");
            }

            // Increment the download count for THAT specific share link
            $stmt_inc = $pdo->prepare('UPDATE shares SET downloads = downloads + 1 WHERE share_uuid = ?');
            $stmt_inc->execute([$share]);

            // Map real path back to relative or read file content
            if (strpos($real_path, '/var/www/html') !== 0) { $real_path = __DIR__ . $real_path; }

            // Validate the file path stays within the storage directory
            if (file_exists($real_path) && strpos(realpath($real_path), '/var/www/html/storage') === 0) {
                // Return clean filename without exposing internal routing prefix (uuid_disk_uuid_)
                $basename = preg_replace('/^.*[\\\\\\/]/', '', $real_path);
                $clean_filename = preg_replace('/^([a-fA-F0-9]{32}_){2}/', '', $basename);
                
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . addslashes($clean_filename) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($real_path));
                readfile($real_path);
                exit;
            } else {
                echo "File does not exist internally.";
            }
        } else {
            echo "Access denied or File does not exist";
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Access denied or File does not exist";
    }
} else {
    echo "Missing file or share parameter";
}
?>