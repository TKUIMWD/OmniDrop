# OmniDrop

OmniDrop 是一套自架式檔案交換服務。  
提供個人儀表板、檔案管理、安全分享連結，以及訪客上傳通道讓外部協作者無須註冊帳號，即可透過專屬連結將檔案直接傳送至指定帳戶。

---

## 功能介紹

- 檔案管理：上傳、預覽、下載與刪除檔案
- 安全分享：建立可設定密碼、到期時間與下載次數上限的分享連結
- Dropzone：為外部協作者建立一次性或限次訪客上傳連結
- 主題切換：支援深色 / 淺色模式，管理員可自訂強調色
- 管理員面板：管理使用者與系統設定
- 使用者設定：頭像、密碼變更與帳號刪除

---

## 環境需求

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

---

## 部署方式

**一鍵部署：**

```bash
chmod +x run.sh
./run.sh
```

腳本會自動清除舊容器與資料卷，重新建立並啟動新環境。

**手動部署：**

```bash
docker-compose down -v
docker-compose up -d --build
```

服務啟動後，可透過瀏覽器訪問 `http://127.0.0.1:12340/`（Port 可在 `docker-compose.yml` 中修改）。

---

## 預設帳號

| 角色   | 帳號    | 密碼    |
|--------|---------|---------|
| 管理員 | `admin` | `admin` |

請於首次登入後立即至「設定」頁面更改密碼。

---

## 資料庫連線

```bash
# 一般使用者
docker-compose exec db mysql -u omniuser -pomnipassword!! omnidrop

# Root
docker-compose exec db mysql -u root -prootpassword omnidrop
```

---

## 專案結構

```
OmniDrop/
├── docker-compose.yml
├── run.sh
├── mysql/
│   └── init.sql              # 資料庫結構與初始資料
├── php/
│   └── Dockerfile            # PHP 7.4 + Apache 映像
└── src/                      # Web 應用程式根目錄
    ├── index.php             # 登入 / 註冊
    ├── files.php             # 檔案管理
    ├── shares.php            # 分享連結管理
    ├── reverse_shares.php    # Dropzone 管理
    ├── r.php                 # 訪客上傳入口
    ├── s.php                 # 公開分享下載頁
    ├── download.php          # 已驗證的檔案下載
    ├── admin.php             # 管理員面板
    ├── settings.php          # 使用者設定
    ├── sidebar.php           # 側邊欄導覽元件
    ├── db.php                # 資料庫連線
    ├── logout.php
    ├── storage/              # 上傳檔案存放目錄
    └── css/                  # 樣式表
```

---

## 安全說明

- 所有上傳（一般使用者與訪客）均限制副檔名白名單（圖片、文件、壓縮檔）
- `storage/` 目錄透過 `.htaccess` 禁止 PHP 執行
- 所有資料庫查詢使用 PDO Prepared Statement
- 使用者密碼與分享連結密碼均以 bcrypt 儲存

---

## 授權

MIT