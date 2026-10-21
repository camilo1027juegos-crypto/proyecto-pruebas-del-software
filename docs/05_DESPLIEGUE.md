# 05 · Despliegue, versionado y reversión

> **Principios aplicados (material de clase — *Principios de despliegue*):** control de versiones con Git, ramas con propósito,
> mensajes de commit estandarizados (Conventional Commits), automatización con integración continua, configuración fuera del
> código y **plan de reversión (rollback)** probado antes de necesitarlo.

## 1. Control de versiones

| Elemento | Convención |
|---|---|
| **Rama estable** | `main`: siempre desplegable. Solo recibe *merges* con la suite en verde. |
| **Ramas de trabajo** | `fix/mr-nnn-…` (corrección), `feature/mr-nnn-…` (función nueva), `refactor/…`, `test/…`, `docs/…`, `chore/…`. Una rama = una solicitud de modificación. |
| **Integración** | `git merge --no-ff`: conserva la rama como "burbuja" en el historial; revertir una MR completa es **un** comando. |
| **Mensajes** | [Conventional Commits](https://www.conventionalcommits.org/es/): `tipo(ámbito): asunto`. Tipos usados: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`. Los commits de TDD llevan sufijo `[RED]`, `[GREEN]` o `[REFACTOR]`. |
| **Versiones** | [SemVer](https://semver.org/lang/es/) con etiquetas anotadas: `v2.0.0` = línea base recibida; **`v2.1.0`** = esta entrega (funciones nuevas compatibles + correcciones). |
| **Historial** | `git log --oneline --graph --decorate` |
| **Cambios por versión** | `CHANGELOG.md` (formato *Keep a Changelog*). |

### Flujo de trabajo de una MR

```bash
git checkout main && git pull
git checkout -b fix/mr-007-nombre-corto

# 1) RED: escribir la prueba que falla y guardar la evidencia
phpunit --group MR-007            # debe FALLAR
git add -A && git commit -m "test(regresion): reproducir MR-007 [RED]"

# 2) GREEN: el cambio mínimo
phpunit                            # todo en verde
php tests/e2e/ejecutar.php         # E2E en verde
git add -A && git commit -m "fix(modulo): descripción (MR-007) [GREEN]"

# 3) Integrar
git checkout main
git merge --no-ff fix/mr-007-nombre-corto
git tag -a v2.1.1 -m "Corrección MR-007"
```

## 2. Compuertas de calidad (se ejecutan antes de integrar)

| Compuerta | Comando | Criterio |
|---|---|---|
| Estilo (PSR-12) | `composer lint` (`phpcs`) | 0 errores, 0 advertencias |
| Análisis estático | `composer analizar` (`phpmd`) | 0 hallazgos con `phpmd.xml` |
| Seguridad estática | `composer seguridad` | 0 hallazgos (umbral «medio») |
| Pruebas unitarias y de regresión | `composer test` | 100 % en verde |
| Cobertura | `composer test:cobertura` (requiere `pcov`) | informe; lógica pura ≥ 90 % |
| E2E | `composer e2e` (necesita MySQL/MariaDB) | 36/36 |
| Reglas de Apache | `bash tests/apache/verificar_htaccess.sh` (necesita Apache) | todas con el código esperado |

Todo lo anterior (salvo Apache) está automatizado en `.github/workflows/ci.yml` para PHP 8.2 y 8.3 con un servicio MariaDB.

## 3. Configuración por entorno

El código es **el mismo** en desarrollo, pruebas y producción; solo cambian las variables. Se leen de las variables de entorno del
servidor y, si existe, del archivo `.env` (que **nunca** se versiona; `.gitignore` y `.htaccess` lo protegen). Lo definido en el
servidor tiene prioridad sobre el archivo. Plantilla: `.env.example`.

| Variable | Para qué | Valor por defecto |
|---|---|---|
| `APP_ENV` | `development` muestra errores en pantalla; cualquier otro valor los oculta y los envía a `logs/php-error.log` | `production` |
| `APP_BASE_URL` | URL pública del sitio | `https://rubicam.co` |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | Conexión a MySQL/MariaDB | `127.0.0.1` `3306` `rubicam_db` `root` *(vacío)* |
| `N8N_WEBHOOK_URL` · `N8N_WEBHOOK_SECRET` · `N8N_CA_BUNDLE` | Alertas a n8n | URL de la nube de n8n · vacío · vacío |
| `RUBI_WEBHOOK_URL` | Chat «Rubi» del sitio público | vacío (responde con mensaje de cortesía) |
| `UPLOAD_DIR` | Carpeta de documentos del legajo (absoluta, o relativa a la raíz) | `uploads/` |

> **Producción:** use una contraseña de BD propia (no `root` sin clave) y cambie las contraseñas del usuario de ejemplo del panel.

## 4. Despliegue paso a paso (servidor Apache + PHP 8.2+ + MySQL/MariaDB)

1. **Respaldo previo** (siempre): `mysqldump -u USUARIO -p rubicam_db > respaldo_AAAAMMDD_HHMM.sql` y copia de `uploads/`.
2. **Obtener la versión**: `git fetch --tags && git checkout v2.1.0` (o descomprimir el `.zip` de la entrega).
3. **Configurar**: `cp .env.example .env` y completar los valores. Verificar que `uploads/` y `logs/` sean escribibles por el usuario de Apache.
4. **Base de datos**: instalación nueva → importar `SQL/rubicam_db.sql`. Instalación existente → **esta versión no cambia el esquema**; solo si viene de una anterior a la v2 ejecutar una vez `SQL/migracion_panel_v2.sql`.
5. **Apache**: `mod_rewrite` y `mod_headers` activos; `AllowOverride All` en la carpeta del sitio (para que se lea `.htaccess`).
6. **Verificación de humo** (2 minutos):
   * `https://su-sitio/` carga y el cotizador responde.
   * `https://su-sitio/panel/` muestra el login; ingresar con un usuario de prueba, listar DO, abrir el legajo.
   * **Las reglas de acceso:** `https://su-sitio/SQL/rubicam_db.sql`, `/README.md`, `/composer.json`, `/src/Dominio/Nit.php`, `/.env`, `/logs/php-error.log` y `/uploads/<archivo>` deben responder **403**.
7. **Etiquetar el despliegue**: anotar versión, fecha y responsable en el registro de cambios.

## 5. Plan de reversión (rollback)

**Esta versión no modifica el esquema de la base de datos ni los datos**, por lo que revertir es solo volver al código anterior.
Es la razón por la que el diseño evita cambios de esquema "de paso": mientras el esquema no cambie, el rollback es instantáneo y sin pérdida de datos.

| Situación | Acción | Tiempo |
|---|---|---|
| La versión nueva falla en producción | `git checkout v2.0.0` (etiqueta de la versión anterior) y reiniciar Apache. Conservar `.env` y `uploads/` (no están en Git). | minutos |
| Una MR concreta causa el problema | `git revert -m 1 <hash del merge de esa MR>` y desplegar el commit resultante; el resto de la versión se conserva. Hashes en `docs/04_PLAN_DE_MANTENIMIENTO.md` §4. | minutos |
| Se alteraron datos por error | Restaurar el respaldo del paso 1: `mysql -u USUARIO -p rubicam_db < respaldo_AAAAMMDD_HHMM.sql` | depende del tamaño |
| **Futuro:** una versión que sí cambie el esquema | Debe traer un script `SQL/rollback_<versión>.sql` que deshaga el cambio, **probado sobre una copia** antes del despliegue, y el respaldo del paso 1 es obligatorio. | — |

Después de revertir: ejecutar la verificación de humo del paso 6, abrir un *problema* (PR) con la causa y reproducirlo con una prueba antes de volver a intentar.

## 6. Integración continua

`.github/workflows/ci.yml` (GitHub Actions) ejecuta en cada *push* a `main`/`release/**` y en cada *pull request*:

1. **calidad** (PHP 8.2 y 8.3): `composer install` → `composer lint` → `composer analizar` → `composer seguridad` → `composer test:cobertura`.
2. **e2e** (PHP 8.3 + servicio MariaDB 10.11): `php tests/e2e/ejecutar.php`.

Si cualquier paso falla, el *pull request* no se debe integrar. **Honestidad:** el flujo está escrito y revisado, pero no se pudo ejecutar en GitHub desde el entorno de desarrollo (sin acceso a Packagist/GitHub); las mismas comprobaciones se ejecutaron a mano con las herramientas del sistema y su resultado está en `docs/evidencias/`.

## 7. Cómo publicar el repositorio

```bash
git remote add origin https://github.com/<usuario>/rubicam-redesign.git
git push -u origin main --follow-tags       # sube main y las etiquetas
git push origin --all                         # sube también las ramas de trabajo (el historial TDD)
```

## 8. Depuración (herramienta de apoyo del desarrollo)

`.vscode/launch.json` trae tres configuraciones para **Xdebug** (puerto 9003): escuchar el panel en `php -S`, depurar el script
abierto y depurar la prueba PHPUnit abierta. Pasos: instalar Xdebug (`xdebug.mode=debug`, `xdebug.start_with_request=yes`), poner un
punto de interrupción en la línea sospechosa, ejecutar la prueba y examinar variables / pila de llamadas. Ver el caso de estudio
en `docs/INFORME_TECNICO.md` §10.
