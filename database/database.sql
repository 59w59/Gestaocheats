-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','banned') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Sessões
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `last_activity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Tokens de Recuperação de Senha
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Logs de Atividades do Usuário
CREATE TABLE IF NOT EXISTS `user_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Jogos (MOVIDA PARA ANTES DA TABELA CHEATS)
CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Cheats (AGORA VEM DEPOIS DA TABELA GAMES)
CREATE TABLE IF NOT EXISTS `cheats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `game_id` int(11) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `version` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `min_subscription_level` int DEFAULT 1 COMMENT 'Minimum subscription level required to access this cheat (1=Basic, 2=Premium, 3=VIP)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `cheats_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para planos de assinatura dos cheats
CREATE TABLE IF NOT EXISTS `cheat_subscription_plans` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cheat_id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `description` text NULL,
    `price` decimal(10,2) NOT NULL,
    `duration_days` int(11) NOT NULL,
    `features` text NULL,
    `discount_percentage` int(11) DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `display_order` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_cheat` (`cheat_id`),
    CONSTRAINT `fk_cheat_subscription_plans_cheat_id` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para assinaturas de usuários
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `cheat_plan_id` int(11) NOT NULL,
    `status` enum('active','expired','cancelled','suspended') NOT NULL DEFAULT 'active',
    `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
    `end_date` timestamp NOT NULL DEFAULT current_timestamp(),
    `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
    `hwid` varchar(255) NULL,
    `hwid_updated_at` timestamp NULL,
    `notes` text NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_subscription` (`user_id`,`cheat_plan_id`),
    KEY `idx_status` (`status`),
    KEY `idx_end_date` (`end_date`),
    CONSTRAINT `fk_user_subscriptions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_subscriptions_plan_id` FOREIGN KEY (`cheat_plan_id`) REFERENCES `cheat_subscription_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para pagamentos
CREATE TABLE IF NOT EXISTS `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `subscription_id` int(11) NOT NULL,
    `transaction_id` varchar(100) NOT NULL,
    `payment_method` varchar(20) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `currency` varchar(3) NOT NULL DEFAULT 'BRL',
    `status` enum('pending','completed','failed','refunded','cancelled') NOT NULL,
    `gateway_response` text NULL,
    `pix_code` text NULL,
    `card_last_digits` varchar(4) NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_subscription` (`subscription_id`),
    KEY `idx_transaction` (`transaction_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_payments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para cartões salvos
CREATE TABLE IF NOT EXISTS `user_payment_methods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `payment_type` enum('card','bank_account') NOT NULL,
    `card_brand` varchar(20) NULL,
    `last_four_digits` varchar(4) NULL,
    `holder_name` varchar(100) NULL,
    `expiry_month` varchar(2) NULL,
    `expiry_year` varchar(2) NULL,
    `token` varchar(255) NOT NULL,
    `is_default` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_user_payment_methods_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para logs de atividades do usuário relacionados a pagamentos
CREATE TABLE IF NOT EXISTS `user_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `action` varchar(50) NOT NULL,
    `description` text NULL,
    `ip_address` varchar(45) NULL,
    `user_agent` text NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_action` (`user_id`,`action`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_user_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Downloads de Usuários
CREATE TABLE IF NOT EXISTS `user_downloads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `cheat_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `cheat_id` (`cheat_id`),
  CONSTRAINT `user_downloads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_downloads_ibfk_2` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Tickets de Suporte
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `category` enum('technical','billing','account','other') NOT NULL DEFAULT 'technical',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_priority` (`priority`),
  CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Respostas de Tickets
CREATE TABLE IF NOT EXISTS `ticket_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ticket_responses_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) DEFAULT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `is_image` tinyint(1) NOT NULL DEFAULT 0,
  `is_video` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 DAY),
  `admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `response_id` (`response_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `ticket_attachments_response_fk` FOREIGN KEY (`response_id`) REFERENCES `ticket_responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ticket_fk` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Depoimentos
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` int(1) NOT NULL DEFAULT 5,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para tokens de "lembrar de mim"
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `token` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Administradores
CREATE TABLE IF NOT EXISTS `admins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `role` enum('super_admin','admin','editor') NOT NULL DEFAULT 'admin',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `last_login` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    INDEX `idx_admin_status` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Logs de Administradores
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `action` varchar(100) NOT NULL,
    `details` text DEFAULT NULL,
    `ip` varchar(45) NOT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `admin_id` (`admin_id`),
    CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Tentativas de Login
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(100) NOT NULL,
    `ip` varchar(45) NOT NULL,
    `user_agent` text DEFAULT NULL,
    `is_admin` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `username` (`username`),
    KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Notificações
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    cheat_id INT DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cheat_id) REFERENCES cheats(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type VARCHAR(50) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE user_activity_logs ADD COLUMN ip VARCHAR(45) NULL;

ALTER TABLE users ADD COLUMN discord_id VARCHAR(50) DEFAULT NULL;

ALTER TABLE admins 
MODIFY COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
ADD INDEX idx_admin_status (is_active);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configuração padrão de retenção de logs
INSERT INTO settings (setting_key, setting_value) 
VALUES ('log_retention_days', '90')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Remover tabela antiga de planos de assinatura
DROP TABLE IF EXISTS cheat_plans;
DROP TABLE IF EXISTS user_subscriptions;
DROP TABLE IF EXISTS subscription_plans;

-- Criar nova tabela de planos específicos por cheat
CREATE TABLE IF NOT EXISTS cheat_subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cheat_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features TEXT,
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    hwid_protection TINYINT(1) DEFAULT 0,
    update_frequency ENUM('daily','weekly','monthly') DEFAULT 'monthly',
    support_level ENUM('basic','priority','vip') DEFAULT 'basic',
    discord_access TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cheat_plan (cheat_id, slug),
    FOREIGN KEY (cheat_id) REFERENCES cheats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nova tabela de assinaturas de usuários
CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    cheat_plan_id INT NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    hwid VARCHAR(255) DEFAULT NULL,
    hwid_updated_at TIMESTAMP NULL,
    start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    payment_id VARCHAR(255),
    payment_method VARCHAR(50),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cheat_plan_id) REFERENCES cheat_subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir planos de exemplo
INSERT INTO cheat_subscription_plans 
(cheat_id, name, slug, description, price, duration_days, features, is_popular, hwid_protection, update_frequency, support_level, discord_access)
SELECT 
    c.id,
    'CS2 Basic',
    'cs2-basic',
    'Acesso básico ao cheat do CS2',
    29.90,
    30,
    'Aimbot básico;ESP básico (Boxes);Menu customizável',
    0,
    0,
    'monthly',
    'basic',
    0
FROM cheats c WHERE c.slug = 'cs2-pro-aim'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO cheat_subscription_plans 
(cheat_id, name, slug, description, price, duration_days, features, is_popular, hwid_protection, update_frequency, support_level, discord_access)
SELECT 
    c.id,
    'CS2 Pro',
    'cs2-pro',
    'Acesso premium ao cheat do CS2',
    49.90,
    30,
    'Aimbot avançado;ESP completo;Radar 3D;Skinchanger;Configs exclusivas',
    1,
    1,
    'weekly',
    'priority',
    1
FROM cheats c WHERE c.slug = 'cs2-pro-aim'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO cheat_subscription_plans 
(cheat_id, name, slug, description, price, duration_days, features, is_popular, hwid_protection, update_frequency, support_level, discord_access)
SELECT 
    c.id,
    'Warzone Elite',
    'warzone-elite',
    'Pacote completo para Warzone',
    79.90,
    30,
    'Aimbot premium;ESP avançado;Radar 3D;No recoil;Rapid fire;Weapon configs',
    1,
    1,
    'daily',
    'vip',
    1
FROM cheats c WHERE c.slug = 'warzone-radar'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Criar índices para melhor performance
CREATE INDEX idx_subscription_status ON user_subscriptions(status);
CREATE INDEX idx_subscription_dates ON user_subscriptions(start_date, end_date);
CREATE INDEX idx_cheat_plans_active ON cheat_subscription_plans(is_active);

-- Primeiro, dropar constraints antigas se existirem
SET FOREIGN_KEY_CHECKS = 0;

-- Remover tabelas antigas na ordem correta
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS user_subscriptions;
DROP TABLE IF EXISTS cheat_subscription_plans;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS user_downloads;
DROP TABLE IF EXISTS cheats;
DROP TABLE IF EXISTS games;

SET FOREIGN_KEY_CHECKS = 1;

-- Criar tabelas na ordem correta
CREATE TABLE IF NOT EXISTS games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    is_popular TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_game_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cheats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    game_id INT NOT NULL,
    short_description VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    version VARCHAR(20) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    download_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cheat_slug (slug),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cheat_subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cheat_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features TEXT,
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    hwid_protection TINYINT(1) DEFAULT 0,
    update_frequency ENUM('daily','weekly','monthly') DEFAULT 'monthly',
    support_level ENUM('basic','priority','vip') DEFAULT 'basic',
    discord_access TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cheat_plan (cheat_id, slug),
    FOREIGN KEY (cheat_id) REFERENCES cheats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    cheat_plan_id INT NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    hwid VARCHAR(255) DEFAULT NULL,
    hwid_updated_at TIMESTAMP NULL,
    start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    payment_id VARCHAR(255),
    payment_method VARCHAR(50),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cheat_plan_id) REFERENCES cheat_subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subscription_id INT NOT NULL,
    transaction_id VARCHAR(255),
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'BRL',
    status ENUM('pending','completed','failed','refunded') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar índices para performance
CREATE INDEX idx_subscription_status ON user_subscriptions(status);
CREATE INDEX idx_subscription_dates ON user_subscriptions(start_date, end_date);
CREATE INDEX idx_cheat_plans_active ON cheat_subscription_plans(is_active);
CREATE INDEX idx_payments_status ON payments(status);

ALTER TABLE `cheats` 
ADD COLUMN `min_subscription_level` INT DEFAULT 1 
COMMENT 'Minimum subscription level required to access this cheat (1=Basic, 2=Premium, 3=VIP)';

-- Create the cheat_update_logs table
CREATE TABLE IF NOT EXISTS `cheat_update_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheat_id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `changes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cheat_id` (`cheat_id`),
  CONSTRAINT `cheat_update_logs_ibfk_1` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the cheat_features table
CREATE TABLE IF NOT EXISTS `cheat_features` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheat_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cheat_id` (`cheat_id`),
  CONSTRAINT `cheat_features_ibfk_1` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the cheat_system_requirements table
CREATE TABLE IF NOT EXISTS `cheat_system_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheat_id` int(11) NOT NULL,
  `requirement_type` varchar(50) NOT NULL, -- CPU, GPU, RAM, OS, etc.
  `minimum` varchar(100) NOT NULL,
  `recommended` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cheat_id` (`cheat_id`),
  CONSTRAINT `cheat_system_requirements_ibfk_1` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the cheat_screenshots table
CREATE TABLE IF NOT EXISTS `cheat_screenshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheat_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cheat_id` (`cheat_id`),
  CONSTRAINT `cheat_screenshots_ibfk_1` FOREIGN KEY (`cheat_id`) REFERENCES `cheats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Corrigir a tabela support_tickets
ALTER TABLE `support_tickets`
ADD COLUMN `ticket_id` VARCHAR(20) NOT NULL AFTER `id`,
ADD COLUMN `category` ENUM('technical','billing','account','other') NOT NULL DEFAULT 'technical' AFTER `message`;

-- Ajustar os índices
ALTER TABLE `support_tickets` 
ADD INDEX `idx_ticket_id` (`ticket_id`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_category` (`category`),
ADD INDEX `idx_priority` (`priority`);

-- Atualizar tickets existentes com IDs de ticket formatados
UPDATE `support_tickets`
SET `ticket_id` = CONCAT('TK-', LPAD(HEX(id), 8, '0'))
WHERE `ticket_id` = '';

-- Execute este script para adicionar a coluna ticket_id se ela não existir
ALTER TABLE `support_tickets` ADD COLUMN IF NOT EXISTS `ticket_id` VARCHAR(20) AFTER `id`;

-- Execute este script para adicionar a coluna category se ela não existir
ALTER TABLE `support_tickets` ADD COLUMN IF NOT EXISTS `category` ENUM('technical', 'billing', 'account', 'other') NOT NULL DEFAULT 'technical' AFTER `message`;

-- Preencher ticket_id para tickets existentes que não têm um ID formatado
UPDATE `support_tickets` SET `ticket_id` = CONCAT('TK-', LPAD(HEX(id), 8, '0')) WHERE `ticket_id` IS NULL OR `ticket_id` = '';

-- Execute esta consulta SQL para adicionar qualquer coluna que esteja faltando
ALTER TABLE `support_tickets` ADD COLUMN IF NOT EXISTS `ticket_id` VARCHAR(20) AFTER `id`;
ALTER TABLE `support_tickets` ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) DEFAULT 'technical' AFTER `message`;

ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER discord_id;

-- Execute esta SQL no phpMyAdmin ou em seu gerenciador de banco de dados
ALTER TABLE ticket_attachments ADD COLUMN admin_id INT NULL AFTER expires_at;

CREATE TABLE `tutorials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` enum('installation','troubleshooting') NOT NULL,
  `content` text NOT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;