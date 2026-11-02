<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_dynamic_field']['active_legend'] = 'Status';
$GLOBALS['TL_LANG']['tl_dynamic_field']['target_legend'] = 'Zieltabelle & Platzierung';
$GLOBALS['TL_LANG']['tl_dynamic_field']['field_legend'] = 'Feld-Eigenschaften';
$GLOBALS['TL_LANG']['tl_dynamic_field']['snippet_legend'] = 'Template-Snippet';

$GLOBALS['TL_LANG']['tl_dynamic_field']['active'] =['Aktiv', 'Nur aktive Felder erscheinen in der Zieltabelle. Zum Entfernen eines Feldes hier deaktivieren statt löschen - die Daten bleiben erhalten.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['targetTable'] = ['Zieltabelle', 'In welchem Bereich (Termine, Nachrichten oder FAQ) das neue Feld erscheinen soll. Bewusst auf diese drei Content-Bereiche eingegrenzt.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['legend'] = ['Platzierung', 'In welchen bestehenden Abschnitt das Feld eingefügt wird, oder "Neuer eigener Abschnitt am Ende".'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['newLegendTitle'] = ['Titel des neuen Abschnitts', 'Nur relevant, wenn oben "Neuer eigener Abschnitt" gewählt wurde. Felder mit demselben Titel landen im selben Abschnitt. Nach dem ersten Speichern nicht mehr änderbar.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['contentElementType'] = ['Inhaltselement-Typ(en)', 'Nur bei Zieltabelle "Inhaltselemente": bei welchen Typen (z.B. Text, Bild) das Feld erscheinen soll. Mehrfachauswahl möglich. Nichts ausgewählt = bei allen verfügbaren Typen. Nach dem ersten Speichern nur noch änderbar, solange die Spalte noch keine echten Daten enthält.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldType'] = ['Feldtyp', 'Welche Art von Eingabefeld angelegt wird.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldLabel'] = ['Name', 'Sichtbare Bezeichnung im Formular. Daraus wird außerdem der technische Feldname abgeleitet.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldDescription'] = ['Hilfetext', 'Optionaler erklärender Text unter dem Feld.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['customFieldName'] = ['Eigene Bezeichnung (optional)', 'Legt fest, wie die Variable im Snippet heißt (z.B. "hinweistext" ergibt $this->dynFieldHinweistext). Leer lassen, um den Namen automatisch aus "Name" abzuleiten - sinnvoll z.B. wenn "Name" nur Sonderzeichen enthält oder die Variable anders/kürzer heißen soll. Nach dem ersten Speichern nicht mehr änderbar.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['optionsSource'] = ['Optionsquelle', 'Nur bei Feldtyp "Auswahlliste": woher die wählbaren Einträge kommen. "Frei eingetippt" nutzt die Liste unten. Bei "Modul"/"Formular"/"Inhaltselement" zeigt das Dropdown automatisch die vorhandenen Datensätze, und das Snippet bindet das ausgewählte Element direkt ein. Nach dem ersten Speichern nicht mehr änderbar.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['gallerySize'] = ['Bildgröße', 'Nur bei Feldtyp "Galerie": in welcher Größe die Bilder im Snippet ausgegeben werden. Leer lassen für die Originalgröße.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['selectOptions'] = ['Auswahlmöglichkeiten', 'Nur bei Optionsquelle "Frei eingetippt": die Liste der Einträge, aus denen Redakteure wählen können. Mit "Neuen Eintrag hinzufügen" beliebig viele Einträge anlegen, sortieren und entfernen - der eingegebene Text ist zugleich gespeicherter Wert und sichtbares Label.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['snippetPreview'] = ['Template-Snippet', 'Zum manuellen Einfügen in die Template-Datei der Zieltabelle - wird nicht automatisch eingebaut.'];

$GLOBALS['TL_LANG']['tl_dynamic_field']['new'] = ['Neues Feld', 'Ein neues Feld anlegen'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['edit'] = ['Feld bearbeiten', 'Feld ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['copy'] = ['Feld duplizieren', 'Feld ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['show'] = ['Feld-Details', 'Details von Feld ID %s anzeigen'];
