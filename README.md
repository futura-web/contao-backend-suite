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
- [~] Backend Full-Width — nicht nötig, im c5-Projekt bereits vorhanden/gegeben.
- [~] Reihenfolge der Backend-Module einstellbar — nicht gewünscht.
- [x] Neueste Contao-Version anzeigen — bereits durch den Contao Manager abgedeckt (Versionsnummer + "neuste Version"-Hinweis bei jedem Update-Check), kein eigener Code nötig.
- [x] **Ordner-Namensbereinigung** — neu angelegte und umbenannte Ordner im Dateimanager werden wie Datei-Uploads bereinigt (siehe unten).
- [x] **Projekt-Theme-Konfiguration** — Rahmen, Header, Hauptnavigation und Schriftgrößen/Abstände direkt im Theme-Formular pflegen, statt SCSS-Dateien von Hand zu editieren (siehe unten).

### Style-Selector: Benutzung

1. Backend → **Backend-Suite → Style-Selector** → neuen Style anlegen.
2. **Bezeichnung** vergeben (das sieht der Redakteur), z. B. "Rahmen dünn".
3. **CSS-Klasse(n)** eintragen, die beim Auswählen gesetzt werden, z. B. `border-thin`.
4. Unter **Verfügbar in** die Bereiche ankreuzen, in denen der Style wählbar sein soll (z. B. Inhaltselemente, Artikel).
5. In jedem passenden Formular (überall, wo es ein `cssID`- oder `cssClass`-Feld gibt) erscheint automatisch ein Mehrfachauswahlfeld "Style-Klassen". Die gewählten Styles werden beim Speichern in die vorhandene CSS-Klassen-Spalte eingemischt, bereits vorhandene, manuell eingetragene Klassen bleiben erhalten.

### Toolbar: Quicklinks

Bei OM-Backend stapeln sich in der Toolbar die Theme-Buttons (Stylesheets, Module, Seitenlayouts, Bildgrößen) für **jedes** vorhandene Theme untereinander. In Mehrprojekt-Installationen wird das schnell unübersichtlich.

- **Nur ein Theme**: Jedes Quicklink-Icon (Module, Seitenlayouts, Bildgrößen) ist ein direkter Link — genau wie bisher.
- **Mehrere Themes**: Jedes Icon wird stattdessen zu einem Hover-Menü mit allen Projekten darunter:
  - **Module**: pro Projekt ein Link zur Modul-Liste (ein Theme kann sehr viele Module haben, eine Einzelauflistung wäre unübersichtlich).
  - **Seitenlayouts** und **Bildgrößen**: pro Projekt werden die einzelnen Einträge direkt verlinkt (z. B. jedes Layout einzeln) — bei leerem Projekt fällt es auf einen Link zur Liste zurück.
- Kein `tl_style_sheet`-Quicklink: Contao 5 hat den klassischen internen CSS-Editor nicht mehr, nur noch echte CSS-Dateien.

### Bild-Shrinker: Komprimierung & Dateinamen-Bereinigung

Im Dateimanager gibt es einen Schalter ("Bilder beim Hochladen verkleinern & umbenennen") im Theme-Bearbeiten-Formular. Der Zustand wird pro Theme ("Projekt") gespeichert, nicht global oder pro Sitzung.

Ist der Schalter für das aktuelle Projekt aktiviert, passiert bei **jedem** Datei-Upload automatisch:

- **Dateiname bereinigen** (alle Dateitypen): Kleinschreibung, Leerzeichen → Unterstrich, Umlaute/Sonderzeichen werden transliteriert (`ä` → `ae`, `ö` → `oe`, `ü` → `ue`, `ß` → `ss`, sonstige Akzente via Transliteration). Bereits bereinigte Namen bleiben beim erneuten Durchlauf unverändert.
- **Komprimierung** (nur Bilder: JPEG, PNG, WebP; GIFs werden wegen möglicher Animation übersprungen): Bilder über 1 MB werden ohne Änderung der Abmessungen stärker komprimiert. Nur bei tatsächlich komprimierten Bildern wird zusätzlich ein `.min`-Marker vor der Dateiendung eingefügt (`Mein Büro Foto.JPG` → `mein_buero_foto.min.jpg`), um zu kennzeichnen, dass die Datei verkleinert wurde — bei anderen Dokumenten (PDF, ZIP, ...) entfällt dieser Marker, da nichts komprimiert wurde (`Rechnung Januar.pdf` → `rechnung_januar.pdf`).

### Ordner-Namensbereinigung

Neu angelegte Ordner und Umbenennungen bestehender Ordner im Dateimanager werden automatisch nach demselben Schema bereinigt wie Datei-Uploads (Kleinschreibung, Umlaute, Leerzeichen → Unterstrich) — allerdings **ohne** `.min`-Marker. Gilt immer, unabhängig vom Bild-Shrinker-Schalter.

Technische Besonderheit: Contao ruft den Rename-Mechanismus (und damit auch diese Bereinigung) nur auf, wenn sich der Namensfeld-Wert beim Speichern tatsächlich ändert — ein erneutes Speichern ohne Textänderung bewirkt nichts (Contao-Core-Verhalten, siehe `DC_Folder::save()`).

### Projekt-Theme-Konfiguration

Im Theme-Bearbeiten-Formular gibt es zusätzliche Abschnitte, mit denen sich zentrale SCSS-Variablen direkt pflegen lassen, statt die Dateien von Hand zu editieren:

- **Rahmen**: Rahmenfarbe (+ Fokus/Fehler), -stärke, -art, Eckenradius → schreibt nach `_vars_borders.scss`.
- **Header**: Höhe, Logohöhe, Hamburger-Icon-Farbe → schreibt nach `_vars_header.scss`.
- **Hauptnavi**: Einflug-Richtung, Hintergrund, Schriftgröße/-stärke, Farben (inkl. Hover/Aktiv/Trail) → schreibt nach `_nav_main.scss` / `_vars_header.scss` / `_vars_colors.scss`.
- **Schriftgrößen**: Größe und Abstand für Fließtext sowie H1–H6 (jeweils als responsiver `clamp()`-Wert aus Start-/Endgröße) → schreibt nach `_vars_typography.scss`.

Leere Felder lassen den bestehenden SCSS-Wert unangetastet (kein Überschreiben). Die Farb-Dropdowns beziehen ihre Optionen aus den im Feld "Farben" (siehe oben) hinterlegten Variablen; Eckenradius/Abstände aus den `$clampXXpx`-Variablen in `_vars_sizes.scss`.

Der Projekt-Ordner (unter `files/public/<slug>/...`) wird dynamisch über das Theme selbst ermittelt (`tl_theme.templates`), ist also nicht fest verdrahtet — funktioniert automatisch für jedes Theme, das dieser SCSS-Struktur folgt. Beim allerersten Umbenennen eines frisch aus dem Boilerplate-Ordner `project` erzeugten Themes werden `templates/project` und `files/public/project` zudem automatisch in einen aus dem Theme-Namen abgeleiteten Ordnernamen umbenannt.

## Technik

- Symfony-Bundle für Contao 5, `#[AsHook]`/`#[AsCallback]`-Attribute statt klassischer Callback-Arrays.
- Style-Selector-Daten liegen in `tl_style_selector`.
- Das injizierte Auswahlfeld heißt `backendSuiteStyleSelector` und wird dynamisch in jede passende Tabelle eingehängt (eigene `blob`-Spalte je Tabelle, automatisch per `contao:migrate` angelegt).

## Namenskonvention

Felder, die dieses Bundle in fremde Tabellen einhängt (z. B. `tl_content`, die nicht zu diesem Bundle gehören), bekommen einheitlich das Präfix `backendSuite` + Feature-Name in camelCase — so sind Kollisionen mit anderen Bundles ausgeschlossen und erkennbar, woher ein Feld stammt. Eigene Tabellen und Backend-Module (z. B. `tl_style_selector`, `style_selector`) folgen dagegen der normalen, unpräfixierten Contao-Konvention.

Ausnahme: die Felder der **Projekt-Theme-Konfiguration** (Rahmen/Header/Hauptnavi/Schriftgrößen) folgen der älteren `project`-Konvention statt `backendSuite`, da dieses Feature aus einem projekt-lokalen `contao/dca/tl_theme.php` ins Bundle migriert wurde und die bestehenden Feldnamen/Datenbankspalten (mit bereits gepflegten Werten) unverändert übernommen wurden.

## Voraussetzungen

Die SCSS-Projektstruktur (`files/public/<slug>/theme/css/...`) wird von diesem Bundle vorausgesetzt (Schriftarten/Farben-Import, Projekt-Theme-Konfiguration) — sie stammt aus dem Futura-Web-eigenen Theme-Boilerplate, ist aber kein Contao-Standard. In einem neuen Projekt ohne diese Struktur bleiben die betroffenen Formularfelder einfach wirkungslos (kein Fehler), bis die passenden SCSS-Dateien unter diesem Pfad existieren.
