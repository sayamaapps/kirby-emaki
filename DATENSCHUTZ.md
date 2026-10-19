# Emaki: technische Grundlage für den Datenschutztext

(English version: [PRIVACY.md](PRIVACY.md). Optionen heißen `emaki.*`; die
Namen stehen im [README](README.md#configuration).)

Dieser Baustein beschreibt die Implementierung. Er ist keine Rechtsberatung;
ob und wie ein Bildbetrachter in der Datenschutzerklärung erwähnt wird,
entscheidet der Betreiber. In der Regel ist **kein eigener Absatz nötig**,
weil Emaki nichts verarbeitet, was nicht ohnehin beim Aufruf der Seite
anfällt.

## Was Emaki tut

- Der Bildbetrachter besteht aus einer Skript- und einer Stildatei, die
  **vom eigenen Server** der Website ausgeliefert werden
  (`/media/plugins/sayamaapps/emaki/…`).
- Emaki lädt nur, was im Markup des Betreibers verlinkt ist: das geöffnete
  Bild und seine beiden Nachbarn, die im Voraus geladen werden. Verlinkt eine
  Seite ein Bild auf einem fremden Host, lädt der Browser es von dort, beim
  Öffnen wie beim Vorladen. Bilder der eigenen Galerie in einer gerechneten
  Größe bleiben beim Aufruf auf dem eigenen Server.
- Emaki setzt **keine Cookies** und speichert **nichts im Browser** (kein
  Local Storage, kein Session Storage, keine IndexedDB).
- Emaki **überträgt keine Daten** und führt keine eigene Zählung. Es löst im
  Browser lediglich ein Ereignis `emaki:open` aus, auf das eine Website mit
  einer eigenen Zählung reagieren *kann*. Ob eine solche Zählung stattfindet
  und was sie erfasst, beschreibt dann der Baustein des jeweiligen
  Zählwerkzeugs (zum Beispiel Kizami), nicht dieser hier.
- Emaki beachtet die Einstellung „Bewegung reduzieren“ des Betriebssystems
  und liest sonst keine Geräteeigenschaften aus.

## Vorschlag für einen Satz (falls gewünscht)

Zum Vergrößern von Bildern verwenden wir einen Bildbetrachter, der
vollständig von unserem eigenen Server geladen wird. Dabei werden keine
Cookies gesetzt, keine Daten in Ihrem Browser gespeichert und keine Daten an
Dritte übermittelt, solange die Bilder auf unserem eigenen Server liegen.
