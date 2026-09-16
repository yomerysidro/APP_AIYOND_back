# Roles and Supply: manual API checks

Las rutas actuales conservan `list`, `create`, `update` y `delete`.

## Primer administrador

Se comprobó únicamente el conteo de administradores en MySQL: 0. Revisa manualmente el usuario que deseas promover y sustituye `123` por su ID real:

```sql
SELECT id_usuario, nombre, correo, rol FROM usuarios WHERE id_usuario = 123;

UPDATE usuarios SET rol = 'admin' WHERE id_usuario = 123;
```

Este SQL no fue ejecutado automáticamente. No hay registro público de administradores.

## Login

`POST http://127.0.0.1:8000/api/auth/login`

```json
{
  "correo": "correo-de-tu-cuenta",
  "password": "contraseña-de-tu-cuenta"
}
```

Usa `data.access_token` en las siguientes solicitudes:

```text
Authorization: Bearer {ADMIN_TOKEN}
Accept: application/json
Content-Type: application/json
```

## Create — admin (201)

`POST http://127.0.0.1:8000/api/supplies/create`

```json
{
  "nombre_insumo": "Oxicloruro de cobre",
  "tipo_insumo": "Fungicida",
  "unidad_medida": "kg",
  "costo_unitario": 24.50,
  "descripcion": "Insumo agrícola"
}
```

Este ejemplo fue proporcionado para verificar el formato; no es una recomendación de uso agrícola. Guarda `data.id_insumo` y úsalo en lugar de 1.

## Read — user or admin (200)

```text
GET http://127.0.0.1:8000/api/supplies/list
GET http://127.0.0.1:8000/api/supplies/list/1
```

## Patch — admin (200)

`PATCH http://127.0.0.1:8000/api/supplies/update/1`

```json
{
  "costo_unitario": 25.00
}
```

## Put — admin (200)

`PUT http://127.0.0.1:8000/api/supplies/update/1`

```json
{
  "nombre_insumo": "Oxicloruro de cobre",
  "tipo_insumo": "Fungicida",
  "unidad_medida": "kg",
  "costo_unitario": 26.00,
  "descripcion": "Insumo agrícola actualizado"
}
```

## Delete — admin

`DELETE http://127.0.0.1:8000/api/supplies/delete/1`

Sin body. Esperado: 200; 404 si no existe; 409 si MySQL impide eliminarlo por una relación existente. No se fuerza la eliminación.

## Authorization checks

- Sin JWT: 401 en rutas protegidas.
- JWT de usuario normal: lecturas Supply permitidas; escrituras Supply y todo User CRUD devuelven 403.
- JWT admin: User CRUD y Supply CRUD permitidos, sujetos a validaciones.
- Roles permitidos en POST/PUT User: `user` y `admin`, obligatorios. PATCH permite omitir el rol, pero si se envía solo acepta esos valores.
- POST `/api/auth/register` con `rol: admin` sigue creando un usuario con `rol: user`.
- `/api/auth/refresh` mantiene la renovación del JWT expirado dentro de su ventana; no exige pasar por `auth:api` antes del refresh.

Rutas User administrativas:

```text
GET    /api/users/list
GET    /api/users/list/{id}
POST   /api/users/create
PUT    /api/users/update/{id}
PATCH  /api/users/update/{id}
DELETE /api/users/delete/{id}
```

Disease permanece de lectura: `/api/diseases/list` y `/api/diseases/list/{id}`. Plot y Sector conservan sus rutas y el aislamiento por propietario, también para administradores.

## Automated tests

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

Las pruebas utilizan SQLite en memoria únicamente durante testing. No cambian `.env` ni escriben en MySQL. El CRUD manual y la restricción de eliminación sobre MySQL real quedan pendientes.
