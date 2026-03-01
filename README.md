# 予約管理システム

飲食店・美容室・マッサージ等の店舗向け予約管理システム（PHP + MySQL）

## 機能一覧

### 客側
- **LINEログイン** — OAuth 2.0 による LINE 認証（初回のみ名前・電話番号登録）
- **予約フォーム** — メニュー選択 → 日付 → 時間スロット選択の 3 ステップ予約
- **マイページ** — 保有ポイント確認・予約履歴・ポイント履歴

### 店側（管理画面 `/admin/`）
- **ダッシュボード** — 本日の予約・週次件数・月次売上・顧客数を一覧表示
- **予約管理** — 一覧・絞り込み・確定/完了/キャンセル操作・Googleカレンダー自動連携
- **顧客一覧** — 名前クリックで最終来店日・来店回数・累計売上・売上履歴を表示
- **ポイント管理** — 一覧・手動付与・調整
- **メニュー管理** — 追加・編集・無効化
- **Googleカレンダー連携** — OAuth 2.0 認証・予約確定時に自動イベント作成
- **設定** — 店舗情報・営業時間・管理者パスワード変更

## ディレクトリ構成

```
/
├── config/
│   └── config.php          # 各種設定（要編集）
├── sql/
│   └── schema.sql          # テーブル定義 + 初期データ
├── includes/
│   ├── db.php              # DB接続（PDO シングルトン）
│   ├── functions.php       # 共通関数
│   ├── line_login.php      # LINE Login ヘルパー
│   └── google_calendar.php # Google Calendar API ヘルパー
├── public/                 # 顧客向けページ
│   ├── index.php           # トップ / LINEログイン
│   ├── callback.php        # LINE OAuth コールバック
│   ├── register.php        # 初回登録
│   ├── reserve.php         # 予約フォーム
│   ├── mypage.php          # マイページ
│   └── logout.php
├── admin/                  # 管理者向けページ
│   ├── login.php
│   ├── dashboard.php
│   ├── reservations.php
│   ├── customers.php
│   ├── customer_detail.php
│   ├── points.php
│   ├── menus.php
│   ├── google_auth.php
│   ├── google_callback.php
│   ├── settings.php
│   ├── logout.php
│   └── partials/
│       ├── navbar.php
│       └── sidebar.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
└── .htaccess
```

## セットアップ手順

### 1. データベース作成

```sql
CREATE DATABASE reservation_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Xサーバーのコントロールパネルからデータベースとユーザーを作成し、
`sql/schema.sql` をインポートしてください。

### 2. 設定ファイルの編集

`config/config.php` を開き、以下を設定してください。

```php
// データベース
define('DB_HOST', 'localhost');
define('DB_NAME', 'reservation_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// LINE Login（LINE Developersコンソールで取得）
define('LINE_CHANNEL_ID',     'YOUR_CHANNEL_ID');
define('LINE_CHANNEL_SECRET', 'YOUR_CHANNEL_SECRET');
define('LINE_REDIRECT_URI',   'https://yourdomain.com/public/callback.php');

// Google Calendar API（Google Cloud Consoleで取得）
define('GOOGLE_CLIENT_ID',     'YOUR_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'https://yourdomain.com/admin/google_callback.php');

// サイトURL
define('APP_URL', 'https://yourdomain.com');
```

### 3. LINE Developers 設定

1. [LINE Developers Console](https://developers.line.biz/console/) でプロバイダー・チャネルを作成
2. チャネル種別: **LINE ログイン**
3. コールバックURL に `https://yourdomain.com/public/callback.php` を登録
4. チャネルID・チャネルシークレットを `config.php` に設定

### 4. Google Calendar API 設定

1. [Google Cloud Console](https://console.cloud.google.com/) でプロジェクト作成
2. **Google Calendar API** を有効化
3. 「認証情報」→「OAuth 2.0 クライアント ID」を作成（種類: ウェブアプリケーション）
4. リダイレクトURIに `https://yourdomain.com/admin/google_callback.php` を追加
5. クライアントID・シークレットを `config.php` に設定
6. 管理画面 → 「Googleカレンダー」から認証

### 5. 管理者パスワードの変更

初期パスワード: `admin123`（**必ず本番前に変更してください**）

管理画面ログイン後、「設定」ページからパスワードを変更できます。

### 6. Xサーバーへのアップロード

FTPまたはファイルマネージャーで `/public_html/` 以下にアップロード。
`.htaccess` でトップページは `public/index.php` にリダイレクトされます。

## セキュリティ

- CSRF トークンによる全フォーム保護
- PDO プリペアドステートメントによる SQL インジェクション対策
- XSS 対策（`htmlspecialchars` による全出力エスケープ）
- セッション固定攻撃対策（ログイン後に `session_regenerate_id`）
- 機密ファイル（`config/`, `includes/`, `sql/`）への HTTP アクセス禁止

## 動作環境

- PHP 8.0 以上
- MySQL 5.7 以上 / MariaDB 10.3 以上
- cURL 拡張モジュール（LINE・Google API 通信用）
- mod_rewrite（Apache）
