<?php
session_start();
require 'db.php';

if (isset($_GET['file']) && isset($_GET['share'])) {
    $uuid = $_GET['file'];
    $share = $_GET['share'];

    try {
        $stmt_file = $pdo->prepare('SELECT id, real_path, filename FROM files WHERE uuid = ?');
        $stmt_file->execute([$uuid]);
        $result = $stmt_file;
        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $s3_key = $row['real_path'];
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

            // Get presigned URL from S3
            $presigned_url = $s3Manager->getPresignedUrl($s3_key, 3600); // 1 hour expiry
            if ($presigned_url) {
                // Redirect to presigned URL
                header('Location: ' . $presigned_url);
                exit;
            } else {
                echo "Failed to generate download link.";
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