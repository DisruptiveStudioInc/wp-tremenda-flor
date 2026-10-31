# Personalizar Tremenda Flor

Plugin de funcionalidades personalizadas para Tremenda Flor, desarrollado por
**Disruptive Crea**. Versión actual: **1.1.0**.

## Activación

Activa **Personalizar Tremenda Flor** desde **Plugins** en la administración de
WordPress. La activación es manual y no crea opciones ni tablas.

## Shortcode de categorías del producto

Inserta el siguiente shortcode en el contenido o en un widget de shortcode de la
plantilla individual de producto:

```text
[producto_categoria_nombre]
```

Devuelve los nombres de todas las categorías asignadas al producto consultado,
separados por coma y espacio, como texto escapado para HTML. Por ejemplo:
`Ramos, Flores de temporada`.

Fuera de una página individual de producto, o si no hay categorías o se produce
un error al consultarlas, devuelve una cadena vacía. No acepta atributos.
El plugin se puede cargar sin WooCommerce; mostrar categorías requiere el tipo
de contenido `product` y la taxonomía `product_cat` que este proporciona.

## Organización y nuevas funcionalidades

- `personalizar-tremenda-flor.php`: metadatos y carga explícita de módulos.
- `shortcodes/producto-categoria-nombre.php`: registro y callback del shortcode.

Añade cada nueva funcionalidad en su propio archivo. Coloca los nuevos shortcodes
en `shortcodes/` y crea carpetas por responsabilidad cuando añadas otros tipos de
funcionalidad. Carga cada módulo con un `require_once` explícito en el archivo
principal, usando rutas relativas a `__DIR__`.

Cada archivo PHP debe incluir la protección `defined( 'ABSPATH' ) || exit;`.
Usa el namespace `PersonalizarTremendaFlor` o un subnamespace correspondiente al
módulo para evitar colisiones. Registra el comportamiento mediante hooks de
WordPress; los shortcodes se registran en `init`. Mantén la lógica de cada
funcionalidad dentro de su módulo y deja el archivo principal como bootstrap.

## Galerías de variaciones de WooCommerce

El módulo `woocommerce/variation-galleries/` integra las galerías por estampado,
el cambio de imágenes al seleccionar una variación y su restauración al limpiar
la selección. Se carga automáticamente junto al shortcode existente.
No necesita un plugin separado ni modifica las asignaciones de imágenes guardadas.
Ver `woocommerce/variation-galleries/README.md` para los metadatos y la integración.
