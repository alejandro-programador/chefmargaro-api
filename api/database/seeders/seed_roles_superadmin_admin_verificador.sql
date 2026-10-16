-- =============================================
-- Seed roles: superadmin, admin, verificador
-- Run after seed_permissions_menu.sql so permissions exist.
-- user_roles: ensure role_name is UNIQUE. If table has no updated_at, remove it from INSERT.
-- role_permission: pivot (role_id, permission_id); use UNIQUE(role_id, permission_id) for INSERT IGNORE.
-- =============================================
-- superadmin: all permissions, sees all branches (no branch filter).
-- admin:      all permissions, data filtered by assigned branch (user_branch_access).
-- verificador: only orders, payments, customers; data filtered by assigned branch.
-- =============================================

-- 1) Insert the three roles (INSERT IGNORE: skip if role_name already exists)
-- If your user_roles table has no updated_at column, use only (role_name, description, created_at).
INSERT IGNORE INTO user_roles (role_name, description, created_at, updated_at) VALUES
('superadmin', 'Full access to all data across all branches.', NOW(), NOW()),
('admin', 'Full permissions; data restricted to the branch assigned to the user.', NOW(), NOW()),
('verificador', 'Can view orders, payments, and customers; data restricted to assigned branch.', NOW(), NOW());

-- 1b) Fill permissions column (JSON string) in user_roles: superadmin and admin = all slugs; verificador = orders, payments, customers
UPDATE user_roles SET permissions = '["dashboard","orders","payments","branches","logs","extras","customers","combos","users","permissions","user_roles"]'
WHERE role_name IN ('superadmin', 'admin');

UPDATE user_roles SET permissions = '["orders","payments","customers"]'
WHERE role_name = 'verificador';

-- 2) Assign ALL permissions to superadmin
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_name = 'superadmin';

-- 3) Assign ALL permissions to admin
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_name = 'admin';

-- 4) Assign only orders, payments, customers to verificador (by slug)
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
INNER JOIN permissions p ON p.slug IN ('orders', 'payments', 'customers')
WHERE r.role_name = 'verificador';
