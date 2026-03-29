USE csms;
UPDATE users SET password='$2y$10$oKkCb1Mz1PlQK50vOCMbBOocEElNHMbqnA6cxx5UWdd5Y38yfxqk6' WHERE email='admin@csms.local';
SELECT email, is_verified, role FROM users;
