CREATE TABLE IF NOT EXISTS v2_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value JSON NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_workers (
  worker_id VARCHAR(100) PRIMARY KEY,
  platform VARCHAR(50) NOT NULL,
  preferred_role ENUM('discovery','scan','auto') NOT NULL,
  capacity ENUM('low','medium','high') NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  permissions JSON NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  max_runs_per_day SMALLINT UNSIGNED NULL,
  last_seen_at DATETIME NULL,
  last_action VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_common_crawl_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  crawl_id VARCHAR(80) NOT NULL,
  tld VARCHAR(32) NOT NULL,
  page_number INT UNSIGNED NOT NULL,
  status ENUM('pending','reserved','completed','failed','dead_letter') NOT NULL DEFAULT 'pending',
  reserved_by VARCHAR(100) NULL,
  reserved_until DATETIME NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rows_received INT UNSIGNED NOT NULL DEFAULT 0,
  root_domains_found INT UNSIGNED NOT NULL DEFAULT 0,
  domains_added INT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_v2_cc_job(crawl_id,tld,page_number),
  KEY idx_v2_cc_claim(status,reserved_until,created_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_domain_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  domain VARCHAR(253) NOT NULL,
  tld VARCHAR(32) NOT NULL,
  discovered_source VARCHAR(40) NOT NULL DEFAULT 'common_crawl',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  common_crawl_seen_count INT UNSIGNED NOT NULL DEFAULT 1,
  last_scanned_at DATETIME NULL,
  next_scan_at DATETIME NULL,
  reserved_by VARCHAR(100) NULL,
  reserved_until DATETIME NULL,
  scan_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_scan_status VARCHAR(40) NULL,
  UNIQUE KEY uq_v2_domain(domain),
  KEY idx_v2_scan_claim(last_scanned_at,next_scan_at,reserved_until,tld),
  KEY idx_v2_tld(tld)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_domains (
  domain VARCHAR(253) PRIMARY KEY,
  tld VARCHAR(32) NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  final_url TEXT NULL,
  default_language VARCHAR(12) NULL,
  selected_language VARCHAR(12) NULL,
  language_source VARCHAR(40) NULL,
  language_confidence DECIMAL(5,4) NULL,
  analysis_json JSON NULL,
  first_scanned_at DATETIME NOT NULL,
  last_scanned_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_v2_language(default_language),
  KEY idx_v2_updated(updated_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_worker_runs (
  run_id CHAR(36) PRIMARY KEY,
  worker_id VARCHAR(100) NOT NULL,
  action VARCHAR(40) NOT NULL,
  reason VARCHAR(100) NULL,
  capacity VARCHAR(16) NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  duration_ms INT UNSIGNED NULL,
  cc_pages INT UNSIGNED NOT NULL DEFAULT 0,
  domains_added INT UNSIGNED NOT NULL DEFAULT 0,
  domains_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  details JSON NULL,
  KEY idx_v2_worker_runs(worker_id,started_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_request_nonces (
  request_id CHAR(36) PRIMARY KEY,
  worker_id VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_v2_nonce_cleanup(created_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_mcp_clients (
  client_id VARCHAR(100) PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  permissions JSON NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_common_crawl_tld_state (
  crawl_id VARCHAR(80) NOT NULL,
  tld VARCHAR(32) NOT NULL,
  next_page INT UNSIGNED NOT NULL DEFAULT 0,
  total_pages INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY(crawl_id,tld),
  KEY idx_v2_cc_state(updated_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_rate_limits (
  bucket VARCHAR(160) NOT NULL,
  window_start DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(bucket,window_start),
  KEY idx_v2_rate_cleanup(window_start)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE v2_domains
 ADD COLUMN IF NOT EXISTS category VARCHAR(64) NOT NULL DEFAULT 'unidentified',
 ADD COLUMN IF NOT EXISTS category_status VARCHAR(24) NOT NULL DEFAULT 'unidentified',
 ADD COLUMN IF NOT EXISTS category_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS robots_status VARCHAR(24) NULL,
 ADD COLUMN IF NOT EXISTS robots_blocks_everything TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS ai_bot_policy JSON NULL,
 ADD COLUMN IF NOT EXISTS dependencies JSON NULL,
 ADD COLUMN IF NOT EXISTS provider_reference_version VARCHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS provider_reference_sha256 CHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS dependency_provider_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS dependency_red_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS dependency_tracking_role_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS consent_signals JSON NULL,
 ADD COLUMN IF NOT EXISTS consent_friction_score_auto TINYINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS consent_review_needed TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS consent_cmp_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS consent_tracking_provider_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS digital_identity_providers JSON NULL,
 ADD COLUMN IF NOT EXISTS review_platforms JSON NULL,
 ADD COLUMN IF NOT EXISTS social_presence JSON NULL,
 ADD COLUMN IF NOT EXISTS file_presence JSON NULL,
 ADD COLUMN IF NOT EXISTS tdm_reservation TINYINT(1) NULL,
 ADD COLUMN IF NOT EXISTS tdm_policy_url VARCHAR(500) NULL,
 ADD COLUMN IF NOT EXISTS generator VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS server_header VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL,
 ADD COLUMN IF NOT EXISTS ai_openness_score TINYINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS eu_sovereignty_score TINYINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS scan_engine_version VARCHAR(32) NULL,
 ADD COLUMN IF NOT EXISTS data_quality_status VARCHAR(24) NOT NULL DEFAULT 'inconclusive';
CREATE TABLE IF NOT EXISTS v2_scan_history(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,domain VARCHAR(253) NOT NULL,worker_id VARCHAR(100) NULL,scanned_at DATETIME NOT NULL,http_status SMALLINT UNSIGNED NULL,selected_language VARCHAR(12) NULL,category VARCHAR(64) NULL,data_quality_status VARCHAR(24) NOT NULL,engine_version VARCHAR(32) NOT NULL,result_json JSON NOT NULL,KEY idx_hist_domain(domain,scanned_at),KEY idx_hist_category(category,scanned_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS v2_report_statistics(section_key VARCHAR(100) PRIMARY KEY,payload JSON NOT NULL,sample_size BIGINT UNSIGNED NULL,calculated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE v2_scan_history
 ADD COLUMN IF NOT EXISTS response_truncated TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS analysis_complete TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN IF NOT EXISTS dependency_provider_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS consent_cmp_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS robots_status VARCHAR(24) NULL,
 ADD COLUMN IF NOT EXISTS eu_sovereignty_score TINYINT UNSIGNED NULL,
 ADD KEY IF NOT EXISTS idx_hist_quality(data_quality_status,scanned_at),
 ADD KEY IF NOT EXISTS idx_hist_worker(worker_id,scanned_at);

-- Alpha5 consolidée : parité V1 et dimensions de recherche versionnées.
ALTER TABLE v2_domains
 ADD COLUMN IF NOT EXISTS alternate_languages JSON NULL,
 ADD COLUMN IF NOT EXISTS country_code CHAR(2) NULL,
 ADD COLUMN IF NOT EXISTS eu_member TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS tld_type VARCHAR(32) NULL,
 ADD COLUMN IF NOT EXISTS tld_groups JSON NULL,
 ADD COLUMN IF NOT EXISTS redirect_status VARCHAR(16) NOT NULL DEFAULT 'none',
 ADD COLUMN IF NOT EXISTS redirect_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS category_source VARCHAR(48) NULL,
 ADD COLUMN IF NOT EXISTS category_signals JSON NULL,
 ADD COLUMN IF NOT EXISTS category_negative_signals JSON NULL,
 ADD COLUMN IF NOT EXISTS category_tier2 VARCHAR(96) NULL,
 ADD COLUMN IF NOT EXISTS category_tier2_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS category_tier2_signals JSON NULL,
 ADD COLUMN IF NOT EXISTS taxonomy_version VARCHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS unknown_dependencies JSON NULL,
 ADD COLUMN IF NOT EXISTS dependency_governance JSON NULL,
 ADD COLUMN IF NOT EXISTS file_misplaced JSON NULL,
 ADD COLUMN IF NOT EXISTS file_conflict JSON NULL,
 ADD COLUMN IF NOT EXISTS domain_for_sale TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS domain_for_sale_source VARCHAR(32) NULL,
 ADD COLUMN IF NOT EXISTS domain_for_sale_provider VARCHAR(120) NULL,
 ADD COLUMN IF NOT EXISTS accessibility_statement_present TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS legal_notice_present TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS has_json_ld TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS has_microdata TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS ssl_issuer VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS foodtruck_mentions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS access_status VARCHAR(24) NOT NULL DEFAULT 'inconclusive',
 ADD KEY IF NOT EXISTS idx_v2_country_category(country_code,category),
 ADD KEY IF NOT EXISTS idx_v2_category_tier2(category,category_tier2),
 ADD KEY IF NOT EXISTS idx_v2_sale(domain_for_sale),
 ADD KEY IF NOT EXISTS idx_v2_access(access_status,data_quality_status);

ALTER TABLE v2_scan_history
 ADD COLUMN IF NOT EXISTS category_tier2 VARCHAR(96) NULL,
 ADD COLUMN IF NOT EXISTS category_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS redirect_status VARCHAR(16) NOT NULL DEFAULT 'none',
 ADD COLUMN IF NOT EXISTS redirect_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS domain_for_sale TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS access_status VARCHAR(24) NOT NULL DEFAULT 'inconclusive',
 ADD COLUMN IF NOT EXISTS taxonomy_version VARCHAR(64) NULL,
 ADD KEY IF NOT EXISTS idx_hist_tier2(category,category_tier2,scanned_at),
 ADD KEY IF NOT EXISTS idx_hist_sale(domain_for_sale,scanned_at);

CREATE TABLE IF NOT EXISTS v2_reference_versions (
 reference_key VARCHAR(80) PRIMARY KEY,
 reference_version VARCHAR(120) NOT NULL,
 sha256 CHAR(64) NULL,
 metadata JSON NULL,
 installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE v2_domains
 ADD COLUMN IF NOT EXISTS hosting_provider VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS hosting_asn BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS hosting_eu_status VARCHAR(16) NULL,
 ADD KEY IF NOT EXISTS idx_v2_hosting(hosting_provider),
 ADD KEY IF NOT EXISTS idx_v2_hosting_status(hosting_eu_status);
ALTER TABLE v2_scan_history
 ADD COLUMN IF NOT EXISTS hosting_provider VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS hosting_asn BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS hosting_eu_status VARCHAR(16) NULL;
ALTER TABLE v2_common_crawl_jobs
 ADD COLUMN IF NOT EXISTS next_attempt_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS error_type VARCHAR(40) NULL,
 ADD KEY IF NOT EXISTS idx_v2_cc_retry(status,next_attempt_at,attempts);

-- Contrôle de coût par worker (utile pour les plateformes payantes comme
-- Render) : plafond de runs/jour, vérifié dans api/run-start.php avant de
-- distribuer du travail. NULL = illimité (comportement par défaut inchangé).
ALTER TABLE v2_workers
 ADD COLUMN IF NOT EXISTS max_runs_per_day SMALLINT UNSIGNED NULL;

-- Taux de disparition / "mortalité numérique" (repris de V1, absent de la
-- consolidation alpha4/5) : unreachable_streak compte les échecs consécutifs
-- de résolution (mis à 0 dès qu'une réponse arrive, même mauvaise),
-- first_unreachable_at marque le début de la série en cours, et
-- confirmed_unreachable ne passe à 1 qu'après un double seuil : au moins 3
-- échecs consécutifs ET au moins 7 jours écoulés depuis le premier échec de
-- la série — voir updateUnreachableTracking() dans api/scan-ingest.php.
ALTER TABLE v2_domains
 ADD COLUMN IF NOT EXISTS unreachable_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS first_unreachable_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS confirmed_unreachable TINYINT(1) NOT NULL DEFAULT 0,
 ADD KEY IF NOT EXISTS idx_v2_unreachable(confirmed_unreachable,unreachable_streak);
