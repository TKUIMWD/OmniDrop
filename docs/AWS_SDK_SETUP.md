# AWS SDK 配置指南

## 安裝和初始化

### 1. 構建 Docker 映像
```bash
docker-compose build
```

此命令會自動安裝 Composer 和所有依賴項（包括 AWS SDK）。

### 2. 配置 AWS 認證

#### 方法 A：使用環境變數（建議用於 Docker）

在 `docker-compose.yml` 中添加環境變數：

```yaml
services:
  web:
    build: ./php
    environment:
      AWS_ACCESS_KEY_ID: your_access_key_here
      AWS_SECRET_ACCESS_KEY: your_secret_key_here
      AWS_DEFAULT_REGION: ap-southeast-1
    # ... 其他配置
```

#### 方法 B：使用 .env 文件

創建 `.env` 文件在項目根目錄：

```
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=ap-southeast-1
```

在 PHP 代碼中讀取：
```php
<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => getenv('AWS_DEFAULT_REGION') ?: 'ap-southeast-1'
]);
?>
```

### 3. 基本使用示例

#### 連接到 S3

```php
<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => 'ap-southeast-1'
]);

try {
    // 列出 bucket
    $buckets = $s3Client->listBuckets();
    
    // 上傳文件到 S3
    $s3Client->putObject([
        'Bucket' => 'your-bucket-name',
        'Key'    => 'path/to/file.txt',
        'Body'   => fopen('/local/path/to/file.txt', 'r')
    ]);
    
    // 下載文件
    $result = $s3Client->getObject([
        'Bucket' => 'your-bucket-name',
        'Key'    => 'path/to/file.txt'
    ]);
    
    file_put_contents('/local/path/to/downloaded.txt', $result['Body']);
    
} catch (AwsException $e) {
    echo "錯誤: " . $e->getMessage();
}
?>
```

## Docker 運行

### 啟動容器
```bash
docker-compose up -d
```

### 停止容器
```bash
docker-compose down
```

### 查看日誌
```bash
docker-compose logs -f web
```

## 常見問題

### 1. Composer 安裝失敗
- 確保 Docker 可以訪問網絡
- 檢查 `composer.json` 語法

### 2. AWS SDK 認證失敗
- 驗證 AWS 認證密鑰
- 檢查 IAM 權限
- 確認 AWS_DEFAULT_REGION 設置正確

### 3. 容器啟動失敗
```bash
docker-compose logs web
```
查看詳細錯誤日誌

## 進一步配置

### 安裝其他 AWS 服務

編輯 `composer.json` 添加所需服務：

```json
{
    "require": {
        "aws/aws-sdk-php": "^3.300",
        "aws/aws-dynamodb-encryption": "^1.5"
    }
}
```

然後運行：
```bash
docker-compose rebuild
```

## 安全建議

1. **不要提交敏感信息**：`.env` 文件已在 `.gitignore` 中
2. **使用 IAM 角色**：在 AWS EC2 上，優先使用 IAM 角色而不是密鑰
3. **限制 IAM 權限**：只授予必需的權限
4. **定期輪換密鑰**：定期更新 AWS 認證密鑰
