<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\RedirectWizard\CandidateImporter;
use Futuraweb\ContaoBackendSuite\RedirectWizard\RedirectPageCreator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Die beiden schreibenden Aktionen der Vorschlagsliste:
 *
 * - key=createPages legt aus den geprueften Vorschlaegen die
 *   Weiterleitungsseiten im Seitenbaum an.
 * - onsubmit haelt den Zustand einer Zeile mit dem von Hand gesetzten Ziel
 *   im Einklang, damit eine nachtraeglich zugeordnete Seite beim naechsten
 *   Anlegen auch beruecksichtigt wird.
 */
class RedirectCandidateActionListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RedirectPageCreator $creator,
        private readonly Security $security,
    ) {
    }

    #[AsCallback(table: 'tl_bs_redirect', target: 'config.onload')]
    public function onLoad(DataContainer|null $dc = null): void
    {
        if ('createPages' !== Input::get('key')) {
            return;
        }

        $importId = (int) Input::get('id');

        if ($importId < 1) {
            Message::addError('Kein Importlauf ausgewählt.');
            Controller::redirect($this->backUrl($importId));
        }

        $folderId = (int) $this->connection->fetchOne(
            'SELECT targetFolder FROM tl_bs_redirect_import WHERE id = ?',
            [$importId],
        );

        // Wer dieses Modul sehen darf, darf damit nicht automatisch
        // irgendwo im Baum Seiten anlegen - die Seitenrechte des Nutzers
        // gelten weiter.
        $action = new CreateAction('tl_page', ['pid' => $folderId, 'type' => 'redirect']);

        if (!$this->security->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_page', $action)) {
            Message::addError('Keine Berechtigung, unterhalb der gewählten Seite neue Seiten anzulegen.');
            Controller::redirect($this->backUrl($importId));
        }

        try {
            $result = $this->creator->createForImport($importId);
        } catch (\RuntimeException $e) {
            Message::addError($e->getMessage());
            Controller::redirect($this->backUrl($importId));
        }

        if (0 === $result['created'] && 0 === $result['skipped']) {
            Message::addInfo('Keine Vorschläge mit gesetztem Ziel vorhanden. Es wurde nichts angelegt.');
            Controller::redirect($this->backUrl($importId));
        }

        if ($result['created'] > 0) {
            Message::addConfirmation(\sprintf(
                '%d Weiterleitungsseiten angelegt: versteckt, nicht in der Sitemap, Statuscode 301. '
                .'Falls ein Reverse-Proxy im Einsatz ist, einmal den Cache leeren, damit zwischengespeicherte '
                .'404-Antworten der alten Adressen verschwinden.',
                $result['created'],
            ));
        }

        foreach (\array_slice($result['errors'], 0, 20) as $error) {
            Message::addError($error);
        }

        if (\count($result['errors']) > 20) {
            Message::addInfo(\sprintf('%d weitere Zeilen wurden übersprungen.', \count($result['errors']) - 20));
        }

        Controller::redirect($this->backUrl($importId));
    }

    /**
     * Setzt der Redakteur bei einer Zeile ohne Treffer von Hand ein Ziel,
     * wechselt sie von "notarget" nach "review" und wird damit anlegbar.
     * Umgekehrt faellt eine geleerte Zeile zurueck. Bereits angelegte und
     * blockierte Zeilen bleiben unberuehrt.
     */
    #[AsCallback(table: 'tl_bs_redirect', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        $id = (int) $dc->id;

        if ($id < 1) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT state, targetPage, alias FROM tl_bs_redirect WHERE id = ?',
            [$id],
        );

        if (!$row) {
            return;
        }

        $state = (string) $row['state'];

        $editable = [
            CandidateImporter::STATE_READY,
            CandidateImporter::STATE_REVIEW,
            CandidateImporter::STATE_NOTARGET,
        ];

        if (!\in_array($state, $editable, true)) {
            return;
        }

        $hasTarget = (int) $row['targetPage'] > 0 && '' !== (string) $row['alias'];
        $newState = $hasTarget
            ? (CandidateImporter::STATE_READY === $state ? $state : CandidateImporter::STATE_REVIEW)
            : CandidateImporter::STATE_NOTARGET;

        if ($newState !== $state) {
            $this->connection->update(
                'tl_bs_redirect',
                ['state' => $newState, 'tstamp' => time()],
                ['id' => $id],
            );
        }
    }

    private function backUrl(int $importId): string
    {
        if ($importId < 1) {
            return Backend::addToUrl('', true, ['key']);
        }

        return Backend::addToUrl('table=tl_bs_redirect&id='.$importId, true, ['key']);
    }
}
