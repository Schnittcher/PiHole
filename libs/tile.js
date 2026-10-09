// Gemeinsame Funktionen der Kacheln

/** Schriftgröße der Kachel an ihre Größe anpassen, alle em-Angaben skalieren mit. cb(breite, höhe) läuft danach. */
function scaleTile(tile, widthDiv, heightDiv, cb) {
  if (typeof ResizeObserver === 'undefined') { return; }
  new ResizeObserver(function (e) {
    var bs = e[0].borderBoxSize && e[0].borderBoxSize[0];
    var w = bs ? bs.inlineSize : tile.offsetWidth, h = bs ? bs.blockSize : tile.offsetHeight;
    tile.style.fontSize = Math.max(9, Math.min(18, Math.min(w / widthDiv, h / heightDiv))).toFixed(2) + 'px';
    if (cb) { cb(w, h); }
  }).observe(tile);
}

/** Große Zahlen kurz: 12 345 bleibt, ab 10 000 "12 k", ab einer Million "1.2 M". */
function shortNum(n) {
  n = Number(n || 0);
  return n >= 1e6 ? (n / 1e6).toFixed(1) + ' M' : n >= 1e4 ? Math.round(n / 1e3) + ' k' : n.toLocaleString();
}

/** Dauer in Sekunden als "3d 4h", "5h 12m" oder "7m". */
function duration(sec) {
  sec = Math.max(0, Math.floor(Number(sec || 0)));
  var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
  return d > 0 ? d + 'd ' + h + 'h' : h > 0 ? h + 'h ' + m + 'm' : m + 'm';
}

/** Farbe für Auslastungen: grün, orange ab warn, rot ab bad. */
function levelColor(value, warn, bad) {
  return value >= bad ? 'var(--bad)' : value >= warn ? 'var(--warn)' : 'var(--ok)';
}
