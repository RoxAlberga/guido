# Mostra del Millenario di Guido d'Arezzo — sito

Sito del Comitato Nazionale per le Celebrazioni del Millenario della Notazione Guidoniana.
È una build statica (Vue + Vite) già compilata: `index.html`, `assets/`, `img/`, `ts1/`, `ts2/`.

## Pubblicazione online (GitHub Pages)

Il sito si pubblica da solo con GitHub Actions (`.github/workflows/pubblica-sito.yml`)
a ogni push sul branch `main`.

**Da fare una volta sola**, su GitHub:

1. Portare queste modifiche su `main` (merge del branch).
2. Settings → Pages → *Build and deployment* → Source: **GitHub Actions**.
   (Il workflow prova ad attivarlo da solo; se il primo avvio fallisce, basta questo passaggio
   e poi Actions → "Pubblica sito" → *Run workflow*.)

Dopo il primo avvio il sito è raggiungibile su **https://roxalberga.github.io/guido/**
(l'indirizzo esatto compare in Settings → Pages e nel riepilogo del workflow).

### Dominio personalizzato (www.millenarionotazioneguidoniana.it)

Per servire il sito dal dominio del Comitato:

1. Dal pannello DNS del dominio, creare un record `CNAME` per `www` che punta a `roxalberga.github.io`.
2. Su GitHub, Settings → Pages → *Custom domain*: inserire `www.millenarionotazioneguidoniana.it`
   e salvare; attivare *Enforce HTTPS* quando il certificato è pronto (pochi minuti).

Con il dominio personalizzato il sito è servito dalla radice (`/`), quindi i percorsi
non vengono riscritti: lo script lo gestisce da solo in base all'indirizzo.

## Come funziona la pubblicazione

`.github/scripts/prepara-sito.sh` copia i file in una cartella di pubblicazione e:

- esclude `_backup_originali/` e la sezione PHP `amministrazione-trasparente/`
  (GitHub Pages non esegue PHP e pubblicherebbe i sorgenti, `config.php` compreso);
  al suo posto mette una pagina statica "sezione in arrivo";
- crea `404.html` uguale a `index.html`, così le rotte dell'app (`/touchDoc`, `/touchMano`)
  funzionano anche aprendole direttamente;
- se il sito è in una sottocartella (es. `/guido/`), riscrive i percorsi assoluti
  (`/assets`, `/img`, `/ts1`, `/ts2`, base del router e dei chunk Vite).

Per provare in locale:

```bash
.github/scripts/prepara-sito.sh /tmp/sito /guido
cd /tmp && python3 -m http.server 8000   # poi aprire http://localhost:8000/sito/
```

## Amministrazione trasparente (PHP)

La cartella `amministrazione-trasparente/` richiede Apache + PHP 7.4+ (vedi `ISTRUZIONI.txt`)
e non può girare su GitHub Pages. Va caricata su un hosting con PHP, oppure riscritta come
sezione statica.
