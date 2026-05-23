<?php
/**
 * AWS SDK Example - S3 File Upload/Download
 * 
 * This is an example file showing how to integrate AWS SDK
 * with the OmniDrop application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3FileManager
{
    private $s3Client;
    private $bucket;
    
    public function __construct($bucket = null)
    {
        $this->bucket = $bucket ?: getenv('AWS_S3_BUCKET') ?: 'omnidrop-files';
        
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region'  => getenv('AWS_DEFAULT_REGION') ?: 'ap-southeast-1'
        ]);
    }
    
    /**
     * Upload file to S3
     * 
     * @param string $filePath Local file path
     * @param string $key S3 object key
     * @return bool
     */
    public function uploadFile($filePath, $key)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("文件不存在: $filePath");
            }
            
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => fopen($filePath, 'r'),
                'ACL'    => 'private'
            ]);
            
            return true;
        } catch (AwsException $e) {
            error_log("S3 上傳錯誤: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download file from S3
     * 
     * @param string $key S3 object key
     * @param string $targetPath Local path to save
     * @return bool
     */
    public function downloadFile($key, $targetPath)
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $key
            ]);
            
            file_put_contents($targetPath, $result['Body']);
            return true;
        } catch (AwsException $e) {
            error_log("S3 下載錯誤: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete file from S3
     * 
     * @param string $key S3 object key
     * @return bool
     */
    public function deleteFile($key)
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key
            ]);
            return true;
        } catch (AwsException $e) {
            error_log("S3 刪除錯誤: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List files in S3 bucket
     * 
     * @param string $prefix Optional prefix to filter
     * @return array
     */
    public function listFiles($prefix = '')
    {
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ]);
            
            return $result['Contents'] ?? [];
        } catch (AwsException $e) {
            error_log("S3 列表錯誤: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate presigned URL for file sharing
     * 
     * @param string $key S3 object key
     * @param int $expiresIn Expiration time in seconds
     * @return string
     */
    public function getPresignedUrl($key, $expiresIn = 3600)
    {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, '+' . $expiresIn . ' seconds');
            $presignedUrl = (string)$request->getUri();
            
            return $presignedUrl;
        } catch (AwsException $e) {
            error_log("生成預簽名 URL 錯誤: " . $e->getMessage());
            return '';
        }
    }
}

// Example usage:
/*
$fileManager = new S3FileManager('my-omnidrop-bucket');

// Upload
$fileManager->uploadFile('/local/file.txt', 'uploads/file.txt');

// Download
$fileManager->downloadFile('uploads/file.txt', '/local/downloaded.txt');

// List
$files = $fileManager->listFiles('uploads/');

// Presigned URL for sharing
$url = $fileManager->getPresignedUrl('uploads/file.txt', 3600);
echo $url;

// Delete
$fileManager->deleteFile('uploads/file.txt');
*/
?>
