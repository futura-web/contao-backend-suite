<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_dynamic_field']['active_legend'] = 'Status';
$GLOBALS['TL_LANG']['tl_dynamic_field']['target_legend'] = 'Target table & placement';
$GLOBALS['TL_LANG']['tl_dynamic_field']['field_legend'] = 'Field properties';
$GLOBALS['TL_LANG']['tl_dynamic_field']['snippet_legend'] = 'Template snippet';

$GLOBALS['TL_LANG']['tl_dynamic_field']['active'] =['Active', 'Only active fields show up in the target table. To remove a field, deactivate it here instead of deleting it - the data stays intact.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['targetTable'] = ['Target table', 'Which area (events, news or FAQ) the new field should appear in. Deliberately scoped to these three content areas.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['legend'] = ['Placement', 'Which existing section the field is added to, or "New section at the end".'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['newLegendTitle'] = ['New section title', 'Only relevant when "New section" was selected above. Fields sharing the same title land in the same section. Locked after the first save.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['contentElementType'] = ['Content element type', 'Only for target "Content elements": which type (e.g. Text, Image) the field should appear on. Each type has its own form, hence this is required. Locked after the first save.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldType'] = ['Field type', 'Which kind of input field to create.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldLabel'] = ['Name', 'Visible label in the form. Also used to derive the technical field name.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldDescription'] = ['Help text', 'Optional explanatory text shown below the field.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['customFieldName'] = ['Custom name (optional)', 'Sets the variable name used in the snippet (e.g. "hint" becomes $this->dynFieldHint). Leave empty to derive it automatically from "Name" - useful e.g. when "Name" is mostly special characters or you want a shorter/different variable name. Locked after the first save.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['optionsSource'] = ['Options source', 'Only for field type "Select": where the choices come from. "Free text" uses the list below. "Module"/"Form"/"Content element" automatically list the existing records, and the snippet embeds the chosen one directly. Locked after the first save.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['gallerySize'] = ['Image size', 'Only for field type "Gallery": the size images are rendered at in the snippet. Leave empty for the original size.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['selectOptions'] = ['Choices', 'Only for options source "Free text": the list of entries editors can pick from. Use "Add new item" to add, reorder or remove as many entries as needed - the entered text is both the stored value and the visible label.'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['snippetPreview'] = ['Template snippet', 'For manual insertion into the target table\'s template - not inserted automatically.'];

$GLOBALS['TL_LANG']['tl_dynamic_field']['new'] = ['New field', 'Create a new field'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['edit'] = ['Edit field', 'Edit field ID %s'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['copy'] = ['Duplicate field', 'Duplicate field ID %s'];
$GLOBALS['TL_LANG']['tl_dynamic_field']['show'] = ['Field details', 'Show details of field ID %s'];
