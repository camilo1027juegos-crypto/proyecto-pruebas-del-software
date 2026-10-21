# 03 · Auditoría del código heredado (análisis forense previo al refactor)

> **Asignatura:** Codificación y Pruebas del Software · **Unidad 1:** Codificación de software
> **Tema:** Fundamentos de la codificación — tipos comunes de errores y su impacto (guía, actividad 1).
> **Código auditado:** etiqueta Git `v2.0.0` (línea base, antes de cualquier cambio).

## 1. Método

1. Lectura completa del código fuente (10 archivos PHP, 2 SQL, 1 JS).
2. Ejecución de herramientas automáticas sobre la línea base (evidencias en `docs/evidencias/antes-*.txt`):
   - `php -l` (sintaxis) · `phpcs --standard=PSR12` (estilo) · `phpmd` (complejidad, código muerto, diseño) · `phploc` (tamaño).
3. **Reproducción real** de cada sospecha de defecto con un script (no se reporta nada que no se haya podido demostrar).
4. Clasificación de cada hallazgo por **tipo de error** y por **característica de calidad ISO/IEC 25010**.

### Tipos de error utilizados

| Tipo | Significado |
|---|---|
| **Sintáctico** | El código no cumple la gramática del lenguaje (lo detecta `php -l`). |
| **Lógico** | Compila y corre, pero produce un resultado incorrecto. |
| **De ejecución** | Falla en tiempo de ejecución ante ciertos datos o entorno (excepciones, recursos). |
| **De seguridad** | Permite a un tercero leer, alterar o negar el servicio. |
| **De estilo / diseño** | No rompe la ejecución pero degrada la mantenibilidad (duplicación, complejidad, acoplamiento). |

### Escala de severidad

**Alta**: compromete confidencialidad/integridad o interrumpe la operación · **Media**: resultado incorrecto o riesgo moderado · **Baja**: deuda técnica, sin impacto inmediato.

## 2. Resultados automáticos de la línea base

| Herramienta | Resultado en `v2.0.0` |
|---|---|
| `php -l` | 10/10 archivos sin errores **sintácticos**. |
| `phpcs` PSR-12 | **56 errores y 249 advertencias** (242 de longitud de línea; 7 archivos con *side effects* mezclados con declaraciones). |
| `phpmd` | 47 hallazgos. Complejidad ciclomática (CC) fuera de umbral (10): `validar_nuevo_do` **25** (NPath **207 360**), `accion_descargar` 13, `calcular_semaforo_do` 12, `accion_crear` 11, `firma_archivo_valida` 11. |
| `phploc` | 3 873 líneas, **0 clases, 0 espacios de nombres**; 410 LLOC en funciones globales; 101 accesos a variables/constantes globales. |
| Pruebas automáticas | **Ninguna** (antes de este trabajo). |

## 3. Hallazgos verificados

| ID | Ubicación (`v2.0.0`) | Hallazgo | Tipo | ISO/IEC 25010 | Sev. | Evidencia reproducible | Acción |
|---|---|---|---|---|---|---|---|
| H-01 | `config/security.php:69-73` `clean_number()` | Acepta notación científica e infinito: `'1e3'`→`1000.0`, `'1e999'`→`INF`. El cliente sí rechaza esos valores (regex `^\d+(\.\d{1,2})?$`), de modo que **el servidor no es autoritativo**. `INF` termina en el correo del asesor. | Lógico / Seguridad (validación) | Fiabilidad · Seguridad (integridad) | Media | `clean_number('1e999')` → `INF` | **MR-001** |
| H-02 | `config/security.php:63-67` `clean_phone()` y `panel/dashboard.php:270` | `'-------'`, `'()()()()'` y `'++++++++'` pasan como teléfono válido: se cuentan *caracteres*, no *dígitos* (el mensaje de error dice «7 a 30 dígitos»). | Lógico | Adecuación funcional · Fiabilidad | Media | `clean_phone('-------')` → `'-------'` | **MR-002** |
| H-03 | `handlers/quote-handler.php:41-43` | `origen` y `destino` no tienen longitud máxima: se aceptaron 5 000 000 de caracteres hacia el cuerpo del correo. | De ejecución / Seguridad (DoS) | Eficiencia de desempeño · Seguridad (disponibilidad) | Media | `strlen(clean_string(str_repeat('a', 5000000)))` → `5000000` | **MR-003** |
| H-04 | `config/config_db.php:327-337` `calcular_dv_nit()` | Con entradas no numéricas devuelve un dígito en silencio (`'abc12'`→`9`) en vez de rechazarlas. Un error del llamador se convierte en un dato corrupto. | Lógico (falta de codificación defensiva) | Fiabilidad (tolerancia a fallos) | Baja | `calcular_dv_nit('abc12')` → `9` | **MR-004** |
| H-05 | `.htaccess` (raíz) | Solo se bloquean `config/` y `logs/`. En Apache quedan **descargables** `SQL/rubicam_db.sql` (contiene un hash de contraseña y datos de clientes), `README.md`, `composer.json`/`composer.lock`, `package-lock.json` y todo el código de `handlers/`. | Seguridad (exposición de información) | Seguridad (confidencialidad) | **Alta** | Lectura de reglas de `.htaccess`; verificación con Apache en `docs/evidencias/despues-*` | **MR-005** |
| H-06 | `panel/index.php:20-57` | El login no limita intentos (fuerza bruta) y solo ejecuta `password_verify` si el usuario existe, lo que permite **enumerar usuarios por tiempo de respuesta**. | Seguridad | Seguridad (autenticidad) | **Alta** | 10 intentos fallidos seguidos → todos responden 200 | **MR-006** |
| H-07 | `config/security.php:80-97` `rate_limit_check()` | Función genérica (recibe un *bucket*) acoplada a las constantes `QUOTE_RATE_LIMIT_*` del cotizador; lee y escribe el archivo sin bloqueo único (condición de carrera) y no verifica el resultado de `mkdir`. | De diseño / De ejecución | Mantenibilidad (reusabilidad) · Fiabilidad | Media | Lectura del código | **MR-006** (se reescribe como `LimitadorTasa`) |
| H-08 | `responder` ×4: `handlers/quote-handler.php:18`, `handlers/upload_documento.php:22`, `handlers/webhook_trigger.php:26`, `panel/dashboard.php:68` · `h()` ×2: `config/security.php:75`, `config/config_db.php:125` · CSRF ×2 · inicio de sesión ×2 | **Código duplicado**: un cambio (p. ej. agregar una cabecera de seguridad) hay que hacerlo 4 veces. | De estilo / diseño | Mantenibilidad (modularidad, modificabilidad) | Media | `grep -n "function responder"` | **MR-007** |
| H-09 | `panel/dashboard.php` (1 835 líneas) | Un solo archivo mezcla SQL, reglas de negocio, controlador HTTP, HTML y 900 líneas de JavaScript. Imposible de probar por partes. | De diseño | Mantenibilidad (modularidad, capacidad de ser probado) | Media | `phploc`: 0 clases | **MR-007** |
| H-10 | `panel/dashboard.php:202` `validar_nuevo_do()` | CC = **25**, NPath = **207 360**: no se puede razonar ni cubrir con pruebas todas las rutas. | De diseño | Mantenibilidad (analizabilidad) | Media | `phpmd` | **MR-007** |
| H-11 | 7 archivos | `PSR1.Files.SideEffects`: archivos que declaran funciones **y** ejecutan lógica (no se pueden incluir en una prueba sin disparar efectos, p. ej. enviar cabeceras o llamar `exit`). | De diseño | Mantenibilidad (capacidad de ser probado) | Media | `phpcs` | **MR-007** |
| H-12 | `exit` dentro de 5 funciones; operador `@` en 3 sitios (`upload_documento.php:284,288,321`) | `exit` en una función impide probarla; `@` oculta errores reales de E/S. | De estilo / diseño | Mantenibilidad · Fiabilidad | Baja | `phpmd` (ExitExpression, ErrorControlOperator) | **MR-007** |
| H-13 | `config/config.php:48` | Constante `CSRF_SECRET` con valor por defecto débil **que ningún código usa** (código muerto que da una falsa sensación de seguridad). | De estilo / Seguridad (falsa seguridad) | Mantenibilidad (analizabilidad) | Baja | `grep CSRF_SECRET` → 1 ocurrencia (la definición) | **MR-007** |
| H-14 | Números mágicos: `65536` (`dashboard.php:178`, `webhook_trigger.php:85`), `60000` (`config_db.php:284`), `CURLOPT_CONNECTTIMEOUT => 4`, `42000`, `1000`, `200`, `150`, `75`/`30` repetidos en README | Reglas de negocio y límites repartidos en literales. | De estilo | Mantenibilidad (modificabilidad) | Baja | Lectura del código | **MR-007** |
| H-15 | `config/config_db.php:57-85` · `handlers/upload_documento.php:55-75` · `panel/dashboard.php:444-450` · JS `EXT_PERMITIDAS` | Extensiones y tipos MIME permitidos definidos en **3 lugares** distintos. | De diseño | Mantenibilidad | Baja | Lectura del código | **MR-007** |
| H-16 | `config/config_db.php:51` `UPLOAD_DIR` fijo | El directorio de cargas no se puede cambiar por entorno; las pruebas E2E escriben en el `uploads/` real. | De diseño | Portabilidad (adaptabilidad) | Baja | Archivos de prueba en `uploads/` tras la suite E2E | **MR-008** |
| H-17 | `config/security.php:15` | La CSP del sitio público permite `'unsafe-inline'` en scripts, y el panel **no envía CSP**. Mitiga mal la XSS. | Seguridad | Seguridad (integridad) | Media | Lectura del código | **MR-009** (CSP estricta en el panel tras extraer su JS) |
| H-18 | `SQL/rubicam_db.sql` (datos de ejemplo) | Los NIT de ejemplo tienen dígitos de verificación incorrectos (`900123456-1`; el DV correcto es `8`) y nunca fueron validados con la regla que el propio panel aplica. | Lógico (calidad de datos) | Adecuación funcional | Baja | `Nit::digitoVerificacion('900123456')` → `8` | Observación (no se modifican datos del cliente) |
| H-19 | `README.md` §0 y §3 | La documentación se contradice: §0 dice que Tailwind ya no depende del CDN y §3 dice que se carga por CDN. | Documentación | Mantenibilidad (analizabilidad) | Baja | Lectura | Se actualiza en `docs/` y `README.md` |

### Observaciones que **no** se corrigen (decisión consciente)

* **Prefijo del código de DO** (`SUCURSAL+AÑO+RÉGIMEN`): el JavaScript lo *sugiere* pero el servidor solo valida el formato. Endurecerlo cambia una regla de negocio; se deja como pregunta abierta para el cliente.
* **Fecha de apertura ilegible en el semáforo**: `SemaforoDo` conserva la tolerancia original (se interpreta como «hoy»). La columna es `DATE NOT NULL` con modo estricto, por lo que no es alcanzable en la práctica; queda como prueba de caracterización.

## 4. Impacto en el negocio (resumen)

| Riesgo | Hallazgos | Consecuencia para Rubicam |
|---|---|---|
| Fuga de información de clientes y credenciales | H-05, H-06 | Pérdida de confianza, incumplimiento de la Ley 1581 de 2012 (protección de datos personales). |
| Datos de contacto inválidos en cotizaciones y DO | H-01, H-02, H-04 | Asesores que no pueden contactar al cliente; reprocesos. |
| Indisponibilidad por entradas abusivas | H-03, H-07 | Correo y servidor saturados. |
| Cambios caros y arriesgados | H-08 a H-15 | Cada ajuste a reglas DIAN exige tocar varios archivos sin red de seguridad (sin pruebas). |

## 5. Trazabilidad hacia el mantenimiento

Cada hallazgo se convierte en una **Solicitud de Modificación (MR)** clasificada según ISO/IEC/IEEE 14764:2022; ver `docs/04_PLAN_DE_MANTENIMIENTO.md` y el registro MR/PR.
