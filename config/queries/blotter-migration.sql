-- ============================================================
-- Blotter System Migration
-- Run this once against your barangay853 database
-- ============================================================

ALTER TABLE blotter_cases

  -- Evidence tracking
  ADD COLUMN IF NOT EXISTS has_evidence          TINYINT(1)   NOT NULL DEFAULT 0
      COMMENT '1 if reporter submitted evidence',

  ADD COLUMN IF NOT EXISTS evidence_cctv         TINYINT(1)   NOT NULL DEFAULT 0
      COMMENT '1 if CCTV/video footage was cited',

  ADD COLUMN IF NOT EXISTS evidence_eyewitnesses TEXT         NULL
      COMMENT 'Semicolon-separated eyewitness names + contacts',

  ADD COLUMN IF NOT EXISTS evidence_other        TEXT         NULL
      COMMENT 'Other evidence description (photos, medical cert, etc.)',

  ADD COLUMN IF NOT EXISTS evidence_summary      TEXT         NULL
      COMMENT 'Compiled evidence summary string',

  -- Summons tracking
  ADD COLUMN IF NOT EXISTS summon_count          INT          NOT NULL DEFAULT 0
      COMMENT 'How many summons have been issued for this case',

  -- Staff testimony / follow-up notes log (JSON array)
  ADD COLUMN IF NOT EXISTS testimony_log         LONGTEXT     NULL
      COMMENT 'JSON array: [{date, by, note}] — staff appends notes over time';

-- ============================================================
-- Status reference (no ENUM change needed — stored as VARCHAR)
-- Possible status values after this update:
--   Pending              → no evidence, awaiting review
--   For Summons          → evidence submitted, not yet summoned
--   1st Summon Issued    → first summon sent
--   2nd Summon Issued    → unresolved after 1st
--   3rd Summon Issued    → unresolved after 2nd
--   Referred to Police   → still unresolved after 3rd summon;
--                          barangay jurisdiction exhausted,
--                          case forwarded to PNP or higher authority
--   Resolved             → case settled
--   Escalated            → referred to higher authority
--   Dismissed            → insufficient grounds / withdrawn
-- ============================================================


-- Budget update for sql
SELECT annual_budget FROM budget_config 
ORDER BY updated_at DESC, fiscal_year DESC 
LIMIT 1