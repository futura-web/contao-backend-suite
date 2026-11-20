<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_bs_redirect']['candidate_legend'] = 'Alte Adresse';
$GLOBALS['TL_LANG']['tl_bs_redirect']['target_legend'] = 'Weiterleitungsziel';

$GLOBALS['TL_LANG']['tl_bs_redirect']['source'] = ['Alte Adresse', 'So stand die Adresse im Altprojekt. Nicht änderbar, der Alias ist daraus berechnet.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['alias'] = ['Alias der Weiterleitungsseite', 'Unter diesem Alias antwortet die neue Weiterleitungsseite. Nur ändern, wenn klar ist, dass die alte Adresse damit noch getroffen wird.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['targetPage'] = ['Zielseite', 'Hierhin wird dauerhaft (301) weitergeleitet. Gespeichert wird ein Verweis auf die Seite, nicht ihr Pfad: die Weiterleitung bleibt also richtig, wenn sich der Alias der Zielseite später ändert.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['score'] = ['Übereinstimmung', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect']['state'] = ['Zustand', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect']['createdPage'] = ['Angelegte Seite', ''];

$GLOBALS['TL_LANG']['tl_bs_redirect']['states'] = [
    'ready' => 'Ziel sicher',
    'review' => 'Ziel prüfen',
    'notarget' => 'kein Ziel gefunden',
    'exists' => 'Adresse schon vergeben',
    'created' => 'Seite angelegt',
    'skip_query' => 'nicht abbildbar: Query-String',
    'skip_prefix' => 'nicht abbildbar: anderer Sprach-Prefix',
    'skip_suffix' => 'nicht abbildbar: andere URL-Endung',
    'skip_invalid' => 'nicht abbildbar: Zeichen im Alias nicht erlaubt',
    'skip_root' => 'Startseite, keine Weiterleitung nötig',
];

$GLOBALS['TL_LANG']['tl_bs_redirect']['createPages'] = ['Weiterleitungsseiten anlegen', 'Aus allen Vorschlägen mit gesetztem Ziel Weiterleitungsseiten im Seitenbaum anlegen'];
