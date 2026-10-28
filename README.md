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
composer require futura/contao-backend-suite
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
composer require futura/contao-backend-suite:@dev
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear
```

## Features

Die Features werden nacheinander gebaut. Stand:

- [x] **Style-Selector** — benannte CSS-Klassen, die Redakteure an beliebigen Stellen auswählen können (Content-Elemente, Artikel, Module, Formulare, Seiten, Layouts, ...). Nach dem Vorbild von [craffft/css-style-selector-bundle](https://github.com/Craffft/css-style-selector-bundle), aber mit zwei Verbesserungen:
  - **Automatische Erkennung**: Jede Tabelle mit einem `cssID`- oder `cssClass`-Feld bekommt den Style-Selector automatisch eingehängt — keine eigene DCA-Datei pro Tabelle nötig.
  - **Dynamische Verfügbarkeit**: Statt fixer Checkboxen pro Tabelle (`disableInArticle`, `disableInContent`, ...) wird die Liste der verfügbaren Tabellen zur Laufzeit ermittelt.
- [ ] Toolbar (inkl. Projekt-Umschalter für Mehrprojekt-Installationen, siehe unten)
- [ ] Sprache bei Startpunkten
- [ ] ID-/Alias-Suche
- [ ] IDs bei Shift anzeigen
- [ ] Textfeld-Längenanzeige
- [ ] Markdown-Viewer
- [ ] Backend-Tabs
- [ ] Backend-Links (Kurzlinks)
- [ ] User-Redirects nach Login
- [ ] Layout-Button in der Seitenstruktur
- [ ] Speichern-Buttons ohne Untermenü
- [ ] Backend Full-Width
- [ ] Reihenfolge der Backend-Module einstellbar
- [ ] Neueste Contao-Version anzeigen

### Style-Selector: Benutzung

1. Backend → **Backend-Suite → Style-Selector** → neuen Style anlegen.
2. **Bezeichnung** vergeben (das sieht der Redakteur), z. B. "Rahmen dünn".
3. **CSS-Klasse(n)** eintragen, die beim Auswählen gesetzt werden, z. B. `border-thin`.
4. Unter **Verfügbar in** die Bereiche ankreuzen, in denen der Style wählbar sein soll (z. B. Inhaltselemente, Artikel).
5. In jedem passenden Formular (überall, wo es ein `cssID`- oder `cssClass`-Feld gibt) erscheint automatisch ein Mehrfachauswahlfeld "Style-Klassen". Die gewählten Styles werden beim Speichern in die vorhandene CSS-Klassen-Spalte eingemischt, bereits vorhandene, manuell eingetragene Klassen bleiben erhalten.

### Toolbar: Projekt-Umschalter (geplant)

Bei OM-Backend stapeln sich in der Toolbar die Theme-Buttons (Stylesheets, Module, Seitenlayouts, Bildgrößen) für **jedes** vorhandene Theme untereinander. In Mehrprojekt-Installationen wird das schnell unübersichtlich.

Geplante Lösung: ein manueller Umschalter in der Toolbar, mit dem das aktive Projekt (Theme) ausgewählt wird. Die Auswahl wird pro Backend-Benutzer gespeichert und bleibt bestehen, bis sie geändert wird — danach zeigt die Toolbar nur noch die vier Buttons des aktuell gewählten Projekts.

## Technik

- Symfony-Bundle für Contao 5, `#[AsHook]`/`#[AsCallback]`-Attribute statt klassischer Callback-Arrays.
- Style-Selector-Daten liegen in `tl_style_selector`.
- Das injizierte Auswahlfeld heißt `backendSuiteStyleSelector` und wird dynamisch in jede passende Tabelle eingehängt (eigene `blob`-Spalte je Tabelle, automatisch per `contao:migrate` angelegt).

## Namenskonvention

Felder, die dieses Bundle in fremde Tabellen einhängt (z. B. `tl_content`, die nicht zu diesem Bundle gehören), bekommen einheitlich das Präfix `backendSuite` + Feature-Name in camelCase — so sind Kollisionen mit anderen Bundles ausgeschlossen und erkennbar, woher ein Feld stammt. Eigene Tabellen und Backend-Module (z. B. `tl_style_selector`, `style_selector`) folgen dagegen der normalen, unpräfixierten Contao-Konvention.
