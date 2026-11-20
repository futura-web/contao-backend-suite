<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['title_legend'] = 'Importlauf';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['target_legend'] = 'Ziel im neuen Projekt';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['source_legend'] = 'Quelle der alten Adressen';

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['title'] = ['Bezeichnung', 'Frei wählbar, z. B. der Name des Altprojekts.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['rootPage'] = ['Startpunkt', 'Bestimmt, welcher Seitenbaum nach Zielseiten durchsucht wird. Die Angabe in Klammern zeigt Prefix und Endung, in die eine alte Adresse passen muss.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['targetFolder'] = ['Ablage der Weiterleitungsseiten', 'Unter dieser Seite werden die Weiterleitungsseiten angelegt. Am besten eine eigene, zugeklappte Seite am Ende des Baums. Die Position im Baum hat keinen Einfluss auf die Adresse, nur der Alias zählt.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['sourceType'] = ['Woher kommen die alten Adressen?', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['urlList'] = ['URL-Liste', 'Eine Adresse pro Zeile, als vollständige URL oder als Pfad. Zeilen mit # werden übersprungen. Beste Quelle ist ein Export der alten Datenbank oder das 404-Protokoll der neuen Seite.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackDomain'] = ['Domain des Altprojekts', 'Zum Beispiel "alt.de". Das Internet Archive wird nach allen je archivierten Adressen dieser Domain gefragt, Subdomains eingeschlossen. Ohne Zugangsdaten, funktioniert auch bei längst abgeschalteten Seiten.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackLimit'] = ['Maximale Anzahl', 'Obergrenze für einen Abruf. Bei großen Domains lieber in mehreren Durchläufen arbeiten.'];

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['list'] = 'URL-Liste einfügen';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['wayback'] = 'Internet Archive (Wayback) abfragen';

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['children'] = ['Vorschläge ansehen', 'Die Weiterleitungsvorschläge dieses Importlaufs ansehen'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['runImport'] = ['Vorschläge erzeugen', 'Die Quelle einlesen und Weiterleitungsvorschläge berechnen'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['editheader'] = ['Einstellungen bearbeiten', 'Einstellungen dieses Importlaufs bearbeiten'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['new'] = ['Neuer Importlauf', 'Einen neuen Importlauf anlegen'];
