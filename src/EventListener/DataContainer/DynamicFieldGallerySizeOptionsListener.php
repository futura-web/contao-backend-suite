<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Image\ImageSizes;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liefert die wählbaren Bildgrößen-Presets (tl_image_size) für das
 * "gallerySize"-Feld im Feld-Builder-eigenen Formular - 1:1 nach dem
 * Muster von Contao-Core's ImageSizeOptionsListener (dort zuständig für
 * tl_content.size, tl_module.imgSize usw.).
 */
#[AsCallback(table: 'tl_dynamic_field', target: 'fields.gallerySize.options')]
class DynamicFieldGallerySizeOptionsListener
{
    public function __construct(
        private readonly Security $security,
        private readonly ImageSizes $imageSizes,
    ) {
    }

    public function __invoke(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return [];
        }

        return $this->imageSizes->getOptionsForUser($user);
    }
}
