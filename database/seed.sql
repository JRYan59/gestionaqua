-- Generic, non-sensitive reference data for a fresh installation.

INSERT IGNORE INTO configuracion_empresa (id, nombre_empresa, tasa_cambio_usd)
VALUES (1, 'AQUA', 1.0000);

INSERT IGNORE INTO categorias (id, nombre, descripcion, activo) VALUES
    (1, 'Botellas Vacías', 'Botellas vacías de diferentes tamaños', 1),
    (2, 'Botellas Llenas', 'Botellas llenas de agua purificada', 1),
    (3, 'Servicios de Llenado', 'Servicios de llenado de botellas', 1),
    (4, 'Accesorios', 'Accesorios relacionados con botellas', 1);
