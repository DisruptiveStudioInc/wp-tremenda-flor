# Módulo de galerías por variación

Módulo de `Personalizar Tremenda Flor`, cargado por su archivo principal. No cambia archivos de WooCommerce, Elementor ni Vamtam.

- `_tf_variation_gallery_ids`: tres IDs de adjuntos adicionales por variación; el frente utiliza la imagen nativa de WooCommerce.
- `_tf_catalog_gallery_key`: estampado cuya galería se utiliza en el producto general.
- El filtro `woocommerce_available_variation` añade `tf_gallery` con clave de IDs y HTML generado por WooCommerce. Funciona tanto con variaciones incluidas en la página como con la búsqueda AJAX.
- El frontend escucha `found_variation` y `reset_data`; reconstruye el controlador de galería para evitar imágenes y lightbox de la selección anterior. Conserva el modo de galería completa de Vamtam y el slider móvil.
- Desactivar Personalizar Tremenda Flor conserva todas las asignaciones. WooCommerce seguirá mostrando la imagen principal de cada variación, pero no cambiará las tres tomas adicionales.

Importador: `scripts/import-catalog-galleries.php`, restringido a `http://localhost:9070`.
Ejecutar `inspect`, luego `apply`; verificar con `verify` pasando el estado JSON previo.
El respaldo y los informes de la prueba están en `catalogo/prueba-galerias-local` (directorio hermano del repositorio).
Los adjuntos se reutilizan mediante `_tf_catalog_image_source`, con la ruta relativa del manifiesto.
No volver a usar el verificador inicial de placeholders para este catálogo: exige la imagen anterior.
