# Contao Backend Suite

Backend-Werkzeuge für **Contao 5** — ein Nachbau von [OM-Backend](https://github.com/OMOSde/contao-om-backend) (das nur bis Contao 4.x läuft), Feature für Feature.

Ein Projekt von [Futura Web](https://futura-web.de).

## Installation

### Aus diesem Repository (empfohlen)

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/futura-web/contao-backend-suite" }
]
```

```bash
composer require futuraweb/contao-backend-suite
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear
```

### Als lokales Paket

```json
"repositories": [
    { "type": "path", "url": "packages/contao-backend-suite" }
]
```

```bash
composer require futuraweb/contao-backend-suite:@dev
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear
```

## Features

Die Features werden nacheinander gebaut. Stand:

- [x] **Style-Selector** — benannte CSS-Klassen, die Redakteure an beliebigen Stellen auswählen können (Content-Elemente, Artikel, Module, Formulare, Seiten, Layouts, ...). Nach dem Vorbild von [craffft/css-style-selector-bundle](https://github.com/Craffft/css-style-selector-bundle), aber mit zwei Verbesserungen:
  - **Automatische Erkennung**: Jede Tabelle mit einem `cssID`- oder `cssClass`-Feld bekommt den Style-Selector automatisch eingehängt — keine eigene DCA-Datei pro Tabelle nötig.
  - **Dynamische Verfügbarkeit**: Statt fixer Checkboxen pro Tabelle (`disableInArticle`, `disableInContent`, ...) wird die Liste der verfügbaren Tabellen zur Laufzeit ermittelt.
- [x] **Toolbar-Quicklinks** — Quicklinks zu Modulen, Seitenlayouts und Bildgrößen des aktuellen Themes direkt im Backend-Kopfmenü, plus Projekt-Umschalter bei mehreren Themes (siehe unten). Kein `tl_style_sheet`-Quicklink, da Contao 5 den internen CSS-Editor nicht mehr hat.
- [x] **Bild-Shrinker** — Bilder beim Hochladen automatisch komprimieren und Dateinamen bereinigen (siehe unten).
- [x] **Sprache bei Startpunkten** — zeigt den Sprachcode einer Startseite (`type = root`) direkt im Seitenbaum an, z. B. „Startseite [de]".
- [x] **ID-/Alias-Suche** — die Seiten-ID steht direkt neben dem Titel im Seitenbaum (z. B. „Startseite [ID: 1]"); der Seitenalias ist bereits über Contao-Core im Backend-Suchfeld durchsuchbar.
- [x] **IDs bei Shift anzeigen** — die Seiten-ID (siehe ID-/Alias-Suche) ist standardmäßig ausgeblendet und erscheint nur, solange die Umschalttaste (Shift) gedrückt gehalten wird.
- [x] **Textfeld-Längenanzeige** — unter Seitentitel und Beschreibung (`pageTitle`/`description`) zeigt ein Live-Zähler die Zeichenanzahl, farblich markiert gegen die von Google üblicherweise angezeigte Länge (Titel 40–60, Beschreibung 120–160 Zeichen).
- [~] Markdown-Viewer — nicht relevant, wird nicht gebaut (kein Markdown-Einsatz in den Projekten).
- [ ] Backend-Tabs
- [x] Backend-Links (Kurzlinks) — bereits durch Contao-Core abgedeckt: "URL als Favorit speichern" + die **Favoriten**-Verwaltung (`do=favorites`), kein eigener Code nötig.
- [~] User-Redirects nach Login — nicht gewünscht, Standardverhalten (Dashboard) bleibt.
- [x] Layout-Button in der Seitenstruktur — nicht nötig, bereits durch den "Seitenlayouts"-Quicklink in der Toolbar abgedeckt (siehe oben).
- [x] **Speichern-Buttons ohne Untermenü** — "Speichern und neu" / "Speichern und duplizieren" stehen als eigene, immer sichtbare Buttons da statt hinter dem Dropdown-Pfeil versteckt zu sein.
- [ ] Backend Full-Width
- [ ] Reihenfolge der Backend-Module einstellbar
- [ ] Neueste Contao-Version anzeigen

### Style-Selector: Benutzung

1. Backend → **Backend-Suite → Style-Selector** → neuen Style anlegen.
2. **Bezeichnung** vergeben (das sieht der Redakteur), z. B. "Rahmen dünn".
3. **CSS-Klasse(n)** eintragen, die beim Auswählen gesetzt werden, z. B. `border-thin`.
4. Unter **Verfügbar in** die Bereiche ankreuzen, in denen der Style wählbar sein soll (z. B. Inhaltselemente, Artikel).
5. In jedem passenden Formular (überall, wo es ein `cssID`- oder `cssClass`-Feld gibt) erscheint automatisch ein Mehrfachauswahlfeld "Style-Klassen". Die gewählten Styles werden beim Speichern in die vorhandene CSS-Klassen-Spalte eingemischt, bereits vorhandene, manuell eingetragene Klassen bleiben erhalten.

### Toolbar: Quicklinks & Projekt-Umschalter

Bei OM-Backend stapeln sich in der Toolbar die Theme-Buttons (Stylesheets, Module, Seitenlayouts, Bildgrößen) für **jedes** vorhandene Theme untereinander. In Mehrprojekt-Installationen wird das schnell unübersichtlich.

Hier zeigt das Backend-Kopfmenü stattdessen nur die Buttons des **aktuell erkannten** Themes ("Projekt"):

- **Automatische Erkennung**: Sobald ein Modul, ein Seitenlayout, eine Bildgröße oder das Theme selbst bearbeitet bzw. aufgerufen wird, merkt sich die Session das zugehörige Theme automatisch.
- **Umschalter**: Gibt es mehr als ein Theme, erscheint am Ende der Quicklinks ein Menüpunkt mit dem Namen des aktuellen Themes, der beim Hovern/Klicken alle anderen Themes als Auswahl anzeigt. Die Auswahl gilt für die laufende Session, bis automatisch oder manuell erneut gewechselt wird.
- Kein `tl_style_sheet`-Quicklink: Contao 5 hat den klassischen internen CSS-Editor nicht mehr, nur noch echte CSS-Dateien.

### Bild-Shrinker: Komprimierung & Dateinamen-Bereinigung

Im Dateimanager gibt es einen Schalter ("Bilder verkleinern: An/Aus") in der Werkzeugleiste. Der Zustand wird pro Theme ("Projekt") gespeichert, nicht global oder pro Sitzung.

Ist der Schalter für das aktuelle Projekt aktiviert, passiert bei jedem Bild-Upload (JPEG, PNG, WebP; GIFs werden wegen möglicher Animation übersprungen) automatisch:

- **Komprimierung**: Bilder über 1 MB werden ohne Änderung der Abmessungen stärker komprimiert, um die Dateigröße zu senken.
- **Dateiname bereinigen**: Kleinschreibung, Leerzeichen → Unterstrich, Umlaute/Sonderzeichen werden transliteriert (`ä` → `ae`, `ö` → `oe`, `ü` → `ue`, `ß` → `ss`, sonstige Akzente via Transliteration), und ein `.min`-Marker wird vor der Dateiendung eingefügt (`Mein Büro Foto.JPG` → `mein_buero_foto.min.jpg`). Bereits bereinigte Namen bleiben beim erneuten Durchlauf unverändert.

## Technik

- Symfony-Bundle für Contao 5, `#[AsHook]`/`#[AsCallback]`-Attribute statt klassischer Callback-Arrays.
- Style-Selector-Daten liegen in `tl_style_selector`.
- Das injizierte Auswahlfeld heißt `backendSuiteStyleSelector` und wird dynamisch in jede passende Tabelle eingehängt (eigene `blob`-Spalte je Tabelle, automatisch per `contao:migrate` angelegt).

## Namenskonvention

Felder, die dieses Bundle in fremde Tabellen einhängt (z. B. `tl_content`, die nicht zu diesem Bundle gehören), bekommen einheitlich das Präfix `backendSuite` + Feature-Name in camelCase — so sind Kollisionen mit anderen Bundles ausgeschlossen und erkennbar, woher ein Feld stammt. Eigene Tabellen und Backend-Module (z. B. `tl_style_selector`, `style_selector`) folgen dagegen der normalen, unpräfixierten Contao-Konvention.
