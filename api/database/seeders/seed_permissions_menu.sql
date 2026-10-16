-- =============================================
-- Permisos por sección del menú (admin panel)
-- Una fila por cada sección: Dashboard, Órdenes, Pagos, Sucursales, Logs, etc.
-- Ejecutar sobre la base de datos (MySQL/MariaDB).
-- Si existe UNIQUE en slug, es idempotente (re-ejecutable sin duplicar).
-- =============================================
-- Tabla: permissions (permission_id, name, slug, description, created_at, updated_at)

INSERT INTO permissions (name, slug, description, created_at, updated_at) VALUES
('Dashboard', 'dashboard', 'Acceso al panel principal / resumen', NOW(), NOW()),
('Órdenes', 'orders', 'Acceso a la sección de órdenes', NOW(), NOW()),
('Pagos', 'payments', 'Acceso a la sección de pagos', NOW(), NOW()),
('Sucursales', 'branches', 'Acceso a la sección de sucursales', NOW(), NOW()),
('Logs', 'logs', 'Acceso a la sección de logs del sistema', NOW(), NOW()),
('Extras', 'extras', 'Acceso a la sección de extras', NOW(), NOW()),
('Clientes', 'customers', 'Acceso a la sección de clientes', NOW(), NOW()),
('Combos', 'combos', 'Acceso a la sección de combos', NOW(), NOW()),
('Usuarios', 'users', 'Acceso a la sección de usuarios', NOW(), NOW()),
('Permisos', 'permissions', 'Acceso a la sección de permisos', NOW(), NOW()),
('Roles', 'user_roles', 'Acceso a la sección de roles de usuario', NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), updated_at = NOW();
