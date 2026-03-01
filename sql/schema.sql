-- ============================================================
-- 予約管理システム データベーススキーマ
-- 飲食店・美容室・マッサージ等向け
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- データベース作成（必要に応じて使用）
-- CREATE DATABASE IF NOT EXISTS reservation_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE reservation_db;

-- ============================================================
-- 管理者テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT 'ログインID',
    password_hash VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ',
    name VARCHAR(100) NOT NULL COMMENT '表示名',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 顧客テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'LINE ユーザーID',
    name VARCHAR(100) NOT NULL COMMENT '氏名',
    phone VARCHAR(20) NOT NULL COMMENT '電話番号',
    points INT DEFAULT 0 COMMENT '保有ポイント',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- メニュー（施術メニュー・コース）テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'メニュー名',
    price INT NOT NULL COMMENT '料金（税込）',
    duration INT NOT NULL DEFAULT 60 COMMENT '所要時間（分）',
    description TEXT COMMENT '説明',
    points_rate INT DEFAULT 1 COMMENT 'ポイント付与率（%）',
    is_active TINYINT(1) DEFAULT 1 COMMENT '表示フラグ',
    sort_order INT DEFAULT 0 COMMENT '表示順',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 予約テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '顧客ID',
    menu_id INT COMMENT 'メニューID',
    menu_name VARCHAR(100) COMMENT 'メニュー名（スナップショット）',
    reservation_date DATE NOT NULL COMMENT '予約日',
    reservation_time TIME NOT NULL COMMENT '予約時刻（開始）',
    end_time TIME NOT NULL COMMENT '予約時刻（終了）',
    price INT NOT NULL COMMENT '料金',
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending'
        COMMENT 'ステータス: pending=仮予約, confirmed=確定, cancelled=キャンセル, completed=完了',
    google_event_id VARCHAR(255) COMMENT 'GoogleカレンダーイベントID',
    customer_notes TEXT COMMENT '顧客からの備考',
    staff_notes TEXT COMMENT 'スタッフメモ',
    points_earned INT DEFAULT 0 COMMENT '獲得ポイント',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE SET NULL,
    INDEX idx_reservation_date (reservation_date),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ポイント履歴テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '顧客ID',
    reservation_id INT COMMENT '予約ID（関連する場合）',
    points INT NOT NULL COMMENT 'ポイント数（付与:正, 使用:負）',
    type ENUM('earned', 'used', 'adjusted', 'expired') NOT NULL
        COMMENT 'earned=付与, used=使用, adjusted=手動調整, expired=期限切れ',
    description VARCHAR(255) COMMENT '説明',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- システム設定テーブル
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL COMMENT '設定キー',
    value TEXT COMMENT '設定値',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 初期データ
-- ============================================================

-- デフォルト管理者アカウント (password: admin123 → 本番環境では必ず変更)
INSERT INTO admins (username, password_hash, name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理者');

-- サンプルメニュー（飲食・美容・マッサージ共通で参考）
INSERT INTO menus (name, price, duration, description, points_rate, sort_order) VALUES
('カット', 4400, 60, 'シャンプー・ブロー込み', 1, 1),
('カット + カラー', 11000, 120, 'シャンプー・ブロー込み', 1, 2),
('カット + パーマ', 13200, 150, 'シャンプー・ブロー込み', 1, 3),
('ヘッドスパ（60分）', 6600, 60, '頭皮ケア・リラクゼーション', 1, 4),
('トリートメント', 3300, 30, '毛髪補修トリートメント', 1, 5);

-- システム設定
INSERT INTO settings (key_name, value) VALUES
('shop_name', 'サロン名'),
('shop_phone', '00-0000-0000'),
('shop_address', '住所を入力'),
('reservation_start_hour', '9'),
('reservation_end_hour', '19'),
('reservation_interval', '30'),
('points_per_yen', '1'),
('google_calendar_id', ''),
('line_notice_enabled', '1');
