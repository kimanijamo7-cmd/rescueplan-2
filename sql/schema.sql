-- Beneficiary Record Deduplication System
-- MySQL / MariaDB schema (XAMPP). Mirrors the ER diagram in Figure 2.3.4.


CREATE TABLE IF NOT EXISTS users (
    user_id         INT          AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    username        VARCHAR(60)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'field_officer'
                       CHECK (role IN ('administrator', 'field_officer')),
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    failed_attempts INT          NOT NULL DEFAULT 0,
    date_created    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS beneficiaries (
    beneficiary_id  INT          AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    national_id     VARCHAR(20)  NOT NULL,
    household_size  INT          NOT NULL CHECK (household_size > 0),
    camp_location   VARCHAR(120) NOT NULL,
    phone           VARCHAR(30),
    record_status   VARCHAR(25)  NOT NULL DEFAULT 'pending_review'
                       CHECK (record_status IN ('pending_review', 'verified',
                                                'duplicate_flagged', 'merged_inactive')),
    notes           TEXT,
    registered_by   INT,
    date_registered DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_updated    DATETIME NULL,
    CONSTRAINT fk_beneficiary_user FOREIGN KEY (registered_by)
        REFERENCES users (user_id) ON DELETE SET NULL,
    KEY idx_beneficiary_name (full_name),
    KEY idx_beneficiary_nid (national_id),
    KEY idx_beneficiary_camp (camp_location)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS match_clusters (
    cluster_id       INT           AUTO_INCREMENT PRIMARY KEY,
    similarity_score DECIMAL(6, 4) NOT NULL,
    match_status     VARCHAR(25)   NOT NULL DEFAULT 'pending'
                         CHECK (match_status IN ('pending', 'confirmed_duplicate', 'false_positive')),
    reviewed_by      INT NULL,
    date_created     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_reviewed    DATETIME NULL,
    CONSTRAINT fk_cluster_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS cluster_members (
    member_id      INT AUTO_INCREMENT PRIMARY KEY,
    cluster_id     INT NOT NULL,
    beneficiary_id INT NOT NULL,
    UNIQUE KEY uq_cluster_member (cluster_id, beneficiary_id),
    CONSTRAINT fk_member_cluster FOREIGN KEY (cluster_id)
        REFERENCES match_clusters (cluster_id) ON DELETE CASCADE,
    CONSTRAINT fk_member_beneficiary FOREIGN KEY (beneficiary_id)
        REFERENCES beneficiaries (beneficiary_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    log_id     INT         AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    action     VARCHAR(60) NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45),
    logged_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE SET NULL,
    KEY idx_audit_action (action)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS reports (
    report_id     INT          AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NULL,
    report_type   VARCHAR(40)  NOT NULL,
    camp_location VARCHAR(120),
    date_from     VARCHAR(20),
    date_to       VARCHAR(20),
    payload_json  LONGTEXT     NOT NULL,
    generated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE = InnoDB;
