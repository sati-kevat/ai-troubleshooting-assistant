-- =====================================================================
-- AI Troubleshooting Assistant for L1/L2 Support - Database schema
-- Target: MySQL 5.7+ / MariaDB 10.4+ (XAMPP)
--
-- Safe to re-run: uses CREATE ... IF NOT EXISTS and never drops data.
-- Import order: 01_schema.sql -> 02_seed_knowledge_base.sql
--               -> (optional) 03_sample_queries.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS troubleshoot_assistant
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE troubleshoot_assistant;

-- ---------------------------------------------------------------------
-- Issue categories. Add a row here to introduce a new category.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(60)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Knowledge-base articles.
-- Multi-line fields (symptoms, causes, steps, escalation_conditions)
-- store one item per line. keywords is a comma-separated list; multi-word
-- keywords (e.g. "charged twice") are matched as phrases.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kb_articles (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_code          VARCHAR(20)  NOT NULL,
  title                 VARCHAR(200) NOT NULL,
  category_id           INT UNSIGNED NOT NULL,
  symptoms              TEXT         NOT NULL,
  causes                TEXT         NOT NULL,
  steps                 TEXT         NOT NULL,
  escalation_conditions TEXT         NOT NULL,
  escalate_to           VARCHAR(100) NOT NULL,
  keywords              VARCHAR(500) NOT NULL,
  is_fictional          TINYINT(1)   NOT NULL DEFAULT 1,
  is_active             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kb_articles_code (article_code),
  KEY idx_kb_articles_category (category_id),
  CONSTRAINT fk_kb_articles_category
    FOREIGN KEY (category_id) REFERENCES categories (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Support queries submitted by L1/L2 agents and their outcomes.
-- status starts as 'open'; only an agent action changes it, so the
-- system never marks an issue resolved without the agent confirming it.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_queries (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_text             TEXT         NOT NULL,
  category_id            INT UNSIGNED NULL,
  category_source        ENUM('user','detected','none') NOT NULL DEFAULT 'none',
  summary                VARCHAR(500) NOT NULL,
  confidence             ENUM('high','medium','low','none') NOT NULL,
  top_score              DECIMAL(7,2) NOT NULL DEFAULT 0,
  answer_mode            ENUM('demo','ai') NOT NULL,
  ai_model               VARCHAR(100) NULL,
  response_json          MEDIUMTEXT   NOT NULL,
  escalation_recommended TINYINT(1)   NOT NULL DEFAULT 0,
  status                 ENUM('open','resolved','unresolved','escalated') NOT NULL DEFAULT 'open',
  status_note            VARCHAR(500) NULL,
  status_updated_at      DATETIME     NULL,
  is_sample              TINYINT(1)   NOT NULL DEFAULT 0,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_queries_status (status),
  KEY idx_queries_category (category_id),
  KEY idx_queries_created (created_at),
  CONSTRAINT fk_queries_category
    FOREIGN KEY (category_id) REFERENCES categories (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Knowledge-base articles retrieved for each query (the cited sources).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS query_sources (
  query_id      INT UNSIGNED     NOT NULL,
  article_id    INT UNSIGNED     NOT NULL,
  rank_position TINYINT UNSIGNED NOT NULL,
  score         DECIMAL(7,2)     NOT NULL,
  PRIMARY KEY (query_id, article_id),
  KEY idx_sources_article (article_id),
  CONSTRAINT fk_sources_query
    FOREIGN KEY (query_id) REFERENCES support_queries (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_sources_article
    FOREIGN KEY (article_id) REFERENCES kb_articles (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
