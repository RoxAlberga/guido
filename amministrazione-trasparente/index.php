<?php
/**
 * Amministrazione trasparente — ELENCO PUBBLICO.
 * Mostra i documenti pubblicati dall'area riservata (admin.php),
 * con filtro per categoria e paginazione (AT_PER_PAGINA elementi per pagina).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$tutti     = at_carica_documenti();
$categoria = (string) ($_GET['categoria'] ?? '');
if (!in_array($categoria, AT_CATEGORIE, true)) {
    $categoria = '';
}

$filtrati = $categoria === ''
    ? $tutti
    : array_values(array_filter($tutti, function ($d) use ($categoria) {
        return (string) ($d['categoria'] ?? '') === $categoria;
    }));

$totale   = count($filtrati);
$pagine   = max(1, (int) ceil($totale / AT_PER_PAGINA));
$pagina   = min($pagine, max(1, (int) ($_GET['pagina'] ?? 1)));
$elementi = array_slice($filtrati, ($pagina - 1) * AT_PER_PAGINA, AT_PER_PAGINA);

$conteggi = [];
foreach ($tutti as $d) {
    $c = (string) ($d['categoria'] ?? '');
    $conteggi[$c] = ($conteggi[$c] ?? 0) + 1;
}

at_testata(
    'Amministrazione trasparente' . ($categoria !== '' ? ' — ' . $categoria : '') . ($pagina > 1 ? ' — pagina ' . $pagina : ''),
    'Verbali delle riunioni, atti e documenti pubblici del ' . AT_ENTE . '.'
);
?>
<header class="page-hero" id="home">
  <p class="page-hero__eyebrow"><?= e(AT_ENTE) ?></p>
  <h1 class="page-hero__title">Amministrazione <em>trasparente</em></h1>
  <p class="page-hero__sub">Verbali, atti e documenti pubblici del Comitato</p>
</header>

<main>
  <section class="section section--pearl" id="documenti">
    <div class="section__inner">
      <p class="at-intro"><?= e(AT_INTRO) ?></p>
      <div class="ornament"><span>✦</span></div>

      <nav class="at-filtri" aria-label="Filtra per categoria">
        <a href="<?= e(at_url_elenco()) ?>" class="at-chip<?= $categoria === '' ? ' is-active' : '' ?>">Tutti <span><?= count($tutti) ?></span></a>
        <?php foreach (AT_CATEGORIE as $c): ?>
          <a href="<?= e(at_url_elenco($c)) ?>" class="at-chip<?= $categoria === $c ? ' is-active' : '' ?>"><?= e($c) ?> <span><?= $conteggi[$c] ?? 0 ?></span></a>
        <?php endforeach; ?>
      </nav>

      <p class="at-risultati">
        <?php if ($totale === 0): ?>
          Nessun documento<?= $categoria !== '' ? ' in questa categoria' : ' pubblicato al momento' ?>.
        <?php elseif ($totale === 1): ?>
          1 documento
        <?php else: ?>
          <?= $totale ?> documenti<?= $pagine > 1 ? ', pagina ' . $pagina . ' di ' . $pagine : '' ?>
        <?php endif; ?>
      </p>

      <?php if ($totale === 0): ?>
        <div class="at-empty">I documenti saranno pubblicati in questa pagina non appena disponibili.</div>
      <?php else: ?>
        <div class="at-elenco">
          <?php foreach ($elementi as $d): ?>
            <?php
              $url  = at_url_documento($d);
              $nAll = count($d['allegati']);
              $ext  = $nAll === 1 ? at_estensione((string) $d['allegati'][0]['file']) : '';
            ?>
            <article class="at-doc">
              <a class="at-doc__type" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                <?= $nAll === 1 ? e(at_etichetta_tipo($ext)) : ($nAll === 0 ? 'Atto' : $nAll . ' file') ?>
              </a>
              <div class="at-doc__body">
                <p class="at-doc__date">
                  <?= at_data_it((string) ($d['data'] ?? '')) ?>
                  <?php if (!empty($d['categoria'])): ?><span class="at-doc__cat">· <?= e((string) $d['categoria']) ?></span><?php endif; ?>
                </p>
                <h2 class="at-doc__title"><a href="<?= e($url) ?>"><?= e((string) ($d['titolo'] ?? 'Documento')) ?></a></h2>
                <?php if (!empty($d['descrizione'])): ?>
                  <p class="at-doc__desc"><?= e(mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', (string) $d['descrizione'])), 0, 220, '…')) ?></p>
                <?php endif; ?>
                <p class="at-doc__meta">
                  <?= $nAll === 0 ? 'Nessun allegato' : ($nAll === 1 ? '1 allegato' : $nAll . ' allegati') ?>
                </p>
              </div>
              <div class="at-doc__actions">
                <a class="at-btn" href="<?= e($url) ?>">
                  Apri
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($pagine > 1): ?>
          <nav class="at-paginazione" aria-label="Pagine">
            <?php if ($pagina > 1): ?>
              <a class="at-pag at-pag--arrow" href="<?= e(at_url_elenco($categoria, $pagina - 1)) ?>" rel="prev">‹ Precedente</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $pagine; $p++): ?>
              <?php if ($p === $pagina): ?>
                <span class="at-pag is-active" aria-current="page"><?= $p ?></span>
              <?php else: ?>
                <a class="at-pag" href="<?= e(at_url_elenco($categoria, $p)) ?>"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagina < $pagine): ?>
              <a class="at-pag at-pag--arrow" href="<?= e(at_url_elenco($categoria, $pagina + 1)) ?>" rel="next">Successiva ›</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>

      <p class="at-riservata"><a href="<?= AT_BASE ?>/admin.php">Area riservata</a></p>
    </div>
  </section>
</main>
<?php at_pie(); ?>
