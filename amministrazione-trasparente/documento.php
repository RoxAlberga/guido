<?php
/**
 * Amministrazione trasparente — SCHEDA DEL SINGOLO DOCUMENTO
 * (titolo, data, categoria, descrizione completa, allegati scaricabili).
 * URL: /amministrazione-trasparente/documento/<id>/<titolo>  (o documento.php?id=<id>)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$id   = (string) ($_GET['id'] ?? '');
$docs = at_carica_documenti();
$i    = $id !== '' ? at_trova_documento($docs, $id) : null;

if ($i === null) {
    http_response_code(404);
    at_testata('Documento non trovato', 'Il documento richiesto non esiste o è stato rimosso.', true);
    ?>
<header class="page-hero page-hero--compatta">
  <p class="page-hero__eyebrow"><?= e(AT_ENTE) ?></p>
  <h1 class="page-hero__title">Documento <em>non trovato</em></h1>
</header>
<main>
  <section class="section section--pearl">
    <div class="section__inner">
      <div class="at-empty">Il documento richiesto non esiste o è stato rimosso.</div>
      <p class="at-riservata"><a href="<?= AT_BASE ?>/">Torna all'elenco dei documenti</a></p>
    </div>
  </section>
</main>
    <?php
    at_pie();
    exit;
}

$doc        = $docs[$i];
$precedente = $docs[$i + 1] ?? null; // più vecchio
$successivo = $docs[$i - 1] ?? null; // più recente
$descr      = trim((string) ($doc['descrizione'] ?? ''));
$riassunto  = $descr !== '' ? mb_strimwidth((string) preg_replace('/\s+/', ' ', $descr), 0, 160, '…') : 'Documento pubblicato dal ' . AT_ENTE . '.';

at_testata((string) $doc['titolo'], $riassunto);
?>
<header class="page-hero page-hero--compatta">
  <p class="page-hero__eyebrow"><?= e((string) ($doc['categoria'] ?? 'Amministrazione trasparente')) ?></p>
  <h1 class="page-hero__title page-hero__title--doc"><?= e((string) $doc['titolo']) ?></h1>
  <?php if (!empty($doc['data'])): ?><p class="page-hero__sub"><?= at_data_it((string) $doc['data']) ?></p><?php endif; ?>
</header>

<main>
  <section class="section section--pearl">
    <div class="section__inner">
      <div class="at-scheda">
        <p class="at-breadcrumb">
          <a href="<?= e(at_url_elenco()) ?>">Amministrazione trasparente</a>
          <?php if (!empty($doc['categoria'])): ?> › <a href="<?= e(at_url_elenco((string) $doc['categoria'])) ?>"><?= e((string) $doc['categoria']) ?></a><?php endif; ?>
        </p>

        <div class="at-scheda__grid">
          <div class="at-scheda__testo">
            <?php if ($descr !== ''): ?>
              <?php foreach (preg_split('/\n\s*\n/', str_replace("\r", '', $descr)) as $par): ?>
                <p><?= nl2br(e(trim($par))) ?></p>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="at-scheda__vuoto">Nessuna descrizione disponibile per questo documento.</p>
            <?php endif; ?>
          </div>

          <aside class="at-scheda__info">
            <div class="info-item">
              <span class="info-item__label">Data</span>
              <span class="info-item__value"><?= !empty($doc['data']) ? at_data_it((string) $doc['data']) : '—' ?></span>
            </div>
            <div class="info-item">
              <span class="info-item__label">Categoria</span>
              <span class="info-item__value"><?= e((string) ($doc['categoria'] ?? '—')) ?></span>
            </div>
            <div class="info-item">
              <span class="info-item__label">Pubblicato il</span>
              <span class="info-item__value"><?= !empty($doc['caricato_il']) ? at_data_it((string) $doc['caricato_il']) : '—' ?></span>
            </div>
            <?php if (!empty($doc['modificato_il'])): ?>
            <div class="info-item">
              <span class="info-item__label">Ultimo aggiornamento</span>
              <span class="info-item__value"><?= at_data_it((string) $doc['modificato_il']) ?></span>
            </div>
            <?php endif; ?>
          </aside>
        </div>

        <h2 class="at-scheda__h2">Allegati <span class="at-categoria__count"><?= count($doc['allegati']) ?></span></h2>
        <div class="at-categoria__rule"></div>
        <?php if (!$doc['allegati']): ?>
          <div class="at-empty">Nessun allegato per questo documento.</div>
        <?php else: ?>
          <?php foreach ($doc['allegati'] as $a): ?>
            <?php $ext = at_estensione((string) $a['file']); ?>
            <div class="at-doc at-doc--allegato">
              <div class="at-doc__type" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                <?= e(at_etichetta_tipo($ext)) ?>
              </div>
              <div class="at-doc__body">
                <h3 class="at-doc__title at-doc__title--file"><?= e((string) ($a['nome_originale'] ?? $a['file'])) ?></h3>
                <p class="at-doc__meta"><?= e(strtoupper($ext)) ?><?php if (!empty($a['dimensione'])): ?> · <?= e(at_dimensione((int) $a['dimensione'])) ?><?php endif; ?></p>
              </div>
              <div class="at-doc__actions">
                <a class="at-btn at-btn--ghost at-btn--small" href="<?= e(at_url_allegato($a)) ?>" target="_blank" rel="noopener">Apri</a>
                <a class="at-btn at-btn--small" href="<?= e(at_url_allegato($a)) ?>" download="<?= e((string) ($a['nome_originale'] ?? $a['file'])) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Scarica
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <nav class="at-prevnext" aria-label="Altri documenti">
          <?php if ($successivo): ?>
            <a class="at-prevnext__link" href="<?= e(at_url_documento($successivo)) ?>" rel="prev">
              <span class="at-prevnext__label">‹ Più recente</span>
              <span class="at-prevnext__title"><?= e((string) $successivo['titolo']) ?></span>
            </a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($precedente): ?>
            <a class="at-prevnext__link at-prevnext__link--right" href="<?= e(at_url_documento($precedente)) ?>" rel="next">
              <span class="at-prevnext__label">Precedente ›</span>
              <span class="at-prevnext__title"><?= e((string) $precedente['titolo']) ?></span>
            </a>
          <?php endif; ?>
        </nav>

        <p class="at-riservata"><a href="<?= e(at_url_elenco()) ?>">← Torna all'elenco dei documenti</a></p>
      </div>
    </div>
  </section>
</main>
<?php at_pie(); ?>
