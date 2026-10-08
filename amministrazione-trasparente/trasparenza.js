/* Navbar: stesso comportamento del sito (trasparente in alto, "perla" allo scroll)
   e menu a tendina per schermi piccoli. */
(function () {
  var nav = document.getElementById('navbar');
  var toggle = document.getElementById('nav-toggle');
  var drawer = document.getElementById('nav-drawer');
  var logoMic = document.getElementById('logo-mic');
  if (!nav) return;

  function aggiorna() {
    var scrolled = window.scrollY > 60;
    nav.classList.toggle('navbar--scrolled', scrolled);
    nav.classList.toggle('navbar--top', !scrolled);
    if (logoMic) {
      var src = scrolled ? logoMic.getAttribute('data-scrolled') : logoMic.getAttribute('data-top');
      if (src && logoMic.getAttribute('src') !== src) logoMic.setAttribute('src', src);
    }
  }
  window.addEventListener('scroll', aggiorna, { passive: true });
  aggiorna();

  if (toggle && drawer) {
    toggle.addEventListener('click', function () {
      var aperto = drawer.hasAttribute('hidden');
      if (aperto) drawer.removeAttribute('hidden'); else drawer.setAttribute('hidden', '');
      toggle.classList.toggle('nav-toggle--open', aperto);
      toggle.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    });
    drawer.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        drawer.setAttribute('hidden', '');
        toggle.classList.remove('nav-toggle--open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }
})();
