# Sector: comprobación manual con Postman y MySQL

Estas peticiones no fueron ejecutadas automáticamente contra MySQL. Sustituye los identificadores por los devueltos por tu backend y utiliza una parcela del usuario autenticado con superficie disponible.

## Login

`POST http://127.0.0.1:8000/api/auth/login`

Headers: `Accept: application/json`, `Content-Type: application/json`.

```json
{
  "correo": "yomer@aiyond.com",
  "password": "12345678"
}
```

Utiliza las credenciales de tu cuenta existente. Copia `data.access_token` de la respuesta. En todas las peticiones siguientes configura:

```text
Authorization: Bearer {TOKEN}
Accept: application/json
Content-Type: application/json
```

## Crear sector (201)

`POST http://127.0.0.1:8000/api/sectors/create/1`

```json
{
  "nombre_sector": "Sector Norte",
  "superficie_hectareas": 3.50,
  "descripcion": "Zona norte de la parcela",
  "ubicacion": "Parte norte"
}
```

No envíes `id_parcela`, `id_usuario`, `id_sector` ni `fecha_registro`. La parcela se toma de la URL y se comprueba su propietario contra el JWT. Guarda `data.id_sector`; en los ejemplos siguientes se supone que es 1.

## Consultar (200)

```text
GET http://127.0.0.1:8000/api/sectors/by-plot/1
GET http://127.0.0.1:8000/api/sectors/list
GET http://127.0.0.1:8000/api/sectors/list/1
```

## Actualizar completo (200)

`PUT http://127.0.0.1:8000/api/sectors/update/1`

```json
{
  "nombre_sector": "Sector Norte Principal",
  "superficie_hectareas": 3.75,
  "descripcion": "Zona norte actualizada",
  "ubicacion": "Parte norte"
}
```

## Actualizar parcialmente (200)

`PATCH http://127.0.0.1:8000/api/sectors/update/1`

```json
{
  "superficie_hectareas": 4.00
}
```

## Comprobar aislamiento antes de eliminar

Inicia sesión con otro usuario y utiliza su token. GET sobre `/api/sectors/list/1`, PUT y PATCH sobre `/api/sectors/update/1`, y DELETE sobre `/api/sectors/delete/1` deben devolver **404**, al igual que GET sobre `/api/sectors/by-plot/1` y POST sobre `/api/sectors/create/1`. Para PUT/POST envía un body válido como los anteriores. `/api/sectors/list` solamente debe listar sectores del segundo usuario.

Sin token, con token inválido o expirado: **401** en las siete rutas.

## Comprobar hectáreas

En una parcela propia vacía de 10 ha, crea sectores de 3, 4 y 3 ha: **201** para cada uno. Crear otro de 1 ha debe devolver **422**. Actualizar el primero a 4 ha debe devolver **422**; actualizarlo a 2.5 ha debe devolver **200**.

Con sectores de 3, 4 y 2 ha, usa la ruta ACTUAL de Plot:

`PATCH http://127.0.0.1:8000/api/plots/update/1`

```json
{
  "total_hectareas": 8
}
```

Debe devolver **422**. Repite con 9 y 12: **200**. PUT a la misma ruta también debe respetar el límite; incluye `nombre_parcela` en su body.

Prueba nombres vacíos o de más de 150 caracteres, superficies cero o negativas, descripción o ubicación de más de 255 caracteres: **422**.

La comprobación concurrente de MySQL queda pendiente: con 1 ha libre, envía simultáneamente dos creaciones de 1 ha a la misma parcela. Solo una debe ser aceptada; la suma nunca debe superar el total. SQLite en memoria no valida los bloqueos de InnoDB.

## Eliminar (200)

`DELETE http://127.0.0.1:8000/api/sectors/delete/1`

Sin body. Después, GET del mismo ID debe devolver **404**.

## Consultas de comprobación en DBeaver

Ejecuta antes de eliminar si deseas inspeccionar el sector creado:

```sql
SELECT * FROM sectores;

SELECT
    s.id_sector,
    s.nombre_sector,
    s.superficie_hectareas,
    p.id_parcela,
    p.nombre_parcela,
    p.id_usuario
FROM sectores s
INNER JOIN parcelas p ON p.id_parcela = s.id_parcela;

SELECT p.id_parcela, p.total_hectareas,
       COALESCE(SUM(s.superficie_hectareas), 0) AS occupied_hectares
FROM parcelas p
LEFT JOIN sectores s ON s.id_parcela = p.id_parcela
GROUP BY p.id_parcela, p.total_hectareas
HAVING COALESCE(SUM(s.superficie_hectareas), 0) > p.total_hectareas;
```

La última consulta no debe devolver filas.

## Pruebas automatizadas aisladas

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit tests/Feature/SectorTest.php tests/Feature/PlotTest.php tests/Feature/AuthJwtTest.php tests/Feature/AuthRegisterTest.php tests/Feature/UserCrudCompatibilityTest.php
```

Las pruebas sustituyen la conexión únicamente dentro del proceso de testing por SQLite `:memory:`. No cambian `.env`, no ejecutan migraciones y no escriben en `aiyond_db`.