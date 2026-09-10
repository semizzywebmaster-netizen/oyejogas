-- Oyejo Gas - Phase 3 read-only verification queries.
-- Usage: mysql oyejo_verify < tests/phase3-verify.sql
-- (Import database/schema.sql into oyejo_verify first.)

SELECT COUNT(*) AS tables_expected_60
FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();

SELECT COUNT(*) AS foreign_keys
FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE();

SELECT COUNT(*) AS unique_constraints
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'UNIQUE';

SELECT TRIGGER_NAME AS triggers, EVENT_MANIPULATION AS on_event
FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();

SELECT TABLE_NAME AS check_table, CONSTRAINT_NAME AS check_name
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK';

-- Every FK must point at an existing unique/primary key (structural report):
SELECT TABLE_NAME AS tbl, CONSTRAINT_NAME AS fk_name,
       GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS cols,
       REFERENCED_TABLE_NAME AS ref_tbl
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
GROUP BY TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
