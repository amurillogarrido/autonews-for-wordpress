# Changelog

## [1.1.0] - 2025-04-16
### Añadido
- 🧠 **Generación automática del excerpt**: El plugin ahora solicita a la IA de OpenAI que genere un resumen (excerpt) breve del artículo.
- ✅ **Publicación del excerpt en WordPress**: El resumen devuelto por GPT se guarda automáticamente en el campo `post_excerpt` de WordPress, compatible con temas y bloques que lo utilicen.
- 🔤 Traducciones del prompt actualizadas en todos los idiomas para incluir el nuevo campo `Excerpt`.

### Corregido
- Limpieza de contenido mejorada: eliminación de `<figcaption>` y textos como “Pie de foto”.

---

## [1.0.0] - 2025-01-01
### Añadido
- Versión inicial del plugin AutoNews.
- Reescritura automática de artículos de feeds RSS usando OpenAI.
- Publicación programada o inmediata.
- Extracción de imágenes y generación automática de miniaturas.
- Configuración por categoría, autor y idioma.
