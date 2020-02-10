<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\ElementSerializer;

use Divante\DocumentCopierBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Document\Tag;

/**
 * Class Link
 * @package Divante\DocumentCopierBundle\ElementSerializer
 */
class Link extends GenericType
{
    /**
     * @param Tag $element
     * @return mixed
     * @throws InvalidElementTypeException
     */
    public static function getData(Tag $element)
    {
        if ($element instanceof Tag\Link) {
            $data = $element->getData();
            unset($data['internal']);
            unset($data['internalId']);

            return $data;
        } else {
            throw new InvalidElementTypeException();
        }
    }
}
