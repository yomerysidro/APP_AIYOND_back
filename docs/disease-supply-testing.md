# Relaciones enfermedad-insumo

Todas las rutas requieren un JWT. Las lecturas están disponibles para usuarios autenticados y las escrituras requieren rol admin.

## Crear una relación

`POST /api/disease-supplies/create`

```json
{
  "id_enfermedad": 2,
  "id_insumo": 1,
  "frecuencia_aplicacion": null,
  "organico": false,
  "dosis_referencial_ha": null
}
```

Los IDs deben existir. La dosis y frecuencia permanecen en null hasta que un profesional agrónomo proporcione datos validados. Cuando se envía una dosis debe ser finita y mayor que cero. La enfermedad Sano no admite asociaciones.

## Consultar

```text
GET /api/disease-supplies/list
GET /api/disease-supplies/list/1
GET /api/diseases/2/supplies
```

Una enfermedad existente sin insumos devuelve HTTP 200 con `data: []`. Una enfermedad inexistente devuelve 404.

## Actualizar

`PATCH /api/disease-supplies/update/1`

```json
{
  "frecuencia_aplicacion": null,
  "organico": true
}
```

## Eliminar

`DELETE /api/disease-supplies/delete/1`

Elimina únicamente la relación. El insumo y la enfermedad permanecen registrados.

## Respuestas relevantes

- Relación creada: 201.
- Validación, dosis no positiva o asociación con Sano: 422.
- Relación duplicada: 409.
- ID de relación inexistente: 404.
- Sin JWT: 401.
- Usuario no administrador en escrituras: 403.

No se incluyen productos, dosis ni frecuencias de ejemplo porque esos datos requieren validación profesional.
