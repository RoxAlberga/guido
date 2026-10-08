<?php
/**
 * Amministrazione trasparente — CONFIGURAZIONE
 * ------------------------------------------------------------
 * Questo è l'unico file da modificare per adattare l'area riservata.
 * Non serve alcun database: i dati stanno in data/documenti.json,
 * i file caricati in files/.
 */

// Nome utente per l'accesso all'area riservata.
define('AT_UTENTE', 'admin');

// Password INIZIALE. Vale solo finché non viene cambiata dall'area
// riservata (voce "Cambia password"). Dopo il primo cambio, la password
// viene salvata cifrata in data/auth.json e questa riga viene ignorata.
define('AT_PASSWORD_INIZIALE', 'Guido1025!Trasparenza');

// Dimensione massima di ogni file caricato, in megabyte.
// Nota: deve essere compatibile con i limiti PHP del server
// (upload_max_filesize e post_max_size).
define('AT_MAX_MB', 20);

// Estensioni dei file ammesse.
define('AT_ESTENSIONI', ['pdf', 'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']);

// Categorie in cui organizzare i documenti (in questo ordine).
// L'ultima è usata come categoria predefinita per i documenti senza categoria.
define('AT_CATEGORIE', [
    'Verbali delle riunioni',
    'Atti e delibere',
    'Bilanci e rendiconti',
    'Bandi, avvisi e incarichi',
    'Altri documenti',
]);

// Numero massimo di elementi per pagina nell'elenco pubblico.
define('AT_PER_PAGINA', 15);

// Protezione contro i tentativi di accesso ripetuti.
define('AT_MAX_TENTATIVI', 5);   // tentativi falliti consentiti...
define('AT_BLOCCO_MINUTI', 15);  // ...in questo intervallo di minuti.

// Percorso pubblico della cartella (senza barra finale).
define('AT_BASE', '/amministrazione-trasparente');

// Testi della pagina pubblica.
define('AT_ENTE', 'Comitato Nazionale per le Celebrazioni del Millenario della Notazione Guidoniana');
define('AT_INTRO', 'In questa sezione il Comitato pubblica i verbali delle proprie riunioni e gli atti di interesse pubblico, nel rispetto dei principi di trasparenza e di accessibilità delle informazioni. I documenti sono consultabili e scaricabili liberamente.');
