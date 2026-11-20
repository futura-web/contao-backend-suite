<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Futuraweb\ContaoBackendSuite\RedirectWizard\CandidateImporter;
use Futuraweb\ContaoBackendSuite\RedirectWizard\TargetMatcher;
use Futuraweb\ContaoBackendSuite\RedirectWizard\WaybackException;

/**
 * Fuehrt den Import aus, wenn in der Liste der Importlaeufe die
 * Schaltflaeche "Vorschläge erzeugen" (key=runImport) angeklickt wurde.
 *
 * Der Umweg ueber Input::get('key') im onload_callback statt ueber einen
 * eigenen BE_MOD-Callback ist Absicht: Contao\Backend::getBackendModule()
 * greift einen eigenen Callback nur, wenn der Key auch in BE_MOD steht,
 * und instanziiert ihn dann per System::importStatic() - also ohne
 * Dependency Injection. Ein unbekannter Key fällt dagegen stillschweigend
 * auf die normale Tabellenansicht zurueck, deren onload_callback hier mit
 * echten injizierten Services laufen kann.
 */
#[AsCallback(table: 'tl_bs_redirect_import', target: 'config.onload')]
class RedirectImportActionListener
{
    public function __construct(private readonly CandidateImporter $importer)
    {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        if ('runImport' !== Input::get('key')) {
            return;
        }

        $id = (int) Input::get('id');

        if ($id < 1) {
            Message::addError('Kein Importlauf ausgewählt.');
            Controller::redirect($this->backUrl(null));
        }

        try {
            $stats = $this->importer->import($id);
        } catch (WaybackException $e) {
            Message::addError($e->getMessage().' Alternativ eine URL-Liste einfügen.');
            Controller::redirect($this->backUrl(null));
        } catch (\RuntimeException $e) {
            Message::addError($e->getMessage());
            Controller::redirect($this->backUrl(null));
        }

        if (0 === $stats['total']) {
            Message::addInfo(
                'Keine neuen URLs gefunden. Zeilen mit gesetztem Ziel bleiben bei einem erneuten Lauf '
                .'unverändert, nur Zeilen ohne Ziel werden neu berechnet.',
            );
            Controller::redirect($this->backUrl($id));
        }

        Message::addConfirmation(\sprintf(
            '%d URLs eingelesen: %d mit sicherem Ziel (ab %d%% Übereinstimmung), %d zum Prüfen, %d ohne Ziel, %d bereits vorhanden, %d nicht als Seite abbildbar.',
            $stats['total'],
            $stats['ready'],
            TargetMatcher::THRESHOLD_AUTO,
            $stats['review'],
            $stats['notarget'],
            $stats['exists'],
            $stats['blocked'],
        ));

        if ($stats['skippedAssets'] > 0) {
            Message::addInfo(\sprintf('%d Datei-URLs (Bilder, CSS, PDF usw.) wurden übersprungen.', $stats['skippedAssets']));
        }

        if ($stats['blocked'] > 0) {
            Message::addInfo(
                'Nicht abbildbar sind URLs mit Query-String ("?id=47") sowie URLs, die nicht zum Prefix/Suffix '
                .'des Startpunkts passen. Dafür bräuchte es eine Rewrite-Regel statt einer Seite im Baum.',
            );
        }

        Controller::redirect($this->backUrl($id));
    }

    /**
     * Nach dem Import direkt in die Vorschlagsliste des Laufs, damit der
     * Redakteur die Treffer sofort sieht. "key" muss dabei raus, sonst
     * laeuft der Import beim naechsten Seitenaufruf erneut.
     */
    private function backUrl(int|null $id): string
    {
        if (null === $id) {
            return Backend::addToUrl('', true, ['key', 'id']);
        }

        return Backend::addToUrl('table=tl_bs_redirect&id='.$id, true, ['key']);
    }
}
