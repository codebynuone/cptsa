-- Run this if admin login (admin/admin123) doesn't work after importing schema.sql
-- This updates the admin password hash to the correct bcrypt of 'admin123'
USE cptsa_driving;

UPDATE admins 
SET password_hash = '$2y$10$Azz0G0aYcdJupdN0IVavCeC2M39GIH00xbBS/d6NDdOHE1r0he3jG'
WHERE username = 'admin';

SELECT 'Admin password reset to admin123' AS result;
