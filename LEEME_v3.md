# Market Donna — actualización v3

1. Respaldo de la base `sistema_ventas` (phpMyAdmin > Exportar).
2. phpMyAdmin > SQL > pega `sql/migracion_v3.sql` > Continuar (una sola vez).
3. Copia esta carpeta sobre `htdocs/sistema_ventas` (reemplaza archivos).
4. Entra a **Productos**: al crear o editar cada producto ya puedes subir su foto
   (JPG, PNG o WebP, hasta 2 MB; se reduce sola). Las fotos quedan en `uploads/productos/`.

Si una foto grande falla, sube `upload_max_filesize` y `post_max_size` en `php.ini` de XAMPP.
Las tipografías (Baloo 2 y Figtree) se cargan de Google Fonts; sin internet se usa la del sistema.
