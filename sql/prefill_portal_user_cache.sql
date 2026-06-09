-- Seed portal_user_cache with all currently non-expired members from the
-- portal database. Idempotent — safe to run multiple times to refresh stale rows.
INSERT INTO portal_user_cache
    (ww_number, first_name, last_name, email, membership_expiration, cached_at)
SELECT
    membershipNumber,
    firstName,
    lastName,
    emailAddress,
    membershipExpiration,
    NOW()
FROM `mes-portal`.User
WHERE membershipExpiration > NOW()
ON DUPLICATE KEY UPDATE
    first_name            = VALUES(first_name),
    last_name             = VALUES(last_name),
    email                 = VALUES(email),
    membership_expiration = VALUES(membership_expiration),
    cached_at             = NOW();
