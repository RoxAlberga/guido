<?php
/**
 * Amministrazione trasparente — AREA RISERVATA.
 * Accesso con nome utente e password (vedi config.php).
 * Chi è autenticato può: pubblicare un atto (titolo, descrizione, categoria,
 * data, uno o più allegati), modificarlo, aggiungere o rimuovere allegati,
 * eliminarlo e cambiare la password. Nessun database: tutto su file.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
at_avvia_sessione();

$errore = null;
$azione = (string) ($_POST['azione'] ?? '');

/* ---------------------------------------------------------------
   Login
   --------------------------------------------------------------- */
if ($azione === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $utente   = trim((string) ($_POST['utente'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (at_login_bloccato()) {
        $errore = 'Troppi tentativi non riusciti. Riprova tra ' . AT_BLOCCO_MINUTI . ' minuti.';
    } elseif ($utente === '' || $password === '') {
        $errore = 'Inserisci nome utente e password.';
    } elseif (at_verifica_credenziali($utente, $password)) {
        at_azzera_tentativi();
        session_regenerate_id(true);
        $_SESSION['at_utente'] = $utente;
        unset($_SESSION['at_csrf']);
        if (!at_password_personalizzata()) {
            at_flash('warn', 'Stai usando la password iniziale: per sicurezza impostane subito una nuova dal riquadro "Cambia password".');
        }
        header('Location: ' . AT_BASE . '/admin.php');
        exit;
    } else {
        at_registra_tentativo_fallito();
        $errore = 'Nome utente o password non corretti.';
    }
}

if (!at_autenticato()) {
    at_testata('Area riservata', 'Accesso riservato alla gestione dei documenti.', true);
    ?>
<header class="page-hero page-hero--compatta">
  <p class="page-hero__eyebrow"><?= e(AT_ENTE) ?></p>
  <h1 class="page-hero__title">Area <em>riservata</em></h1>
  <p class="page-hero__sub">Gestione dei documenti dell'Amministrazione trasparente</p>
</header>
<main>
  <section class="section section--pearl">
    <div class="section__inner">
      <div class="at-login">
        <div class="at-card">
          <h2 class="at-card__title">Accedi</h2>
          <p class="at-card__sub">Inserisci le credenziali per gestire i documenti.</p>
          <?php if ($errore): ?><div class="at-alert at-alert--err"><?= e($errore) ?></div><?php endif; ?>
          <form method="post" class="at-form" autocomplete="on">
            <input type="hidden" name="azione" value="login">
            <div class="at-field">
              <label for="utente">Nome utente</label>
              <input type="text" id="utente" name="utente" required autocomplete="username" autofocus>
            </div>
            <div class="at-field">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="at-form__actions">
              <button type="submit" class="at-btn">Entra</button>
              <a class="at-btn at-btn--ghost" href="<?= AT_BASE ?>/">Torna alla pagina pubblica</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>
    <?php
    at_pie();
    exit;
}

/* ---------------------------------------------------------------
   Caricamento di un singolo file: controlli e salvataggio.
   Restituisce i dati dell'allegato, oppure null e imposta $errore.
   --------------------------------------------------------------- */
function at_elabora_upload(array $f, string $data, ?string &$errore): ?array
{
    $msg_upload = [
        UPLOAD_ERR_INI_SIZE   => 'supera il limite consentito dal server',
        UPLOAD_ERR_FORM_SIZE  => 'supera il limite consentito',
        UPLOAD_ERR_PARTIAL    => 'è stato caricato solo parzialmente, riprova',
        UPLOAD_ERR_NO_TMP_DIR => 'cartella temporanea mancante sul server',
        UPLOAD_ERR_CANT_WRITE => 'impossibile scrivere sul server',
        UPLOAD_ERR_EXTENSION  => 'caricamento bloccato da un\'estensione del server',
    ];
    $nome_orig = (string) ($f['name'] ?? 'file');

    if ((int) $f['error'] !== UPLOAD_ERR_OK) {
        $errore = 'File "' . $nome_orig . '": ' . ($msg_upload[(int) $f['error']] ?? 'errore durante il caricamento') . '.';
        return null;
    }
    if ((int) $f['size'] > AT_MAX_MB * 1048576) {
        $errore = 'File "' . $nome_orig . '": supera la dimensione massima di ' . AT_MAX_MB . ' MB.';
        return null;
    }
    $ext = at_estensione($nome_orig);
    if (!in_array($ext, AT_ESTENSIONI, true)) {
        $errore = 'File "' . $nome_orig . '": tipo non ammesso. Formati consentiti: ' . strtoupper(implode(', ', AT_ESTENSIONI)) . '.';
        return null;
    }

    // Controllo del contenuto: blocco di script e pagine eseguibili.
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) finfo_file($fi, $f['tmp_name']);
            finfo_close($fi);
        }
    }
    $intestazione = (string) file_get_contents($f['tmp_name'], false, null, 0, 512);
    $pericoloso = preg_match('#^(text/html|text/x-php|application/x-httpd-php|application/x-php|text/x-shellscript|application/x-sh|application/x-executable|application/x-dosexec|application/x-perl|text/x-python|application/javascript|text/javascript)#i', $mime)
        || stripos($intestazione, '<?php') !== false
        || stripos($intestazione, '<script') !== false
        || strncmp($intestazione, '#!', 2) === 0;
    if ($pericoloso) {
        $errore = 'File "' . $nome_orig . '": il contenuto non corrisponde a un documento valido.';
        return null;
    }

    if (!is_dir(AT_DIR_FILE)) {
        @mkdir(AT_DIR_FILE, 0755, true);
    }
    $base = ($data !== '' ? $data : date('Y-m-d')) . '_' . at_slug(pathinfo($nome_orig, PATHINFO_FILENAME));
    $nome_salvato = $base . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], AT_DIR_FILE . '/' . $nome_salvato)) {
        $errore = 'Impossibile salvare "' . $nome_orig . '" sul server. Controlla i permessi della cartella "files".';
        return null;
    }
    @chmod(AT_DIR_FILE . '/' . $nome_salvato, 0644);

    return [
        'file'           => $nome_salvato,
        'nome_originale' => preg_replace('/[^\pL\pN ._()\-]/u', '_', $nome_orig),
        'dimensione'     => (int) $f['size'],
    ];
}

/** Riorganizza $_FILES['file'] (campo multiplo) in un elenco di file, saltando quelli vuoti. */
function at_file_inviati(): array
{
    if (empty($_FILES['file']) || !is_array($_FILES['file']['name'])) {
        return [];
    }
    $out = [];
    foreach ($_FILES['file']['name'] as $k => $nome) {
        $err = (int) $_FILES['file']['error'][$k];
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => $nome,
            'type'     => $_FILES['file']['type'][$k],
            'tmp_name' => $_FILES['file']['tmp_name'][$k],
            'error'    => $err,
            'size'     => $_FILES['file']['size'][$k],
        ];
    }
    return $out;
}

/* ---------------------------------------------------------------
   Azioni dell'utente autenticato (sempre via POST + token CSRF)
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $azione !== 'login') {
    if (!at_csrf_valido()) {
        at_flash('err', 'La sessione è scaduta o la richiesta non è valida. Riprova.');
        header('Location: ' . AT_BASE . '/admin.php');
        exit;
    }

    switch ($azione) {

        case 'logout':
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            header('Location: ' . AT_BASE . '/admin.php');
            exit;

        case 'carica':
        case 'aggiorna':
            $titolo      = trim((string) ($_POST['titolo'] ?? ''));
            $descrizione = trim(str_replace("\r", '', (string) ($_POST['descrizione'] ?? '')));
            $categoria   = (string) ($_POST['categoria'] ?? '');
            $data        = (string) ($_POST['data'] ?? '');
            $id          = (string) ($_POST['id'] ?? '');
            $rimuovi     = array_map('strval', (array) ($_POST['rimuovi'] ?? []));

            $docs  = at_carica_documenti();
            $index = $azione === 'aggiorna' ? at_trova_documento($docs, $id) : null;

            if ($azione === 'aggiorna' && $index === null) {
                at_flash('err', 'Documento non trovato.');
                header('Location: ' . AT_BASE . '/admin.php');
                exit;
            }

            $inviati = at_file_inviati();

            if ($titolo === '') {
                $errore = 'Il titolo è obbligatorio.';
            } elseif (mb_strlen($titolo) > 200) {
                $errore = 'Il titolo è troppo lungo (massimo 200 caratteri).';
            } elseif (mb_strlen($descrizione) > 10000) {
                $errore = 'La descrizione è troppo lunga (massimo 10.000 caratteri).';
            } elseif (!in_array($categoria, AT_CATEGORIE, true)) {
                $errore = 'Seleziona una categoria valida.';
            } elseif ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                $errore = 'La data non è valida.';
            } elseif ($azione === 'carica' && !$inviati) {
                $errore = 'Seleziona almeno un file da caricare.';
            }

            $nuovi = [];
            if ($errore === null) {
                foreach ($inviati as $f) {
                    $a = at_elabora_upload($f, $data, $errore);
                    if ($a === null) {
                        break;
                    }
                    $nuovi[] = $a;
                }
                if ($errore !== null) {
                    // Annulla i file già salvati in questa richiesta.
                    foreach ($nuovi as $a) {
                        at_elimina_file_allegato($a);
                    }
                }
            }

            if ($errore !== null) {
                $_SESSION['at_bozza'] = compact('titolo', 'descrizione', 'categoria', 'data', 'id');
                at_flash('err', $errore);
                header('Location: ' . AT_BASE . '/admin.php' . ($azione === 'aggiorna' ? '?modifica=' . rawurlencode($id) : '') . '#carica');
                exit;
            }

            if ($azione === 'carica') {
                $docs[] = [
                    'id'          => bin2hex(random_bytes(6)),
                    'titolo'      => $titolo,
                    'descrizione' => $descrizione,
                    'categoria'   => $categoria,
                    'data'        => $data,
                    'caricato_il' => date('c'),
                    'allegati'    => $nuovi,
                ];
                $esito = at_salva_documenti($docs);
                at_flash($esito ? 'ok' : 'err', $esito
                    ? 'Documento pubblicato: ora è visibile nella pagina Amministrazione trasparente.'
                    : 'Impossibile salvare l\'archivio dei documenti. Controlla i permessi della cartella "data".');
            } else {
                $doc = $docs[$index];
                $doc['titolo']        = $titolo;
                $doc['descrizione']   = $descrizione;
                $doc['categoria']     = $categoria;
                $doc['data']          = $data;
                $doc['modificato_il'] = date('c');

                $allegati = [];
                foreach ($doc['allegati'] as $a) {
                    if (in_array((string) $a['file'], $rimuovi, true)) {
                        at_elimina_file_allegato($a);
                    } else {
                        $allegati[] = $a;
                    }
                }
                $doc['allegati'] = array_merge($allegati, $nuovi);
                $docs[$index] = $doc;
                $esito = at_salva_documenti($docs);
                at_flash($esito ? 'ok' : 'err', $esito ? 'Documento aggiornato.' : 'Impossibile salvare le modifiche.');
            }
            header('Location: ' . AT_BASE . '/admin.php');
            exit;

        case 'elimina':
            $id    = (string) ($_POST['id'] ?? '');
            $docs  = at_carica_documenti();
            $index = at_trova_documento($docs, $id);
            if ($index === null) {
                at_flash('err', 'Documento non trovato.');
            } else {
                foreach ($docs[$index]['allegati'] as $a) {
                    at_elimina_file_allegato($a);
                }
                unset($docs[$index]);
                at_flash(at_salva_documenti($docs) ? 'ok' : 'err', 'Documento eliminato.');
            }
            header('Location: ' . AT_BASE . '/admin.php');
            exit;

        case 'password':
            $attuale  = (string) ($_POST['attuale'] ?? '');
            $nuova    = (string) ($_POST['nuova'] ?? '');
            $conferma = (string) ($_POST['conferma'] ?? '');
            if (!at_verifica_credenziali(AT_UTENTE, $attuale)) {
                at_flash('err', 'La password attuale non è corretta.');
            } elseif (strlen($nuova) < 10) {
                at_flash('err', 'La nuova password deve avere almeno 10 caratteri.');
            } elseif ($nuova !== $conferma) {
                at_flash('err', 'La conferma non coincide con la nuova password.');
            } elseif (!at_imposta_password($nuova)) {
                at_flash('err', 'Impossibile salvare la nuova password. Controlla i permessi della cartella "data".');
            } else {
                at_flash('ok', 'Password aggiornata. Usala dal prossimo accesso.');
            }
            header('Location: ' . AT_BASE . '/admin.php');
            exit;
    }
}

/* ---------------------------------------------------------------
   Pagina di gestione
   --------------------------------------------------------------- */
$flash     = at_leggi_flash();
$documenti = at_carica_documenti();
$csrf      = at_csrf_token();
$bozza     = $_SESSION['at_bozza'] ?? null;
unset($_SESSION['at_bozza']);

$modifica = null;
if (isset($_GET['modifica'])) {
    $i = at_trova_documento($documenti, (string) $_GET['modifica']);
    if ($i !== null) {
        $modifica = $documenti[$i];
    }
}
$form = [
    'titolo'      => $bozza['titolo']      ?? $modifica['titolo']      ?? '',
    'descrizione' => $bozza['descrizione'] ?? $modifica['descrizione'] ?? '',
    'categoria'   => $bozza['categoria']   ?? $modifica['categoria']   ?? AT_CATEGORIE[0],
    'data'        => $bozza['data']        ?? $modifica['data']        ?? date('Y-m-d'),
];
$password_iniziale = !at_password_personalizzata();

at_testata('Area riservata', 'Gestione dei documenti dell\'Amministrazione trasparente.', true);
?>
<header class="page-hero page-hero--compatta">
  <p class="page-hero__eyebrow"><?= e(AT_ENTE) ?></p>
  <h1 class="page-hero__title">Area <em>riservata</em></h1>
  <p class="page-hero__sub">Gestione dei documenti dell'Amministrazione trasparente</p>
</header>
<main>
  <section class="section section--pearl">
    <div class="section__inner">
      <div class="at-admin">

        <div class="at-toolbar">
          <span class="at-toolbar__user">Collegato come <strong><?= e((string) $_SESSION['at_utente']) ?></strong></span>
          <div class="at-toolbar__actions">
            <a class="at-btn at-btn--ghost at-btn--small" href="<?= AT_BASE ?>/" target="_blank" rel="noopener">Vedi pagina pubblica</a>
            <form method="post">
              <input type="hidden" name="azione" value="logout">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <button type="submit" class="at-btn at-btn--small">Esci</button>
            </form>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="at-alert at-alert--<?= e($flash['tipo']) ?>"><?= e($flash['testo']) ?></div>
        <?php elseif ($password_iniziale): ?>
          <div class="at-alert at-alert--warn">Stai usando la password iniziale: per sicurezza impostane una nuova dal riquadro "Cambia password" in fondo alla pagina.</div>
        <?php endif; ?>

        <!-- Pubblica / modifica documento -->
        <div class="at-card" id="carica">
          <h2 class="at-card__title"><?= $modifica ? 'Modifica documento' : 'Pubblica un nuovo documento' ?></h2>
          <p class="at-card__sub">
            <?= $modifica
                ? 'Aggiorna titolo, descrizione, categoria o data; puoi rimuovere allegati esistenti o aggiungerne di nuovi.'
                : 'Ogni documento (per esempio una riunione) ha una propria scheda pubblica con la descrizione completa e gli allegati scaricabili.' ?>
          </p>
          <form method="post" enctype="multipart/form-data" class="at-form">
            <input type="hidden" name="azione" value="<?= $modifica ? 'aggiorna' : 'carica' ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= AT_MAX_MB * 1048576 ?>">
            <?php if ($modifica): ?><input type="hidden" name="id" value="<?= e((string) $modifica['id']) ?>"><?php endif; ?>

            <div class="at-field">
              <label for="titolo">Titolo *</label>
              <input type="text" id="titolo" name="titolo" required maxlength="200" value="<?= e((string) $form['titolo']) ?>" placeholder="Es. Riunione del Comitato del 12 marzo 2026">
            </div>
            <div class="at-field">
              <label for="descrizione">Descrizione</label>
              <textarea id="descrizione" name="descrizione" maxlength="10000" rows="7" placeholder="Es. Ordine del giorno, presenti, decisioni assunte… (i paragrafi separati da una riga vuota vengono mantenuti)"><?= e((string) $form['descrizione']) ?></textarea>
              <small>Nell'elenco viene mostrato un estratto; nella scheda del documento il testo completo.</small>
            </div>
            <div class="at-form__row">
              <div class="at-field">
                <label for="categoria">Categoria *</label>
                <select id="categoria" name="categoria" required>
                  <?php foreach (AT_CATEGORIE as $c): ?>
                    <option value="<?= e($c) ?>"<?= $c === $form['categoria'] ? ' selected' : '' ?>><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="at-field">
                <label for="data">Data del documento / della riunione</label>
                <input type="date" id="data" name="data" value="<?= e((string) $form['data']) ?>">
              </div>
            </div>

            <?php if ($modifica && $modifica['allegati']): ?>
              <div class="at-field">
                <label>Allegati attuali</label>
                <ul class="at-allegati">
                  <?php foreach ($modifica['allegati'] as $a): ?>
                    <li>
                      <a href="<?= e(at_url_allegato($a)) ?>" target="_blank" rel="noopener"><?= e((string) ($a['nome_originale'] ?? $a['file'])) ?></a>
                      <span class="at-table__meta"><?= e(strtoupper(at_estensione((string) $a['file']))) ?><?php if (!empty($a['dimensione'])): ?> · <?= e(at_dimensione((int) $a['dimensione'])) ?><?php endif; ?></span>
                      <label class="at-check"><input type="checkbox" name="rimuovi[]" value="<?= e((string) $a['file']) ?>"> rimuovi</label>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <div class="at-field">
              <label for="file"><?= $modifica ? 'Aggiungi allegati' : 'Allegati *' ?></label>
              <input type="file" id="file" name="file[]" multiple <?= $modifica ? '' : 'required' ?> accept="<?= e('.' . implode(',.', AT_ESTENSIONI)) ?>">
              <small>Puoi selezionare più file insieme. Formati: <?= e(strtoupper(implode(', ', AT_ESTENSIONI))) ?> · massimo <?= AT_MAX_MB ?> MB ciascuno.</small>
            </div>
            <div class="at-form__actions">
              <button type="submit" class="at-btn"><?= $modifica ? 'Salva modifiche' : 'Pubblica documento' ?></button>
              <?php if ($modifica): ?>
                <a class="at-btn at-btn--ghost" href="<?= AT_BASE ?>/admin.php">Annulla</a>
                <a class="at-btn at-btn--ghost" href="<?= e(at_url_documento($modifica)) ?>" target="_blank" rel="noopener">Vedi scheda pubblica</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Elenco documenti -->
        <div class="at-card">
          <h2 class="at-card__title">Documenti pubblicati</h2>
          <p class="at-card__sub"><?= count($documenti) ?> <?= count($documenti) === 1 ? 'documento' : 'documenti' ?> in archivio, dal più recente.</p>
          <?php if (!$documenti): ?>
            <div class="at-empty">Nessun documento ancora pubblicato. Usa il modulo qui sopra per caricare il primo.</div>
          <?php else: ?>
            <table class="at-table">
              <thead>
                <tr><th>Data</th><th>Documento</th><th>Categoria</th><th>Allegati</th><th></th></tr>
              </thead>
              <tbody>
              <?php foreach ($documenti as $d): ?>
                <tr>
                  <td class="at-table__meta"><?= at_data_it((string) ($d['data'] ?? '')) ?: '—' ?></td>
                  <td>
                    <div class="at-table__title"><a href="<?= e(at_url_documento($d)) ?>" target="_blank" rel="noopener"><?= e((string) ($d['titolo'] ?? '')) ?></a></div>
                    <?php if (!empty($d['descrizione'])): ?><div class="at-table__desc"><?= e(mb_strimwidth((string) preg_replace('/\s+/', ' ', (string) $d['descrizione']), 0, 140, '…')) ?></div><?php endif; ?>
                  </td>
                  <td class="at-table__desc"><?= e((string) ($d['categoria'] ?? '')) ?></td>
                  <td class="at-table__meta"><?= count($d['allegati']) ?></td>
                  <td>
                    <div class="at-table__actions">
                      <a class="at-btn at-btn--ghost at-btn--small" href="<?= AT_BASE ?>/admin.php?modifica=<?= e(rawurlencode((string) $d['id'])) ?>#carica">Modifica</a>
                      <form method="post" onsubmit="return confirm('Eliminare definitivamente questo documento e i suoi allegati?');">
                        <input type="hidden" name="azione" value="elimina">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
                        <button type="submit" class="at-btn at-btn--danger at-btn--small">Elimina</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- Cambia password -->
        <div class="at-card" id="password">
          <h2 class="at-card__title">Cambia password</h2>
          <p class="at-card__sub">Almeno 10 caratteri. La password viene salvata in forma cifrata.</p>
          <form method="post" class="at-form" autocomplete="off">
            <input type="hidden" name="azione" value="password">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="at-field">
              <label for="attuale">Password attuale</label>
              <input type="password" id="attuale" name="attuale" required autocomplete="current-password">
            </div>
            <div class="at-form__row">
              <div class="at-field">
                <label for="nuova">Nuova password</label>
                <input type="password" id="nuova" name="nuova" required minlength="10" autocomplete="new-password">
              </div>
              <div class="at-field">
                <label for="conferma">Conferma nuova password</label>
                <input type="password" id="conferma" name="conferma" required minlength="10" autocomplete="new-password">
              </div>
            </div>
            <div class="at-form__actions">
              <button type="submit" class="at-btn at-btn--ghost">Aggiorna password</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </section>
</main>
<?php at_pie(); ?>
