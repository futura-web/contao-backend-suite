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
- [x] **Contao Manager in neuem Tab** — der Link in der Hauptnavigation (System) öffnet den Contao Manager in einem neuen Tab statt die aktuelle Backend-Seite zu verlassen, mit kleinem ↗-Hinweis-Icon.
- [x] **Erweiterungen pro Nutzer ausblenden** — jeder Nutzer (auch Admins) legt selbst in "Mein Profil" fest, welche der obigen Erweiterungen inklusive der Menüeinträge von BE Suite Builder und Modularis für ihn sichtbar sind; ein Admin kann dasselbe im Benutzer-Formular auch für andere setzen (siehe unten).
- [x] **Flache News-URLs** — Seitentyp "Nachrichtenleser (flache URLs)": News-Artikel liegen direkt unter `/artikel-slug` statt `/beitrag/artikel-slug`, eingehend wie ausgehend, ohne `.htaccess` und ohne Skript im Webroot (siehe unten).
- [x] **BE Suite Weiterleitungen** — Relaunch-Weiterleitungen ohne `.htaccess` und ohne FTP: alte Adressen einlesen, passende neue Seiten vorschlagen, daraus versteckte 301-Weiterleitungsseiten im Seitenbaum anlegen (siehe unten).

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
- Rechts neben **Bildgrößen** steht zusätzlich ein Quicklink zu **BE Suite Weiterleitungen** (siehe unten). Der hängt an keinem Theme und ist daher immer ein einzelner Link, auch bei mehreren Projekten. Er erscheint nur, wenn der Nutzer sowohl die Toolbar-Quicklinks als auch das Weiterleitungen-Modul in "Mein Profil" aktiviert hat.

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

### Flache News-URLs

Contao hängt bei News-Artikeln den Alias der Reader-Seite vor die URL (`/beitrag/artikel-slug`). Mit Bordmitteln lässt sich das nicht entfernen — ein leerer Seiten-Alias fällt intern auf die numerische Seiten-ID zurück.

Die Suite bietet dafür zwei Wege. **Für neue Projekte den Seitentyp nehmen**; die Checkbox ist nur noch für Bestandsprojekte da.

#### Empfohlen: Seitentyp "Nachrichtenleser (flache URLs)"

Backend → **Seiten** → Reader-Seite öffnen → Seitentyp auf **"Nachrichtenleser (flache URLs)"** stellen. Dann im News-Archiv die **Weiterleitungsseite (`jumpTo`)** auf genau diese Seite zeigen lassen — darüber entscheidet das Bundle, welche Artikel die Seite annimmt.

Das war es. Keine `.htaccess`, kein Skript im Webroot.

Die Seite bekommt die Route `/{alias}`, deren Anforderung zur Laufzeit auf exakt die Aliasse der Artikel eingeschränkt wird, deren Archiv auf sie zeigt. Dadurch greift sie nur bei echten Beiträgen und verschluckt keine anderen einsegmentigen Pfade — ohne Existenzprüfung bei jedem Request:

- `Routing\FlatNewsPage` — der Seitentyp (`bs_news_flat`), setzt die Alias-Anforderung in `configurePageRoute()`.
- `Routing\FlatNewsRouteEnhancer` — übersetzt den Alias in `parameters`, damit Contaos `InputEnhancer` daraus `auto_item` macht (Priority 10 > dessen 0).
- `Routing\FlatNewsUrlResolver` — liefert den Alias beim Linkbau, womit flache URLs **schon bei der Erzeugung** entstehen statt per Textersatz im fertigen HTML.
- `EventListener\FlatNewsLegacyUrlListener` — fängt die 404 der alten verschachtelten Form ab und leitet `/<leseseite>/<slug>` dauerhaft auf `/<slug>` um. Ersetzt die frühere `.htaccess`-Regel.

**Zu beachten:** Die Leseseite belegt ihren eigenen Alias nicht mehr — `/beitrag` liefert 404. Wohin dieser Aufruf sinnvoll zeigt (Übersicht, Elternseite, Startseite), hängt vom Projekt ab und lässt sich nicht allgemein entscheiden; bei Bedarf eine Weiterleitungsseite im Baum anlegen.

#### Veraltet: Checkbox "Flache Artikel-URLs"

Backend → **Seiten** → Reader-Seite → unter "Routing" die Checkbox **"Flache Artikel-URLs (veraltet)"**.

Zwei Listener entfernen den Präfix nachträglich aus der Ausgabe:

- `FlatNewsUrlSitemapListener` (auf `contao.sitemap`) — aus jedem `<loc>` der `sitemap.xml`.
- `FlatNewsUrlResponseListener` (auf `kernel.response`) — aus jedem gerenderten Frontend-HTML, sitewide.
- Beide fragen die geflaggten Seiten live per `FlatUrlPageAliasRegistry` ab; eine Änderung wirkt sofort beim nächsten Request.

**Die entscheidende Einschränkung:** Beide wirken nur auf die *Anzeige*. Contaos Routing kennt weiterhin ausschließlich `/beitrag/slug`, die flache Form läuft also in einen 404. Jedes Projekt braucht deshalb zusätzlich eine `.htaccess`-Regel plus ein Router-Skript im Webroot, das `$_SERVER['REQUEST_URI']` vor dem Front-Controller umschreibt — per Composer nicht installierbar, weil ein Bundle die Webserver-Konfiguration nicht ändern kann.

Das vollständige Rezept dafür steht in der README von **v2.25.1**:

```bash
git show v2.25.1:README.md
```

Bestandsprojekte laufen damit unverändert weiter. Für alles Neue ist der Seitentyp oben der kürzere und robustere Weg.

### BE Suite Weiterleitungen

Nach einem Relaunch sollen die Adressen des Altprojekts weiter funktionieren. Dieses Modul erledigt das ohne `.htaccess`, ohne `robots.txt` und ohne FTP-Zugang: es legt für jede alte Adresse eine Seite vom Typ **Weiterleitung** im Seitenbaum an.

**Ablauf**

1. Backend → **Backend-Suite → BE Suite Weiterleitungen** → neuen Importlauf anlegen.
2. **Startpunkt** wählen. Die Klammer dahinter zeigt URL-Prefix und Endung, in die eine alte Adresse passen muss.
3. **Ablage** wählen: unter dieser Seite entstehen die Weiterleitungsseiten. Am besten eine eigene, zugeklappte Seite am Ende des Baums. Die Position im Baum beeinflusst die Adresse nicht, nur der Alias zählt.
4. **Quelle** wählen: URL-Liste einfügen (eine Adresse pro Zeile, als vollständige URL oder als Pfad) oder eine Domain beim Internet Archive abfragen.
5. Auf **Vorschläge erzeugen** klicken, die Liste durchsehen, bei Bedarf Ziele korrigieren.
6. Auf **Weiterleitungsseiten anlegen** klicken.

**Warum das ohne .htaccess und ohne robots.txt reicht** — drei im Contao-Core nachgelesene Eigenschaften:

- Statuscode: `RedirectPageController` liefert 301, sofern `tl_page.redirect` nicht auf `temporary` steht. Der Spaltendefault ist `permanent`.
- Sitemap: derselbe Controller ist mit `#[AsPage(contentComposition: false)]` registriert, und `SitemapController` nimmt eine Seite nur auf, wenn `PageRegistry::supportsContentComposition()` wahr ist. Weiterleitungsseiten erscheinen damit nie in der `sitemap.xml`. Das Modul setzt `sitemap` und `robots` trotzdem defensiv mit, für Drittanbieter-Sitemaps.
- Ziel: `PageResolver` gibt `tl_page.url` an `StringResolver` weiter, der den Wert durch den InsertTagParser schickt. Gespeichert wird deshalb `{{link_url::<id>}}` statt eines festen Pfads — die Weiterleitung bleibt also richtig, wenn sich der Alias der Zielseite später ändert.

Jede angelegte Seite bekommt zusätzlich `hide = 1`. Ohne dieses Flag würden alle Weiterleitungsseiten in jedem Navigationsmodul auftauchen.

**Was sich als Seite nicht abbilden lässt**

| Alte Adresse | Ergebnis |
| --- | --- |
| `/alte-seite.html` | geht, Alias darf Punkt und Schrägstrich enthalten |
| `/index.php?id=47` | nicht möglich, Query-Strings sind kein Alias |
| ohne `/de/`, neue Startseite mit Sprach-Prefix | nicht möglich |
| andere URL-Endung als am Startpunkt eingestellt | nicht möglich |

Das Feld `routePath` in Contao 5.3 wäre der Ausweg, ist aber nur eine Anzeige (`PageRoutingListener::generateRoutePath`), kein Eingabefeld — Prefix und Endung hängen am Startpunkt und lassen sich pro Seite nicht umgehen. Solche Zeilen werden im Import entsprechend markiert und übersprungen; dafür braucht es eine Rewrite-Regel, z. B. `terminal42/contao-url-rewrite`.

**Wie die Vorschläge zustande kommen**

Aus der alten Adresse wird das letzte tragende Pfadsegment genommen, kleingeschrieben, von Umlauten und Trennzeichen befreit und gegen Alias, Titel und Seitentitel aller Inhaltsseiten des Startpunkts verglichen. Der Score ist das Maximum aus Zeichenähnlichkeit (`similar_text`) und Wort-Überschneidung (Jaccard). Ab 90 % gilt das Ziel als sicher, unter 55 % wird keines vorgeschlagen.

Gesetzt wird ein Ziel nie ohne Rückfrage: alles unter 90 % steht als **„Ziel prüfen"** in der Liste. Eine falsche 301-Weiterleitung ist schädlicher als ein 404, weil sie niemandem auffällt — weder dem Redakteur noch der Suchmaschine, die den Zielinhalt dann als irrelevant für die alte Adresse bewertet.

**Woher die Liste der alten Adressen kommt**

Es gibt keine API, die den Google-Index einer Domain auflistet. Die Search-Console-API (`searchanalytics.query`) liefert nur Adressen mit Impressionen, laut Doku ausdrücklich nicht alle, sondern die oberen, und setzt voraus, dass die alte Domain dort noch verifiziert ist. Die URL-Inspection-API prüft nur einzelne Adressen und kann nicht auflisten. Brauchbare Quellen, nach Qualität sortiert:

1. Ein Export der alten Datenbank (bei Contao: `tl_page`) — vollständig und exakt.
2. Das Internet Archive, direkt im Modul abfragbar. Kein Zugangsschlüssel nötig, funktioniert auch bei längst abgeschalteten Seiten.
3. Die Access-Logs der alten Seite, falls vorhanden. Enthalten auch Adressen mit Query-String.
4. Das 404-Protokoll der neuen Seite, z. B. `lukasbableck/contao-404-log-bundle`. Zeigt genau die Adressen, die real noch angefragt werden.

**Hinweise**

- Die Tabellen `tl_bs_redirect_import` und `tl_bs_redirect` legt `contao:migrate` an.
- Ein erneuter Lauf desselben Imports lässt bereits angelegte Zeilen als Protokoll stehen und überspringt schon eingelesene Adressen.
- Die Ablage muss im Baum des gewählten Startpunkts liegen, sonst würden die Seiten unter anderen Adressen antworten. Das Modul bricht in dem Fall mit einer Meldung ab.
- Die Seitenrechte des Nutzers gelten weiter: wer unterhalb der Ablage keine Seiten anlegen darf, bekommt eine Fehlermeldung statt neuer Seiten.
- Läuft ein Reverse-Proxy, nach dem Anlegen einmal den Cache leeren, damit zwischengespeicherte 404-Antworten der alten Adressen verschwinden.

### Erweiterungen pro Nutzer ausblenden

Nicht jeder Redakteur braucht jede Erweiterung der Suite zu sehen. Jeder Nutzer kann daher selbst in **„Mein Profil"** (Legende "Backend-Suite-Erweiterungen") festlegen, welche Erweiterungen für ihn sichtbar sind, und ein Admin kann dasselbe Feld auch im vollen Benutzer-Formular (Backend → **Benutzer** → Nutzer bearbeiten) für jeden anderen Nutzer setzen: Style-Selector, Toolbar-Quicklinks, Sprache bei Startpunkten, ID-/Alias-Anzeige, SEO-Längenanzeige, Projekt-Theme-Konfiguration, JS-Funktionen, SCSS-Features, Schriftarten-/Farben-Picker sowie die Menüeinträge von **BE Suite Builder**, **BE Suite Weiterleitungen** und **Modularis**.

- Nichts angekreuzt (Standard, auch bei allen Bestandsnutzern inklusive Admins) bedeutet: keine der Erweiterungen ist sichtbar, bis gezielt welche angekreuzt werden.
- Gilt für jeden Nutzer gleich, auch für Admins — keine automatische Ausnahme.
- `tl_user` nutzt Contaos Subpaletten (`__selector__` = `inherit`/`admin`) und "Mein Profil" ersetzt die Paletten-Liste komplett durch eine eigene ("login") — das Feld ist deshalb in alle relevanten Paletten eingehängt (`login`, `default`, `group`, `extend`, `custom`, `admin`), nicht nur in eine.
- Der Menüeintrag "Modularis" gehört technisch zum separaten Bundle `futuraweb/contao-modularis-builder`, wird aber trotzdem von hier aus ein-/ausgeblendet (`ModuleVisibilityListener`) — das ist unproblematisch, weil diese Suite bereits von Modularis abhängt und nicht umgekehrt.
- Stille Hintergrund-Mechanik ohne eigene Sichtbarkeit (Bild-Shrinker — bereits pro Theme umschaltbar, Ordner-Namensbereinigung, die eigentlichen Feld-Builder-Felder in den Zieltabellen) ist hiervon bewusst ausgenommen.

## Technik

- Symfony-Bundle für Contao 5, `#[AsHook]`/`#[AsCallback]`-Attribute statt klassischer Callback-Arrays.
- Style-Selector-Daten liegen in `tl_style_selector`.
- Das injizierte Auswahlfeld heißt `backendSuiteStyleSelector` und wird dynamisch in jede passende Tabelle eingehängt (eigene `blob`-Spalte je Tabelle, automatisch per `contao:migrate` angelegt).

## Namenskonvention

Felder, die dieses Bundle in fremde Tabellen einhängt (z. B. `tl_content`, die nicht zu diesem Bundle gehören), bekommen einheitlich das Präfix `backendSuite` + Feature-Name in camelCase — so sind Kollisionen mit anderen Bundles ausgeschlossen und erkennbar, woher ein Feld stammt. Eigene Tabellen und Backend-Module (z. B. `tl_style_selector`, `style_selector`) folgen dagegen der normalen, unpräfixierten Contao-Konvention.

Ausnahme: die Felder der **Projekt-Theme-Konfiguration** (Rahmen/Header/Hauptnavi/Schriftgrößen) folgen der älteren `project`-Konvention statt `backendSuite`, da dieses Feature aus einem projekt-lokalen `contao/dca/tl_theme.php` ins Bundle migriert wurde und die bestehenden Feldnamen/Datenbankspalten (mit bereits gepflegten Werten) unverändert übernommen wurden.

## Voraussetzungen

Die SCSS-Projektstruktur (`files/public/<slug>/theme/css/...`) wird von diesem Bundle vorausgesetzt (Schriftarten/Farben-Import, Projekt-Theme-Konfiguration) — sie stammt aus dem Futura-Web-eigenen Theme-Boilerplate, ist aber kein Contao-Standard. In einem neuen Projekt ohne diese Struktur bleiben die betroffenen Formularfelder einfach wirkungslos (kein Fehler), bis die passenden SCSS-Dateien unter diesem Pfad existieren.
