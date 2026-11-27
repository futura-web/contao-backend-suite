<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_page']['backendSuite']['backendSuiteFlatUrl'] = ['Flache Artikel-URLs (veraltet)', 'Entfernt den Alias dieser Seite aus der URL von Nachrichtenartikeln (oder anderen Inhalten), die über sie gelesen werden - z.B. wird aus "domain.de/beitrag/artikel-slug" dann "domain.de/artikel-slug", sitewide inklusive Sitemap. Betrifft nur die Anzeige der URL; damit "domain.de/artikel-slug" auch tatsächlich erreichbar ist, braucht es zusätzlich eine einmalige .htaccess-/Router-Einrichtung (siehe Bundle-README). VERALTET: Für neue Projekte stattdessen den Seitentyp "Nachrichtenleser (flache URLs)" verwenden - der löst die flache Form auch eingehend auf und kommt ohne .htaccess und ohne eigenes Router-Skript aus.'];
