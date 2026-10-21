# 04 · Plan de mantenimiento y registro de solicitudes de modificación (MR/PR)

> **Asignatura:** Codificación y Pruebas del Software · **Unidad 3:** Mantenimiento y depuración
> **Norma de referencia:** ISO/IEC/IEEE 14764:2022 — *Software engineering — Software life cycle processes — Maintenance*.
> Las definiciones de este documento (3.1.x) están tomadas literalmente de la norma, traducidas al español.

## 1. Propósito y alcance

Define **cómo se mantiene** el sistema Rubicam (landing + panel de Órdenes de Operación) después de entregado y deja el
**registro de las modificaciones ya realizadas** en esta intervención. Cada cambio nace como una *solicitud de modificación*
(MR), se clasifica, se analiza su impacto, se implementa en su propia rama de Git con una prueba de regresión y se cierra con
evidencia. Nada se cambia "de pasada".

## 2. Vocabulario (ISO/IEC/IEEE 14764:2022, cláusula 3)

| Término | Definición de la norma (resumida) | Cómo se usa aquí |
|---|---|---|
| **MR** — solicitud de modificación (3.1.8) | Elemento de información que identifica y describe un cambio propuesto; luego se clasifica como corrección o mejora. También se llama *solicitud de cambio*. | Una fila de la tabla 4 (`MR-nnn`) + una rama de Git. |
| **PR** — informe de problema (3.1.11) | Documento que identifica y describe problemas detectados en el producto. | Los hallazgos `H-nn` de `docs/03_AUDITORIA_CODIGO.md`. |
| **Mantenimiento correctivo** (3.1.4) | Modificación posterior a la entrega para **corregir problemas descubiertos**. | MR-001 … MR-006 |
| **Mantenimiento preventivo** (3.1.10) | Modificación para **corregir fallas latentes antes de que ocurran** en producción. | MR-009 (y la guardia que el escáner de seguridad ejerce) |
| **Mantenimiento adaptativo** (3.1.1) | Modificación para que el producto **siga siendo utilizable en un entorno cambiado o cambiante**. | MR-008 |
| **Mantenimiento perfectivo** (3.1.9) | Modificación para mejorar **rendimiento, mantenibilidad** u otros atributos. | MR-007 |
| **Mantenimiento aditivo** (3.1.2) | Modificación para **agregar funcionalidad o características** que mejoran el uso del producto. | MR-010 |

Según la nota 8 de 3.1.8, la clasificación también puede verse como **reactiva** (correctivo, adaptativo) o **proactiva**
(preventivo, perfectivo, aditivo). Eso permite medir qué proporción del esfuerzo se dedica a apagar incendios y cuál a
evitarlos.

## 3. Proceso de mantenimiento aplicado

```
 PR/idea ──► MR registrada ──► clasificar ──► análisis de impacto ──► rama de Git
                                                                          │
   cierre con evidencia ◄── merge --no-ff ◄── REFACTOR ◄── GREEN ◄── RED (la prueba de regresión FALLA)
```

1. **Registrar** la MR (tabla 4) con su origen (PR `H-nn` o solicitante).
2. **Clasificar** (correctivo / preventivo / adaptativo / perfectivo / aditivo) y fijar prioridad.
3. **Análisis de impacto** *antes* de tocar código (sección 5): qué módulos, pruebas y documentos cambian y qué podría romperse.
4. **Rama propia** (`fix/…`, `feature/…`, `refactor/…`, `test/…`) y **Conventional Commits**.
5. **RED**: escribir la prueba de regresión y ver que **falla** por la razón correcta (se guarda `docs/evidencias/tdd/mr-nnn-rojo.txt`).
6. **GREEN**: el cambio mínimo que la hace pasar (`mr-nnn-verde.txt`) **más** toda la suite (unitarias + 36 E2E).
7. **REFACTOR** si hace falta, con la suite en verde.
8. **Compuertas**: `phpcs` (0), `phpmd` (0), escáner de seguridad (0), `phpunit` y E2E (verde).
9. **Merge `--no-ff`** a `main`: cada MR queda como una burbuja visible en `git log --graph`; si falla en producción, **revertir** ese merge (`git revert -m 1 <merge>`) deshace la MR completa.
10. **Registrar el cierre** (estado, commits, evidencia).

## 4. Registro de MR (todas cerradas en la versión **v2.1.0**)

| MR | PR de origen | Tipo (14764) | Reactiva / proactiva | Prioridad | Descripción | Prueba de regresión | RED → GREEN → merge |
|---|---|---|---|---|---|---|---|
| **MR-001** | H-01 | Correctivo | Reactiva | Media | El importe CIF aceptaba notación científica e infinito (`1e999` → `INF`). Ahora solo decimales estrictos con tope. | `Mr001ImporteEstrictoTest`, E2E-34 | `4379e67` → `517fda6` → `5047d7b` |
| **MR-002** | H-02 | Correctivo | Reactiva | Media | `-------` pasaba como teléfono: se contaban caracteres, no dígitos. Mínimo 7 dígitos reales. | `Mr002TelefonoConDigitosTest`, E2E-35 | `4379e67`, `e81ad2c` → `517fda6` → `5047d7b` |
| **MR-003** | H-03 | Correctivo | Reactiva | Media | `origen`/`destino` del cotizador sin longitud máxima (5 000 000 de caracteres llegaban al correo). Tope de 120. | `ControladorCotizacionTest`, E2E-34 | `56daa51` → `63ccada` → `5c72ee6` |
| **MR-004** | H-04 | Correctivo | Reactiva | Baja | `calcular_dv_nit('abc12')` devolvía `9` en silencio. Ahora lanza excepción (codificación defensiva). | `Mr004NitDefensivoTest` | `56daa51` → `63ccada` → `5c72ee6` |
| **MR-005** | H-05 | Correctivo (seguridad) | Reactiva | **Alta** | `/SQL/rubicam_db.sql` (con hash de contraseña y datos de clientes) y `README.md` se descargaban por HTTP. Se niega todo lo interno por defecto. | `Mr005ArchivosInternosTest` + `tests/apache/verificar_htaccess.sh` (Apache real: antes **200**, después **403**) | `adda409` → `6ff565b` → `6cb7801` |
| **MR-006** | H-06, H-07 | Correctivo (seguridad) | Reactiva | **Alta** | El login no limitaba intentos (fuerza bruta) y revelaba si el usuario existía por tiempo de respuesta. `LimitadorTasa` + hash falso. | `LimitadorTasaTest`, `AutenticadorTest`, E2E-29/30 | `4a102f0` → `7b5f8e6` → `3e59f20` |
| **MR-007** | H-08 … H-15 | Perfectivo | Proactiva | Media | Refactor modular: de 3 873 líneas en funciones globales a 38 clases `final` con capas (Dominio/Seguridad/Http/Panel…); sin `exit`, sin duplicados, sin números mágicos. | Las 28 E2E originales **sin modificar** + unitarias de cada módulo | `661c2a5`/`75a66e2`, `8b9a2d8`/`08358ed`, `4719b53`, `15d699c`, `b88913e` → `4a32675` |
| **MR-008** | H-16 | Adaptativo | Reactiva | Baja | Mismo código en desarrollo, pruebas y producción: configuración por `.env`/variables de entorno y carpeta de cargas (`UPLOAD_DIR`) configurable. | `CargadorEntornoTest`, E2E-36 | `00d1a01` → `e9c1f7b` → `05d2ddd` |
| **MR-009** | H-17 | Preventivo | Proactiva | Media | El panel no enviaba CSP; la del sitio permitía `'unsafe-inline'`. Se extrajo el JavaScript a `assets/js/panel.js` y se aplicó CSP estricta (`script-src 'self'`). Falla latente (XSS) corregida antes de explotarse. | `CabecerasSegurasTest`, E2E-33 | `2180f49` → `8727b38` → `dccfc0c` |
| **MR-010** | Solicitud de usuarios de gestión | Aditivo | Proactiva | Baja | Exportar las DO (con los filtros del panel) a CSV para Excel y a JSON, con defensa contra *CSV injection*. | `ExportadorDoTest`, `RespuestaTest`, E2E-31/32 | `9c3db8b` → `dd01329` → `eb9e0f3` |
| (herramienta) | — | Preventivo / perfectivo | Proactiva | — | Escáner de seguridad estático por tokens (`tools/`), equivalente a *bandit*, aplicado al propio proyecto. | `EscanerSeguridadTest` (58) | `be97134` → `103a2b6` → `195be23` → `c0befdc` |

**Distribución del esfuerzo:** 6 MR correctivas (60 %) · 1 preventiva · 1 adaptativa · 1 perfectiva · 1 aditiva. Reactivas: 7 de 10; proactivas: 3 de 10 (más la herramienta). La meta de mantenimiento es **invertir** esa proporción: el escáner, la CSP y las compuertas de CI existen para que los próximos problemas se detecten *antes* de llegar a producción.

> **Observaciones que no se tratan como MR** (decisión consciente, ver `docs/03_AUDITORIA_CODIGO.md` §3): el prefijo del código de DO
> (cambiaría una regla de negocio; pregunta abierta al cliente), los NIT de ejemplo del SQL (datos del cliente) y la tolerancia de
> fecha ilegible en el semáforo (no alcanzable con la columna `DATE NOT NULL`).

## 5. Análisis de impacto (plantilla y ejemplos)

Antes de implementar cualquier MR se responde por escrito:

| Pregunta | MR-006 (login) | MR-010 (exportar) | MR-008 (entorno) |
|---|---|---|---|
| ¿Qué módulos cambian? | `Autenticador`, `LimitadorTasa`, `panel/index.php` | `ExportadorDo`, `ControladorDo`, `Despachador`, `Respuesta`, vista y JS del panel | `Configuracion`, `CargadorEntorno`, `config/arranque.php`, `config/config.php` |
| ¿Qué pruebas existentes pueden romperse? | E2E-03/04/05 (login) | E2E-07 (listar), E2E-21 (rol consulta) | Todas las E2E (todas usan `Configuracion`) |
| ¿Cambia el esquema de BD / el contrato público? | No | No (acción nueva `?action=exportar`) | No; **sí** el nombre del archivo de arranque (`config_db.php` → `arranque.php`; se actualizaron los 7 puntos de entrada) |
| ¿Riesgo para la seguridad? | Bloqueo de usuarios legítimos (DoS) → ventana corta y reinicio tras éxito | Exponer datos: solo columnas de una lista fija; CSV injection neutralizada | `.env` descargable → bloqueado por `.htaccess` (CP-SEG-08) y fuera de Git |
| ¿Cómo se revierte? | `git revert -m 1 3e59f20` | `git revert -m 1 eb9e0f3` | `git revert -m 1 05d2ddd` |
| ¿Qué documentación se actualiza? | 02, 04, informe | 02, 04, README, informe | 02, 04, 05, `.env.example` |

## 6. Estrategia de pruebas de regresión

* **Una prueba por defecto**: toda corrección añade una prueba etiquetada `@group MR-nnn` que falla sin la corrección. `phpunit --group MR-006` ejecuta solo esa regresión.
* **Caracterización antes de mover código**: antes de refactorizar (MR-007) se escribieron **28 pruebas E2E** que fijan el comportamiento visible; deben seguir en verde, **sin cambiar su resultado esperado**, tras cada paso. Si una se pone en rojo, se detiene el refactor.
* **Suite completa en cada cambio**: 422 unitarias/regresión + 36 E2E + 21 comprobaciones contra Apache real (ver `docs/INFORME_TECNICO.md`).
* **CI** (`.github/workflows/ci.yml`): cada *push* y *pull request* ejecuta linter, análisis estático, escáner de seguridad, pruebas con cobertura y E2E sobre MariaDB, en PHP 8.2 y 8.3. *(El flujo está escrito pero no pudo ejecutarse en el entorno de desarrollo; las mismas compuertas se ejecutaron a mano.)*

## 7. Trazabilidad: hallazgo → MR → prueba → commit

```
H-05 (SQL descargable) ─► MR-005 ─► Mr005ArchivosInternosTest + verificar_htaccess.sh ─► adda409 (RED) · 6ff565b (GREEN)
H-06 (fuerza bruta)    ─► MR-006 ─► LimitadorTasaTest + AutenticadorTest + E2E-29/30   ─► 4a102f0 (RED) · 7b5f8e6 (GREEN)
H-17 (sin CSP)         ─► MR-009 ─► CabecerasSegurasTest + E2E-33                      ─► 2180f49 (RED) · 8727b38 (GREEN)
```

`git log --oneline --graph` muestra la cadena completa; cada hash de la tabla 4 existe en el repositorio.

## 8. Roles y mantenimiento futuro

| Rol | Responsabilidad |
|---|---|
| **Solicitante** (cliente / docente) | Reporta problemas (PR) o pide cambios (MR) y acepta el resultado. |
| **Mantenedor** (estudiante) | Clasifica, analiza el impacto, implementa con RED→GREEN, documenta. |
| **Revisor** | Revisa el *pull request* contra la lista de verificación: pruebas, compuertas en verde, impacto documentado, CHANGELOG. |

**Plan a futuro:** actualizar dependencias de desarrollo trimestralmente (adaptativo); ejecutar `composer seguridad` y `phpmd` en cada PR; revisar `logs/php-error.log` semanalmente; reservar un 20 % del tiempo a MR proactivas (preventivas y perfectivas).
