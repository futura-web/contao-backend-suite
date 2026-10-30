<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkModules'] = 'Module';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkLayouts'] = 'Seitenlayouts';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkImageSizes'] = 'Bildgrößen';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['switchProject'] = 'Projekt wechseln';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteShrinkOnUpload'] = ['Bilder beim Hochladen verkleinern & umbenennen', 'Zu große Bilder werden automatisch verkleinert, Dateinamen werden bereinigt (Kleinschreibung, Umlaute, Leerzeichen → Unterstrich, „.min" vor der Endung).'];
$GLOBALS['TL_LANG']['tl_theme']['backend_suite_legend'] = 'Backend-Suite-Einstellungen';

$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFonts'] = ['Schriftarten (automatischer Import)', 'Schriftart suchen und Subsets/Schriftschnitte auswählen - lädt die Dateien über google-webfonts-helper und schreibt sie in _fontface.scss. Mehrfach möglich.'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontsDeleteOld'] = ['Alte Schriften löschen', 'Entfernt beim Speichern alle Font-Dateien, die nicht Teil der aktuellen Auswahl sind (sonst bleiben Dateien einer früheren Auswahl liegen).'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupport'] = ['Browser-Support', 'Welche Formate pro Schriftschnitt erzeugt werden - entspricht den drei Stufen auf gwfh.mranftl.com/fonts. Gilt einheitlich für alle Schriftarten in der Liste oben.'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupportRef'] = [
    'modern' => 'Modern Browsers (nur woff2)',
    'legacy' => 'Legacy Support (woff2 + ttf)',
    'historic' => 'Historic Support (eot, woff2, woff, ttf, svg)',
];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteColors'] = ['Farben', 'Eine Zeile pro Variable: "$variable | Wert" oder "$variable Wert". Optionaler Farbton IMMER nach "//": "$variable | Wert //Farbton" oder "$variable Wert //Farbton". Beispiele: $orange | #ff8c05 - oder - $orange #ff8c05 //Orange. Schreibt/aktualisiert die Variable in _vars_colors.scss, neue Variablennamen werden am Ende der Datei angelegt.'];
$GLOBALS['TL_LANG']['tl_theme']['backend_suite_font_color_legend'] = 'Schriftart & Farben (automatischer Import)';
