# DiseaseSupply: Postman and MySQL checks

Estas peticiones son para ejecución manual. No se insertaron datos en MySQL durante el desarrollo. Usa una enfermedad, un insumo y una cuenta admin existentes. Sustituye los IDs de ejemplo por los reales.

## Login

`POST http://127.0.0.1:8000/api/auth/login`

```json
{
  "correo": "correo-de-tu-cuenta-admin",
  "password": "contraseña-de-tu-cuenta"
}
```

Copia `data.access_token`. Headers para las peticiones siguientes:

```text
Authorization: Bearer {ADMIN_TOKEN}
Accept: application/json
Content-Type: application/json
```

## Crear relación — admin

`POST http://127.0.0.1:8000/api/disease-supplies/create`

```json
{
  "id_enfermedad": 2,
  "id_insumo": 1,
  "frecuencia_aplicacion": "Cada 15 días",
  "organico": false,
  "dosis_referencial_ha": 2.50
}
```

**La dosis y frecuencia son ejemplos técnicos proporcionados para probar el formato, no recomendaciones agronómicas validadas.** Los valores reales deben configurarse con información agrícola validada.

Esperado: 201. Guarda `data.id_enfermedad_insumo` y úsalo en lugar de 1. Repetir la misma combinación debe devolver 409 sin SQL interno. Referencias inexistentes o datos inválidos devuelven 422.

## Lecturas — user o admin

```text
GET http://127.0.0.1:8000/api/disease-supplies/list
GET http://127.0.0.1:8000/api/disease-supplies/list/1
GET http://127.0.0.1:8000/api/diseases/2/supplies
```

La última consulta devuelve las relaciones de la enfermedad y el objeto `supply` con nombre, tipo, unidad, costo y descripción. `organico` es booleano y `dosis_referencial_ha` es una cadena decimal de dos posiciones o null. No se calcula ninguna cantidad total.

Repite con JWT de usuario normal: debe poder consultar el mismo catálogo. Una enfermedad existente sin insumos, incluida Sano, devuelve 200 con `data: []`. Una enfermedad inexistente devuelve 404.

## PATCH — admin

`PATCH http://127.0.0.1:8000/api/disease-supplies/update/1`

```json
{
  "frecuencia_aplicacion": "Cada 20 días"
}
```

Esperado: 200. El resto de los campos se conserva.

## PUT — admin

`PUT http://127.0.0.1:8000/api/disease-supplies/update/1`

```json
{
  "id_enfermedad": 2,
  "id_insumo": 1,
  "frecuencia_aplicacion": "Cada 15 días",
  "organico": false,
  "dosis_referencial_ha": 2.50
}
```

Esperado: 200. Mantener la misma combinación no es un duplicado del propio registro. Cambiarla a una combinación ocupada por otra relación devuelve 409.

## DELETE — admin

`DELETE http://127.0.0.1:8000/api/disease-supplies/delete/1`

Sin body. Esperado: 200. Elimina solamente la relación; enfermedad e insumo deben continuar existiendo. Repetir devuelve 404.

## Seguridad y validaciones

- Sin JWT, token inválido o expirado: 401.
- JWT user intentando POST, PUT, PATCH o DELETE: 403.
- Dosis negativa, booleano inválido o frecuencia de más de 150 caracteres: 422.
- GET de relación o enfermedad inexistente: 404.
- Cambiar `id_enfermedad_insumo` desde el body no está permitido; el campo se ignora.

## Pruebas automatizadas

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

SQLite se utiliza exclusivamente para testing, sin cambios en `.env` ni escrituras en `aiyond_db`. Las pruebas cubren la respuesta controlada ante una violación UNIQUE simulada; la concurrencia real del motor MySQL queda pendiente de comprobación manual.
