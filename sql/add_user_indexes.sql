-- Add missing indexes to users table.
-- ww_number is used in the cross-DB JOIN to mes-portal and in search.
-- org_id is used in the JOIN to organizations.
ALTER TABLE users ADD INDEX idx_ww_number (ww_number);
ALTER TABLE users ADD INDEX idx_org_id    (org_id);
