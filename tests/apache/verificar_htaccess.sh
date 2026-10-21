#!/usr/bin/env bash
# Verifica CONTRA UN APACHE REAL las reglas de .htaccess (MR-005, casos CP-SEG-01…10 de docs/02_CASOS_DE_PRUEBA.md).
# El servidor embebido de PHP ignora .htaccess, por eso esta comprobación no puede hacerse con PHPUnit.
#
# Uso (Linux, con apache2 instalado):   bash tests/apache/verificar_htaccess.sh
# Otra copia del proyecto (p. ej. la línea base):  RAIZ_PROYECTO=/ruta/a/copia bash tests/apache/verificar_htaccess.sh
# En XAMPP/Windows: repita los mismos GET a mano (la tabla está en docs/02_CASOS_DE_PRUEBA.md, sección 5).
set -u
RAIZ="${RAIZ_PROYECTO:-$(cd "$(dirname "$0")/../.." && pwd)}"
TMP="$(mktemp -d /tmp/rubicam_apache.XXXXXX)"
PUERTO="${PUERTO:-8081}"
DOCROOT="$TMP/sitio"
mkdir -p "$DOCROOT" "$TMP/logs" "$TMP/run"

# Copia del proyecto (con .git y archivos de ejemplo en uploads/ y logs/) legible por el usuario de Apache.
cp -a "$RAIZ/." "$DOCROOT/"
mkdir -p "$DOCROOT/uploads" "$DOCROOT/logs"
printf '%%PDF-1.4 prueba\n' > "$DOCROOT/uploads/CUC26IMP0001_FACTURA_prueba.pdf"
printf 'registro de prueba\n' > "$DOCROOT/logs/php-error.log"
chmod -R a+rX "$TMP"

cat > "$TMP/apache.conf" <<CONF
ServerRoot "$TMP"
PidFile "$TMP/run/apache.pid"
Listen 127.0.0.1:$PUERTO
ErrorLog "$TMP/logs/error.log"
LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
LoadModule mime_module /usr/lib/apache2/modules/mod_mime.so
LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so
LoadModule alias_module /usr/lib/apache2/modules/mod_alias.so
LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so
LoadModule headers_module /usr/lib/apache2/modules/mod_headers.so
LoadModule autoindex_module /usr/lib/apache2/modules/mod_autoindex.so
LoadModule filter_module /usr/lib/apache2/modules/mod_filter.so
LoadModule deflate_module /usr/lib/apache2/modules/mod_deflate.so
TypesConfig /etc/mime.types
User www-data
Group www-data
DocumentRoot "$DOCROOT"
DirectoryIndex index.html
<Directory "$DOCROOT">
    AllowOverride All
    Require all granted
</Directory>
CONF

apache2 -f "$TMP/apache.conf" -k start || { echo "No se pudo iniciar Apache"; exit 2; }
trap 'apache2 -f "$TMP/apache.conf" -k stop >/dev/null 2>&1; rm -rf "$TMP"' EXIT
for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PUERTO/" && break; sleep 0.2; done

fallos=0
verificar() { # id, ruta, codigo esperado
    local codigo
    codigo="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PUERTO$2")"
    if [ "$codigo" = "$3" ]; then echo "[PASS] $1 GET $2 -> $codigo"; else echo "[FAIL] $1 GET $2 -> $codigo (esperado $3)"; fallos=$((fallos + 1)); fi
}

verificar CP-SEG-01 /SQL/rubicam_db.sql 403
verificar CP-SEG-01 /sql/rubicam_db.sql 403
verificar CP-SEG-02 /README.md 403
verificar CP-SEG-02 /docs/02_CASOS_DE_PRUEBA.md 403
verificar CP-SEG-03 /composer.json 403
verificar CP-SEG-03 /package-lock.json 403
verificar CP-SEG-04 /src/Dominio/Nit.php 403
verificar CP-SEG-04 /tests/bootstrap.php 403
verificar CP-SEG-04 /tools/escanear_seguridad.php 403
verificar CP-SEG-05 /.git/config 403
verificar CP-SEG-06 /config/config.php 403
verificar CP-SEG-06 /config/arranque.php 403
verificar CP-SEG-06 /logs/php-error.log 403
verificar CP-SEG-07 /uploads/CUC26IMP0001_FACTURA_prueba.pdf 403
verificar CP-SEG-08 /phpunit.xml.dist 403
verificar CP-SEG-08 /phpcs.xml 403
verificar CP-SEG-08 /.github/workflows/ci.yml 403
verificar CP-SEG-08 /.env.example 403
verificar CP-SEG-08 /tailwind.config.js 403
verificar CP-SEG-09 /assets/css/tailwind.build.css 200
verificar CP-SEG-09 /assets/js/panel.js 200

echo
if [ "$fallos" -eq 0 ]; then echo "Resultado Apache: todas las comprobaciones correctas."; else echo "Resultado Apache: $fallos fallo(s)."; fi
exit "$fallos"
