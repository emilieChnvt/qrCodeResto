<?php
namespace App\Service;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class MenuFileNamer implements NamerInterface
{
    public function name($object, PropertyMapping $mapping): string //obj = entité associéa l'upload => restop
    {
        $fileName = $mapping->getFileName($object); // nom actuel
        $extension = pathinfo($fileName, PATHINFO_EXTENSION); //extrait l'etension
        return 'menu-' . $object->getName() .  '.' . $extension;
    }
}
