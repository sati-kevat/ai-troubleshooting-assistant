-- =====================================================================
-- OPTIONAL sample query history - FICTIONAL DEMONSTRATION RECORDS
--
-- Import this only if you want the History and Dashboard pages to show
-- data before any real query has been submitted. Every row is flagged
-- is_sample = 1, the UI labels these rows as "Sample", and the dashboard
-- can exclude them.
--
-- Re-running this file first removes previous sample rows
-- (is_sample = 1 only); real queries are never touched.
-- Requires 01_schema.sql and 02_seed_knowledge_base.sql.
-- =====================================================================

USE troubleshoot_assistant;

DELETE FROM support_queries WHERE is_sample = 1;

DROP TEMPORARY TABLE IF EXISTS sample_seed;
CREATE TEMPORARY TABLE sample_seed (
  issue_text     TEXT,
  article_code   VARCHAR(20),
  confidence     VARCHAR(10),
  top_score      DECIMAL(7,2),
  escalation     TINYINT(1),
  status         VARCHAR(12),
  days_ago       INT,
  hours_ago      INT
);

INSERT INTO sample_seed VALUES
('Terminal shows host not reachable since morning, wifi is working on phone', 'KB-001', 'high',   14.20, 0, 'resolved',   6, 3),
('Customer card declined with code 51 but customer says balance is available', 'KB-002', 'high',   12.80, 0, 'resolved',   6, 1),
('Printer prints blank receipts after changing the paper roll',               'KB-004', 'high',   15.10, 0, 'resolved',   5, 5),
('UPI payment debited from customer account but terminal shows pending',      'KB-005', 'high',   16.40, 0, 'escalated',  4, 2),
('Settlement for yesterday not credited to merchant bank account',            'KB-006', 'high',   13.70, 0, 'unresolved', 3, 6),
('Merchant cannot log in to portal, OTP not received on mobile',              'KB-008', 'high',   14.90, 0, 'resolved',   3, 2),
('API returning 401 invalid signature in production only',                    'KB-009', 'high',   15.60, 0, 'open',       2, 4),
('Terminal stuck on logo after software update last night',                   'KB-012', 'high',   12.30, 0, 'escalated',  1, 3),
('Customer charged twice for one purchase after timeout',                      'KB-015', 'medium',  8.40, 0, 'open',       1, 1),
('Merchant asking about GST invoice format for their monthly statement',      NULL,     'none',    0.00, 1, 'escalated',  0, 2);

INSERT INTO support_queries
  (issue_text, category_id, category_source, summary, confidence, top_score,
   answer_mode, ai_model, response_json, escalation_recommended, status,
   status_note, status_updated_at, is_sample, created_at, updated_at)
SELECT
  s.issue_text,
  a.category_id,
  IF(a.id IS NULL, 'none', 'detected'),
  IF(a.id IS NULL,
     'Sample record: no matching knowledge-base article.',
     CONCAT('Sample record: best match ', a.article_code, ' - ', a.title)),
  s.confidence,
  s.top_score,
  'demo',
  NULL,
  '{"sample":true,"note":"Fictional sample history record imported from 03_sample_queries.sql. No answer was generated for it."}',
  IF(a.id IS NULL, 1, s.escalation),
  s.status,
  IF(s.status = 'open', NULL, 'Sample outcome'),
  IF(s.status = 'open', NULL,
     DATE_SUB(NOW(), INTERVAL (s.days_ago * 24 + s.hours_ago - 1) HOUR)),
  1,
  DATE_SUB(NOW(), INTERVAL (s.days_ago * 24 + s.hours_ago) HOUR),
  DATE_SUB(NOW(), INTERVAL (s.days_ago * 24 + s.hours_ago) HOUR)
FROM sample_seed s
LEFT JOIN kb_articles a ON a.article_code = s.article_code;

INSERT INTO query_sources (query_id, article_id, rank_position, score)
SELECT q.id, a.id, 1, q.top_score
FROM support_queries q
JOIN sample_seed s ON s.issue_text = q.issue_text
JOIN kb_articles a ON a.article_code = s.article_code
WHERE q.is_sample = 1;

DROP TEMPORARY TABLE IF EXISTS sample_seed;
