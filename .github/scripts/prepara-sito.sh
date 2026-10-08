#!/usr/bin/env bash
# Prepara la cartella da pubblicare su GitHub Pages.
#
# Uso: .github/scripts/prepara-sito.sh <cartella-destinazione> [base-path]
#   base-path: prefisso del sito quando è pubblicato in una sottocartella
#              (es. "/guido" per https://roxalberga.github.io/guido/).
#              Vuoto o "/" per il dominio principale.
#
# Il sito è una build statica (Vite) con percorsi assoluti (/assets, /img, ...):
# se Pages lo serve da una sottocartella, i percorsi vanno riscritti con il prefisso.
set -euo pipefail

DEST="${1:?cartella di destinazione mancante}"
BASE="${2:-}"
[ "$BASE" = "/" ] && BASE=""
BASE="${BASE%/}"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

rm -rf "$DEST"
mkdir -p "$DEST"

# Copia tutto tranne: git, workflow, backup e la sezione PHP
# (Pages non esegue PHP e pubblicherebbe i sorgenti, config.php compreso).
for voce in "$ROOT"/* "$ROOT"/.[!.]*; do
  nome="$(basename "$voce")"
  case "$nome" in
    .git|.github|_backup_originali|amministrazione-trasparente|README.md) continue ;;
  esac
  [ -e "$voce" ] && cp -R "$voce" "$DEST"/
done

# Pagina statica al posto della sezione PHP, così il link nel sito non dà 404.
mkdir -p "$DEST/amministrazione-trasparente"
cat > "$DEST/amministrazione-trasparente/index.html" <<HTML
<!doctype html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Amministrazione trasparente — Millenario della Notazione Guidoniana</title>
<style>
  body{margin:0;font-family:Georgia,serif;background:#f6f1e7;color:#2b2520;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px}
  main{max-width:36rem}
  h1{font-weight:normal;font-size:2rem;margin:0 0 .5rem}
  p{line-height:1.6}
  a{color:#8a1c1c}
</style>
</head>
<body>
<main>
  <h1>Amministrazione trasparente</h1>
  <p>Questa sezione sarà disponibile a breve.</p>
  <p><a href="${BASE}/">Torna al sito</a></p>
</main>
</body>
</html>
HTML

# Fallback per le rotte dell'app (vue-router in modalità history):
# GitHub Pages serve 404.html per ogni percorso sconosciuto.
cp "$DEST/index.html" "$DEST/404.html"

# Disattiva Jekyll: non serve e ignorerebbe file/cartelle con underscore.
touch "$DEST/.nojekyll"

if [ -n "$BASE" ]; then
  echo "Riscrivo i percorsi assoluti con prefisso '$BASE'"
  find "$DEST" -type f \( -name '*.html' -o -name '*.js' -o -name '*.css' \) -print0 \
    | xargs -0 perl -pi -e '
        my $b = $ENV{BASE};
        # riferimenti a cartelle/file del sito: "/img/x", `/assets/y`, url(/ts1/z)
        s!([`"'"'"'(])/(img|assets|ts1|ts2|amministrazione-trasparente|sitemap\.xml|robots\.txt|favicon\.svg|icons\.svg)\b!$1$b/$2!g;
        # base del router (createWebHistory("/"))
        s!(history:[\w\$]+\()`/`\)!$1`$b/`)!g;
        # helper di Vite per i chunk caricati a richiesta (assetsURL = "/" + dep)
        s!(=function\((\w+)\)\{return)`/`\+\2\}!$1`$b/`+$2}!g;
      '
fi

echo "Sito pronto in $DEST"
