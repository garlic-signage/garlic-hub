DELETE FROM user_acl WHERE module = 'system';
DROP TABLE `scheduler_runs`;
DROP INDEX idx_audit_created;
DROP TABLE `audit_log`;
DROP TABLE `unit_versions`;
