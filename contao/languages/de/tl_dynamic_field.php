<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_dynamic_field']['active'] = ['Aktiv', 'Nur aktive Felder erscheinen in der Zieltabelle. Zum Entfernen eines Feldes hier deaktivieren statt löschen - die Daten bleiben erhalten.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['targetTable'] = ['Zieltabelle', 'In welchem Bereich (Termine, Nachrichten oder FAQ) das neue Feld erscheinen soll. Bewusst auf diese drei Content-Bereiche eingegrenzt.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['legend'] = ['Platzierung', 'In welchen bestehenden Abschnitt das Feld eingefügt wird, oder "Neuer eigener Abschnitt am Ende".'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['newLegendTitle'] = ['Titel des neuen Abschnitts', 'Nur relevant, wenn oben "Neuer eigener Abschnitt" gewählt wurde. Felder mit demselben Titel landen im selben Abschnitt. Nach dem ersten Speichern nicht mehr änderbar.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldType'] = ['Feldtyp', 'Welche Art von Eingabefeld angelegt wird.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldLabel'] = ['Name', 'Sichtbare Bezeichnung im Formular. Daraus wird außerdem der technische Feldname abgeleitet.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldDescription'] = ['Hilfetext', 'Optionaler erklärender Text unter dem Feld.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['selectOptions'] = ['Auswahlmöglichkeiten', 'Die Liste der Einträge, aus denen Redakteure bei diesem Feld wählen können (bei "Checkbox" sind mehrere gleichzeitig ankreuzbar). Eine Zeile pro Eintrag, Format "gespeicherter-wert:Sichtbares-Label" - z.B. "rot:Rot" oder "intern:Nur intern sichtbar". Ohne ":" wird derselbe Text für beides verwendet (z.B. "Rot" ergibt Wert UND Label "Rot"). Diese Liste bestimmt also, was im Dropdown bzw. in der Checkbox-Liste angezeigt wird.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['snippetPreview'] = ['Template-Snippet', 'Zum manuellen Einfügen in die Template-Datei der Zieltabelle - wird nicht automatisch eingebaut.'];

$GLOBALS['TL_LANG']['tl_dynamic_field']['new'] = ['Neues Feld', 'Ein neues Feld anlegen'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['edit'] = ['Feld bearbeiten', 'Feld ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['copy'] = ['Feld duplizieren', 'Feld ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['show'] = ['Feld-Details', 'Details von Feld ID %s anzeigen'];
