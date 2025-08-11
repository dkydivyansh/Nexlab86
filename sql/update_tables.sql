-- Update users table to add email verification fields
ALTER TABLE users
    ADD COLUMN verification_token VARCHAR(64) AFTER status,
    ADD COLUMN verification_expires TIMESTAMP NULL AFTER verification_token,
    ADD COLUMN verified_at TIMESTAMP NULL AFTER verification_expires,
    MODIFY COLUMN status ENUM('active', 'deactivated', 'pending') DEFAULT 'pending',
    ADD INDEX idx_verification_token (verification_token);

-- Update existing users to active status
UPDATE users SET status = 'active' WHERE status = 'pending' AND verified_at IS NULL; 