# Insumos: uso de la API

Todas las rutas requieren un JWT. Cualquier usuario autenticado puede consultar el catálogo; crear, actualizar y eliminar requiere rol admin.

## Crear

`POST /api/supplies/create`

```json
{
  "nombre_insumo": "NOMBRE_VALIDADO_DEL_INSUMO",
  "tipo_insumo": "TIPO_VALIDADO",
  "unidad_medida": "UNIDAD_VALIDADA",
  "costo_unitario": null,
  "descripcion": null
}
```

Los textos son marcadores del contrato, no productos ni recomendaciones agrícolas.

## Consultar

```text
GET /api/supplies/list
GET /api/supplies/list/1
```

## Actualizar

`PATCH /api/supplies/update/1`

```json
{
  "descripcion": null
}
```

## Eliminar

`DELETE /api/supplies/delete/1`

Devuelve 200 al eliminar, 404 si el insumo no existe y 409 si tiene relaciones con enfermedades. La API no elimina esas relaciones en cascada.

## Autorización

- Sin JWT o con JWT inválido: 401.
- Usuario normal en una escritura: 403.
- Admin con datos inválidos: 422.
- `costo_unitario` puede ser null o un número mayor o igual que cero.

No se incluyen datos agronómicos iniciales. Productos, dosis y frecuencias deben ser proporcionados o validados por un profesional agrónomo.
