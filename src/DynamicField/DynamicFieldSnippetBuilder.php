<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

use Contao\DataContainer;
use Contao\StringUtil;

/**
 * Baut die Copy-Box im Feld-Builder-Formular: das PHP-Snippet, das
 * manuell in die Template-Datei der Zieltabelle eingefügt werden muss.
 * Kein automatisches Template-Patchen - siehe das etablierte Muster der
 * projekt-lokalen Event-Formular-Erweiterung auf netcup (manueller
 * Einbau in event_full_1.html5).
 */
class DynamicFieldSnippetBuilder
{
    public function render(DataContainer $dc): string
    {
        $record = $dc->activeRecord;
        $fieldName = \is_object($record) ? (string) ($record->fieldName ?? '') : '';

        if ('' === $fieldName) {
            return '<p class="tl_info">Das Snippet erscheint hier nach dem ersten Speichern.</p>';
        }

        $snippet = $this->buildSnippet(
            (string) $record->fieldType,
            $fieldName,
            (string) ($record->optionsSource ?? ''),
            (string) ($record->gallerySize ?? ''),
        );
        $rows = max(3, substr_count($snippet, "\n") + 1);

        return sprintf(
            '<p>Zum Einfügen in die Template-Datei von "%s" (z. B. an der gewünschten Stelle im Markup):</p>'
            .'<textarea readonly rows="%d" style="width:100%%;font-family:monospace;font-size:13px;" onclick="this.select()">%s</textarea>',
            StringUtil::specialchars((string) ($record->targetTable ?? '')),
            $rows,
            StringUtil::specialchars($snippet),
        );
    }

    private function buildSnippet(string $fieldType, string $fieldName, string $optionsSource, string $gallerySize): string
    {
        if (DynamicFieldTypeRegistry::SELECT === $fieldType && DynamicFieldTypeRegistry::SOURCE_MODULE === $optionsSource) {
            return <<<PHP
                <?php if (\$this->{$fieldName}): ?>
                    <?= \Contao\Controller::getFrontendModule(\$this->{$fieldName}) ?>
                <?php endif; ?>
                PHP;
        }

        if (DynamicFieldTypeRegistry::SELECT === $fieldType && DynamicFieldTypeRegistry::SOURCE_FORM === $optionsSource) {
            return <<<PHP
                <?php if (\$this->{$fieldName}): ?>
                    <?php
                        \$dynFieldFormContent = new \Contao\ContentModel();
                        \$dynFieldFormContent->type = 'form';
                        \$dynFieldFormContent->form = \$this->{$fieldName};
                        echo \Contao\Controller::getContentElement(\$dynFieldFormContent);
                    ?>
                <?php endif; ?>
                PHP;
        }

        if (DynamicFieldTypeRegistry::SELECT === $fieldType && DynamicFieldTypeRegistry::SOURCE_CONTENT === $optionsSource) {
            return <<<PHP
                <?php if (\$this->{$fieldName} && (\$dynFieldContentModel = \Contao\ContentModel::findByPk(\$this->{$fieldName}))): ?>
                    <?= \Contao\Controller::getContentElement(\$dynFieldContentModel) ?>
                <?php endif; ?>
                PHP;
        }

        if (DynamicFieldTypeRegistry::GALLERY === $fieldType) {
            $sizeLiteral = $this->imageSizeLiteral($gallerySize);

            return <<<PHP
                <?php if (\$images = \Contao\StringUtil::deserialize(\$this->{$fieldName}, true)): ?>
                    <div class="dynfield-gallery">
                        <?php foreach (\$images as \$uuid): ?>
                            <?php if (\$filesModel = \Contao\FilesModel::findByUuid(\$uuid)): ?>
                                <figure><?= \Contao\Image::getHtml(\$filesModel->path, {$sizeLiteral}) ?></figure>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                PHP;
        }

        if (DynamicFieldTypeRegistry::LINK === $fieldType) {
            // "Link-Set" legt 4 zusammengehörige Spalten an (siehe
            // DynamicFieldTypeRegistry::columnSqlTypes()): die URL unter
            // "fieldName" selbst, Text/Titel/Blank-Checkbox unter
            // "fieldName" + Suffix "Text"/"Title"/"Target". Bewusst OHNE
            // class="btn" - Redakteure ergänzen beim Einfügen selbst
            // eine passende Klasse, falls nötig.
            return <<<PHP
                <?php if (\$this->{$fieldName}): ?>
                    <a href="<?= \$this->{$fieldName} ?>"<?= \$this->{$fieldName}Title ? ' title="'.\Contao\StringUtil::specialchars(\$this->{$fieldName}Title).'"' : '' ?><?= \$this->{$fieldName}Target ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= \$this->{$fieldName}Text ?: 'Mehr erfahren' ?></a>
                <?php endif; ?>
                PHP;
        }

        return <<<PHP
            <?php if (\$this->{$fieldName}): ?>
                <?= \$this->{$fieldName} ?>
            <?php endif; ?>
            PHP;
    }

    /**
     * Baut aus dem gespeicherten "gallerySize"-Wert (Format des
     * imageSize-Widgets: serialisiertes [Breite, Höhe, Modus-ODER-
     * Preset-ID], siehe Contao\Widgets\ImageSize::validator()) den
     * PHP-Quelltext-Ausdruck fürs Snippet. Bei reiner Preset-Auswahl
     * (Breite/Höhe leer) reicht Contaos Image::getHtml() die bloße
     * ID als String - kompakter als das volle Array.
     */
    private function imageSizeLiteral(string $raw): string
    {
        if ('' === $raw) {
            return "'400'";
        }

        $size = StringUtil::deserialize($raw, true);

        if (
            \is_numeric($size[2] ?? null)
            && '' === (string) ($size[0] ?? '')
            && '' === (string) ($size[1] ?? '')
        ) {
            return var_export((string) $size[2], true);
        }

        return '['.implode(', ', array_map(static fn ($part): string => var_export($part, true), $size)).']';
    }
}
