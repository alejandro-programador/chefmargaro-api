# Chef Margaro API

API RESTful desarrollada con Laravel para el sistema de gestión de Chef Margaro.

## Requisitos

- PHP >= 8.2
- Composer
- MySQL/MariaDB
- XAMPP (o servidor web con Apache)

## Instalación

1. Clonar o descargar el proyecto en `C:\xampp\htdocs\api`

2. Instalar dependencias:
```bash
composer install
```

3. Configurar el archivo `.env`:
```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chef_margaro
DB_USERNAME=root
DB_PASSWORD=
```

4. Importar la base de datos:
   - Importar el archivo `chef_margaro.sql` en phpMyAdmin o MySQL

5. Ejecutar las migraciones (opcional, si quieres usar las migraciones en lugar del SQL):
```bash
php artisan migrate
```

6. Ejecutar los seeders para llenar con datos de prueba:
```bash
php artisan db:seed
```

7. Crear el enlace simbólico para almacenamiento público:
```bash
php artisan storage:link
```

## Estructura de la API

### Base URL
- Desarrollo: `http://127.0.0.1:8000/api/v1`
- XAMPP/Apache: `http://localhost/api/public/api/v1`

### Endpoints Principales

#### Branches (Sucursales)
- `GET /api/v1/branches` - Listar todas las sucursales
- `POST /api/v1/branches` - Crear sucursal
- `GET /api/v1/branches/{id}` - Obtener sucursal
- `PUT /api/v1/branches/{id}` - Actualizar sucursal
- `DELETE /api/v1/branches/{id}` - Eliminar sucursal

#### Customers (Clientes)
- `GET /api/v1/customers` - Listar clientes
- `POST /api/v1/customers` - Crear cliente
- `GET /api/v1/customers/{id}` - Obtener cliente
- `PUT /api/v1/customers/{id}` - Actualizar cliente
- `DELETE /api/v1/customers/{id}` - Eliminar cliente

#### Products (Productos)
- `GET /api/v1/products` - Listar productos
- `POST /api/v1/products` - Crear producto (soporta imagen)
- `GET /api/v1/products/{id}` - Obtener producto
- `PUT /api/v1/products/{id}` - Actualizar producto
- `DELETE /api/v1/products/{id}` - Eliminar producto
- `PUT /api/v1/products/{id}/extras` - Sincronizar extras del producto

#### Extras
- `GET /api/v1/extras` - Listar extras
- `POST /api/v1/extras` - Crear extra (soporta imagen)
- `GET /api/v1/extras/{id}` - Obtener extra
- `PUT /api/v1/extras/{id}` - Actualizar extra
- `DELETE /api/v1/extras/{id}` - Eliminar extra

#### Combos
- `GET /api/v1/combos` - Listar combos
- `POST /api/v1/combos` - Crear combo (soporta imagen)
- `GET /api/v1/combos/{id}` - Obtener combo
- `PUT /api/v1/combos/{id}` - Actualizar combo
- `DELETE /api/v1/combos/{id}` - Eliminar combo
- `PUT /api/v1/combos/{id}/extras` - Sincronizar extras del combo

#### Orders (Órdenes)
- `GET /api/v1/orders` - Listar órdenes
- `POST /api/v1/orders` - Crear orden
- `GET /api/v1/orders/{id}` - Obtener orden
- `PUT /api/v1/orders/{id}` - Actualizar orden
- `DELETE /api/v1/orders/{id}` - Eliminar orden

#### Payments (Pagos)
- `GET /api/v1/payments` - Listar pagos
- `POST /api/v1/payments` - Crear pago
- `GET /api/v1/payments/{id}` - Obtener pago
- `PUT /api/v1/payments/{id}` - Actualizar pago
- `DELETE /api/v1/payments/{id}` - Eliminar pago

#### Payment Verifications (Verificaciones de Pago)
- `GET /api/v1/payment-verifications` - Listar verificaciones
- `POST /api/v1/payment-verifications` - Crear verificación
- `GET /api/v1/payment-verifications/{id}` - Obtener verificación
- `PUT /api/v1/payment-verifications/{id}` - Actualizar verificación
- `DELETE /api/v1/payment-verifications/{id}` - Eliminar verificación

#### Users (Usuarios)
- `GET /api/v1/users` - Listar usuarios
- `POST /api/v1/users` - Crear usuario
- `GET /api/v1/users/{id}` - Obtener usuario
- `PUT /api/v1/users/{id}` - Actualizar usuario
- `DELETE /api/v1/users/{id}` - Eliminar usuario

#### User Roles (Roles de Usuario)
- `GET /api/v1/user-roles` - Listar roles
- `POST /api/v1/user-roles` - Crear rol
- `GET /api/v1/user-roles/{id}` - Obtener rol
- `PUT /api/v1/user-roles/{id}` - Actualizar rol
- `DELETE /api/v1/user-roles/{id}` - Eliminar rol
- `PUT /api/v1/user-roles/{id}/permissions` - Sincronizar permisos del rol

#### User Branch Access (Acceso de Usuario a Sucursal)
- `GET /api/v1/user-branch-access` - Listar accesos
- `POST /api/v1/user-branch-access` - Crear acceso
- `GET /api/v1/user-branch-access/{id}` - Obtener acceso
- `PUT /api/v1/user-branch-access/{id}` - Actualizar acceso
- `DELETE /api/v1/user-branch-access/{id}` - Eliminar acceso

#### Logs (Registros - Solo lectura)
- `GET /api/v1/logs` - Listar logs
- `GET /api/v1/logs/{id}` - Obtener log

#### Permissions (Permisos)
- `GET /api/v1/permissions` - Listar permisos
- `POST /api/v1/permissions` - Crear permiso
- `GET /api/v1/permissions/{id}` - Obtener permiso
- `PUT /api/v1/permissions/{id}` - Actualizar permiso
- `DELETE /api/v1/permissions/{id}` - Eliminar permiso

## Características

- ✅ CRUD completo para todos los recursos
- ✅ Paginación en todos los endpoints de listado
- ✅ Búsqueda y filtrado avanzado
- ✅ Validación de datos con Form Requests
- ✅ Transformación de respuestas con API Resources
- ✅ Soporte para carga de imágenes (productos, combos, extras)
- ✅ Relaciones entre modelos cargadas bajo demanda
- ✅ Seeders con datos de prueba
- ✅ Código optimizado y siguiendo buenas prácticas

## Datos de Prueba

Los seeders incluyen:
- 3 sucursales
- 5 clientes
- 5 productos
- 5 extras
- 4 combos
- 3 órdenes
- 3 pagos
- 3 usuarios (admin, verifier, manager)
- 3 roles
- 5 permisos

## Notas

- Las imágenes se almacenan en `storage/app/public/`
- Los endpoints devuelven respuestas JSON estructuradas
- Todas las fechas están en formato ISO 8601
- Los valores monetarios son decimales con 2 decimales

## Desarrollo

Para ejecutar el servidor de desarrollo:
```bash
php artisan serve
```

Para ejecutar los seeders:
```bash
php artisan db:seed
```

Para limpiar la caché:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```
