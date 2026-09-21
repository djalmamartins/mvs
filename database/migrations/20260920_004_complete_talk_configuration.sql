CREATE TABLE IF NOT EXISTS talk_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT talk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    config JSON NULL,
    last_connected_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY talk_channels_type_name (type,name)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_settings(setting_key,setting_value) VALUES
('jack.enabled','0'),
('jack.wait_seconds','60'),
('jack.transfer_summary','1'),
('auto_assign.enabled','1'),
('auto_assign.default_seconds','30');

INSERT IGNORE INTO talk_channels(type,name,status,config) VALUES
('simulation','Simulação','active',JSON_OBJECT('isolated',true)),
('whatsapp','WhatsApp','inactive',JSON_OBJECT('transport','baileys'));
