# VentaPro v2 — cómo instalar

1. Respaldo: en phpMyAdmin exporta tu base `sistema_ventas`.
2. Base de datos: pestaña SQL → pega `sql/migracion_v2.sql` → Continuar (una sola vez).
3. Copia esta carpeta sobre `htdocs/sistema_ventas` (reemplaza los archivos).
4. Entra con tus usuarios de siempre. Crea cuentas de Ventas/Soporte en **Equipo**.

Perfiles: Administración (todo) · Ventas (caja, inventario y facturación de su tienda) ·
Soporte (casos, clientes, consulta de ventas e inventario) · Cliente (tienda en línea).

Tiendas: se crean "Tienda 1" y "Tienda 2". Renombra con:
  UPDATE sedes SET nombre='Tu nombre', direccion='...' WHERE id_sede=1;
Todo el stock actual queda en Tienda 1; repártelo con Inventario → Transferir.
