# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y [Versionado Semántico](https://semver.org/lang/es/).
Cada entrada indica la solicitud de modificación (MR) y su tipo según ISO/IEC/IEEE 14764:2022 (ver `docs/04_PLAN_DE_MANTENIMIENTO.md`).

## [2.1.0] — 2026-10-21

### Agregado
- **MR-010 (aditivo):** exportación de las Órdenes de Operación a **CSV** (UTF-8 con BOM, separador `;`) y **JSON**, respetando los filtros del panel y neutralizando la inyección de fórmulas de Excel (`= + - @`). Botones «Exportar CSV» y «JSON» en el panel.
- **MR-008 (adaptativo):** configuración por archivo `.env` (`.env.example`) y variable `UPLOAD_DIR`; el servidor tiene prioridad sobre el archivo.
- Escáner de seguridad estático `tools/escanear_seguridad.php` (`composer seguridad`) con 9 reglas y supresión con motivo.
- Pruebas: 424 unitarias y de regresión (PHPUnit), 36 E2E, verificación de reglas de Apache (`tests/apache/verificar_htaccess.sh`).
- Herramientas de calidad: PHPCS (PSR-12), PHPMD, PHPUnit, integración continua (`.github/workflows/ci.yml`), configuración de depuración.
- Documentación: especificación, casos de prueba, auditoría del código, plan de mantenimiento, despliegue e informe técnico (`docs/`).

### Corregido
- **MR-001:** el valor CIF ya no acepta notación científica ni infinito.
- **MR-002:** el teléfono exige al menos 7 **dígitos** reales.
- **MR-003:** `origen` y `destino` del cotizador limitados a 120 caracteres.
- **MR-004:** el cálculo del dígito de verificación del NIT rechaza entradas no numéricas.
- **MR-005 (seguridad):** `SQL/`, `README.md`, `composer.json`, código fuente, pruebas, documentación y `.env` ya no se pueden descargar por HTTP.
- **MR-006 (seguridad):** el login limita los intentos fallidos (5 por ventana) y ya no revela si un usuario existe.

### Seguridad
- **MR-009 (preventivo):** política de seguridad de contenido (CSP) estricta en el panel y el acceso: el JavaScript del panel se extrajo a `assets/js/panel.js`.

### Cambiado
- **MR-007 (perfectivo):** refactor modular. De funciones globales en archivos de hasta 1 835 líneas a 38 clases `final` en `src/` con capas Dominio / Seguridad / Http / Panel / Archivos / Infraestructura; sin `exit`, sin código duplicado y sin números mágicos. Comportamiento visible intacto (las 28 E2E originales siguen en verde).
- `config/config_db.php` pasó a `config/arranque.php`; `config/config.php` ahora solo declara constantes. Se eliminó la constante `CSRF_SECRET`, que ningún código usaba.

## [2.0.0] — línea base recibida
Panel operativo v2 y landing, sin pruebas automáticas, con funciones globales y sin clases.
