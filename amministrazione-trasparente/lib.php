<?php
/**
 * Amministrazione trasparente — funzioni comuni
 * (archiviazione su file JSON, autenticazione, layout).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

define('AT_DIR_DATI', __DIR__ . '/data');
define('AT_DIR_FILE', __DIR__ . '/files');
define('AT_FILE_DOCUMENTI', AT_DIR_DATI . '/documenti.json');
define('AT_FILE_AUTH', AT_DIR_DATI . '/auth.json');
define('AT_FILE_TENTATIVI', AT_DIR_DATI . '/tentativi.json');

/* ---------------------------------------------------------------
   Utilità generali
   --------------------------------------------------------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function at_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function at_avvia_sessione(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('AT_SESSIONE');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => AT_BASE . '/',
        'secure'   => at_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function at_leggi_json(string $file, $default)
{
    if (!is_file($file)) {
        return $default;
    }
    $raw = file_get_contents($file);
    $dati = json_decode((string) $raw, true);
    return is_array($dati) ? $dati : $default;
}

function at_scrivi_json(string $file, $dati): bool
{
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }
    $json = json_encode($dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $file);
}

function at_slug(string $s): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') {
        $s = $t;
    }
    $s = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $s));
    $s = trim($s, '-');
    $s = substr($s, 0, 60);
    return $s !== '' ? $s : 'documento';
}

function at_dimensione(int $byte): string
{
    if ($byte >= 1048576) {
        return number_format($byte / 1048576, 1, ',', '.') . ' MB';
    }
    if ($byte >= 1024) {
        return number_format($byte / 1024, 0, ',', '.') . ' KB';
    }
    return $byte . ' byte';
}

function at_data_it(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    if (!$ts) {
        return e($iso);
    }
    $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
             'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    return (int) date('j', $ts) . ' ' . $mesi[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function at_estensione(string $nome): string
{
    return strtolower(pathinfo($nome, PATHINFO_EXTENSION));
}

function at_etichetta_tipo(string $ext): string
{
    switch ($ext) {
        case 'pdf':  return 'PDF';
        case 'doc':
        case 'docx':
        case 'odt':
        case 'rtf':  return 'Word';
        case 'xls':
        case 'xlsx':
        case 'ods':  return 'Excel';
        case 'ppt':
        case 'pptx': return 'Slide';
        case 'jpg':
        case 'jpeg':
        case 'png':  return 'Immagine';
        case 'zip':  return 'Archivio';
        default:     return strtoupper($ext);
    }
}

/* ---------------------------------------------------------------
   Documenti (archivio JSON)
   --------------------------------------------------------------- */

function at_normalizza_documento(array $d): array
{
    // Compatibilità: un documento con un singolo campo "file" diventa un documento con un allegato.
    if (!isset($d['allegati']) || !is_array($d['allegati'])) {
        $d['allegati'] = [];
        if (!empty($d['file'])) {
            $d['allegati'][] = [
                'file'           => (string) $d['file'],
                'nome_originale' => (string) ($d['nome_originale'] ?? $d['file']),
                'dimensione'     => (int) ($d['dimensione'] ?? 0),
            ];
        }
    }
    unset($d['file'], $d['nome_originale'], $d['dimensione']);
    return $d;
}

function at_carica_documenti(): array
{
    $docs = at_leggi_json(AT_FILE_DOCUMENTI, []);
    $docs = array_map('at_normalizza_documento', array_values($docs));
    usort($docs, function ($a, $b) {
        $cmp = strcmp((string) ($b['data'] ?? ''), (string) ($a['data'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string) ($b['caricato_il'] ?? ''), (string) ($a['caricato_il'] ?? ''));
    });
    return $docs;
}

function at_salva_documenti(array $docs): bool
{
    return at_scrivi_json(AT_FILE_DOCUMENTI, array_values($docs));
}

function at_trova_documento(array $docs, string $id): ?int
{
    foreach ($docs as $i => $d) {
        if (($d['id'] ?? '') === $id) {
            return $i;
        }
    }
    return null;
}

function at_url_allegato(array $allegato): string
{
    return AT_BASE . '/files/' . rawurlencode((string) ($allegato['file'] ?? ''));
}

function at_url_documento(array $doc): string
{
    return AT_BASE . '/documento/' . rawurlencode((string) ($doc['id'] ?? '')) . '/' . at_slug((string) ($doc['titolo'] ?? ''));
}

function at_url_elenco(string $categoria = '', int $pagina = 1): string
{
    $q = [];
    if ($categoria !== '') {
        $q['categoria'] = $categoria;
    }
    if ($pagina > 1) {
        $q['pagina'] = $pagina;
    }
    return AT_BASE . '/' . ($q ? '?' . http_build_query($q) : '') . ($q ? '#documenti' : '');
}

function at_elimina_file_allegato(array $allegato): void
{
    $nome = basename((string) ($allegato['file'] ?? ''));
    if ($nome !== '' && is_file(AT_DIR_FILE . '/' . $nome)) {
        @unlink(AT_DIR_FILE . '/' . $nome);
    }
}

/* ---------------------------------------------------------------
   Autenticazione
   --------------------------------------------------------------- */

function at_password_personalizzata(): bool
{
    $auth = at_leggi_json(AT_FILE_AUTH, null);
    return is_array($auth) && !empty($auth['hash']);
}

function at_verifica_credenziali(string $utente, string $password): bool
{
    if (!hash_equals(AT_UTENTE, $utente)) {
        return false;
    }
    $auth = at_leggi_json(AT_FILE_AUTH, null);
    if (is_array($auth) && !empty($auth['hash'])) {
        return password_verify($password, (string) $auth['hash']);
    }
    return hash_equals(AT_PASSWORD_INIZIALE, $password);
}

function at_imposta_password(string $password): bool
{
    return at_scrivi_json(AT_FILE_AUTH, [
        'hash'          => password_hash($password, PASSWORD_DEFAULT),
        'aggiornata_il' => date('c'),
    ]);
}

function at_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function at_tentativi(): array
{
    $t = at_leggi_json(AT_FILE_TENTATIVI, []);
    $soglia = time() - AT_BLOCCO_MINUTI * 60;
    foreach ($t as $ip => $lista) {
        $lista = array_values(array_filter((array) $lista, function ($ts) use ($soglia) {
            return (int) $ts > $soglia;
        }));
        if ($lista) {
            $t[$ip] = $lista;
        } else {
            unset($t[$ip]);
        }
    }
    return $t;
}

function at_login_bloccato(): bool
{
    $t = at_tentativi();
    return count($t[at_ip()] ?? []) >= AT_MAX_TENTATIVI;
}

function at_registra_tentativo_fallito(): void
{
    $t = at_tentativi();
    $t[at_ip()][] = time();
    at_scrivi_json(AT_FILE_TENTATIVI, $t);
}

function at_azzera_tentativi(): void
{
    $t = at_tentativi();
    unset($t[at_ip()]);
    at_scrivi_json(AT_FILE_TENTATIVI, $t);
}

function at_autenticato(): bool
{
    return !empty($_SESSION['at_utente']);
}

function at_csrf_token(): string
{
    if (empty($_SESSION['at_csrf'])) {
        $_SESSION['at_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['at_csrf'];
}

function at_csrf_valido(): bool
{
    return isset($_POST['csrf']) && hash_equals(at_csrf_token(), (string) $_POST['csrf']);
}

function at_flash(string $tipo, string $testo): void
{
    $_SESSION['at_flash'] = ['tipo' => $tipo, 'testo' => $testo];
}

function at_leggi_flash(): ?array
{
    if (empty($_SESSION['at_flash'])) {
        return null;
    }
    $f = $_SESSION['at_flash'];
    unset($_SESSION['at_flash']);
    return $f;
}

/* ---------------------------------------------------------------
   Layout: testata (navbar) e piè di pagina (footer),
   con la stessa struttura e le stesse classi del sito.
   --------------------------------------------------------------- */

function at_testata(string $titolo, string $descrizione, bool $noindex = false): void
{
    $voci = [
        ['/#home',       'Home'],
        ['/#comitato',   'Comitato'],
        ['/#millenario', 'Millenario'],
        ['/#info',       'Mostra'],
        ['/#contatti',   'Contatti'],
        [AT_BASE . '/',  'Trasparenza', true],
    ];
    ?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/svg+xml" href="/img/LOGO_V3R.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titolo) ?> — Guido d'Arezzo, Mostra del Millenario</title>
  <meta name="description" content="<?= e($descrizione) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <link rel="stylesheet" href="<?= AT_BASE ?>/trasparenza.css">
</head>
<body>
<nav class="navbar navbar--top" id="navbar">
  <a href="/" class="nav-logo">
    <img src="/img/LOGO_V3R.svg" alt="Logo Guido d'Arezzo" class="nav-logo__img" onerror="this.style.display='none'">
    <img src="/img/logomic-white.png" alt="Logo Mic" class="nav-logo__img nav-logo__img--mic" id="logo-mic" data-top="/img/logomic-white.png" data-scrolled="/img/logomic.PNG" onerror="this.style.display='none'">
  </a>
  <ul class="nav-links">
    <?php foreach ($voci as $i => $v): ?>
      <?php if ($i > 0): ?><li class="nav-sep" aria-hidden="true">·</li><?php endif; ?>
      <li><a href="<?= e($v[0]) ?>"<?= !empty($v[2]) ? ' class="is-active"' : '' ?>><?= e($v[1]) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="nav-actions">
    <button class="nav-toggle" id="nav-toggle" aria-label="Apri menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
<div class="nav-drawer" id="nav-drawer" hidden>
  <?php foreach ($voci as $v): ?>
    <a href="<?= e($v[0]) ?>"<?= !empty($v[2]) ? ' class="is-active"' : '' ?>><?= e($v[1]) ?></a>
  <?php endforeach; ?>
</div>
    <?php
}

function at_pie(): void
{
    ?>
<footer class="footer">
  <a href="/" class="footer__logo">
    <img src="/img/LOGO_V3B.svg" alt="Logo Mostra" class="footer__logo-img" onerror="this.style.display='none'">
    <img src="/img/logomic-white.png" alt="Logo Mic" class="footer__logo-img" onerror="this.style.display='none'">
    <div class="footer__logo-text">La Mano Guidoniana <small>Il primo Software Musicale</small></div>
  </a>
  <p class="footer__copy">© 2026 Comitato Nazionale per le Celebrazioni del Millenario della Notazione Guidoniana — Tutti i diritti riservati</p>
  <div class="footer__credits">
    <span class="footer__author">realizzato da AND - Ambienti Narrativi Digitali</span>
    <a href="https://www.ambientinarratividigitali.it/" target="_blank" rel="noopener" class="footer__agency-link">
      <img src="/img/logo_and.svg" alt="Logo Sviluppatore" class="footer__agency-logo">
    </a>
  </div>
</footer>
<script src="<?= AT_BASE ?>/trasparenza.js"></script>
</body>
</html>
    <?php
}
