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

        $snippet = $this->buildSnippet((string) $record->fieldType, $fieldName, (string) ($record->optionsSource ?? ''));
        $rows = max(3, substr_count($snippet, "\n") + 1);

        return sprintf(
            '<p>Zum Einfügen in die Template-Datei von "%s" (z. B. an der gewünschten Stelle im Markup):</p>'
            .'<textarea readonly rows="%d" style="width:100%%;font-family:monospace;font-size:13px;" onclick="this.select()">%s</textarea>',
            StringUtil::specialchars((string) ($record->targetTable ?? '')),
            $rows,
            StringUtil::specialchars($snippet),
        );
    }

    private function buildSnippet(string $fieldType, string $fieldName, string $optionsSource): string
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
            return <<<PHP
                <?php if (\$images = \Contao\StringUtil::deserialize(\$this->{$fieldName}, true)): ?>
                    <div class="dynfield-gallery">
                        <?php foreach (\$images as \$uuid): ?>
                            <?php if (\$filesModel = \Contao\FilesModel::findByUuid(\$uuid)): ?>
                                <figure><?= \Contao\Image::getHtml(\$filesModel->path, '400') ?></figure>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                PHP;
        }

        if (DynamicFieldTypeRegistry::LINK === $fieldType) {
            return <<<PHP
                <?php if (\$this->{$fieldName}): ?>
                    <a href="<?= \$this->{$fieldName} ?>" class="btn">Mehr erfahren</a>
                <?php endif; ?>
                PHP;
        }

        return <<<PHP
            <?php if (\$this->{$fieldName}): ?>
                <?= \$this->{$fieldName} ?>
            <?php endif; ?>
            PHP;
    }
}
