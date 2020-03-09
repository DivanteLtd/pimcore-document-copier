<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\ElementSerializer;
use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Psr\Log\LoggerInterface;

/**
 * Class ImportService
 * @package Divante\DocumentCopierBundle\Service
 */
class ImportService
{
    /** @var LoggerInterface */
    protected $logger;

    /**
     * DocumentCopierService constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param PortableDocument $dto
     * @return Document|null
     * @throws Exception
     */
    public function import(PortableDocument $dto): ?Document
    {
        $document = $this->initDocument($dto);

        if ($document instanceof Document\PageSnippet) {
            $this->importElements($dto, $document);
        }
        $this->importSettings($dto, $document);
        $this->importProperties($dto, $document);

        try {
            $document->save();
        } catch (Exception $e) {
            $this->logger->error('[DocumentCopier] Failed to save imported document: ' . $e->getMessage());

            return null;
        }

        return $document;
    }

    /**
     * @param PortableDocument $dto
     * @param bool $save
     * @return Document
     * @throws Exception
     */
    public function initDocument(PortableDocument $dto, bool $save = false): Document
    {
        $document = Document::getByPath($dto->getRealFullPath());

        if (!$document) {
            // Document doesn't exist
            $document = $this->createDocument($dto);
        } elseif ($document && $document->getType() !== $dto->getType()) {
            // Document has different type
            $document->delete();
            $document = $this->createDocument($dto);
        }

        if ($save) {
            $document->save();
        }

        return $document;
    }

    /**
     * @param PortableDocument $dto
     * @return Document|null
     * @throws Exception
     */
    private function createDocument(PortableDocument $dto): ?Document
    {
        $documentClass = '\\Pimcore\\Model\\Document\\' . ucfirst($dto->getType());
        /** @var Document $document */
        $document = new $documentClass();
        $pathParts = explode('/', $dto->getRealFullPath());
        $document->setKey(end($pathParts));

        foreach ($dto->getAncestors() as $ancestorPath => $ancestorType) {
            $ancestor = Document::getByPath($ancestorPath);

            if (!$ancestor) {
                $ancestorClass = '\\Pimcore\\Model\\Document\\' . ucfirst($ancestorType);
                /** @var Document $ancestor */
                $ancestor = new $ancestorClass();
                $ancestor->setParent($previousAncestor ?? Document::getByPath('/'));
                $key = end(explode('/', $ancestorPath));
                $ancestor->setKey($key);
                $ancestor->save();
            }

            $previousAncestor = $ancestor;
        }

        $document->setParent($ancestor ?? Document::getByPath('/'));

        return $document;
    }

    /**
     * @param PortableDocument $dto
     * @param Document\PageSnippet $document
     */
    private function importElements(PortableDocument $dto, Document\PageSnippet $document): void
    {
        foreach ($dto->getElements() as $elementName => $elementDao) {
            try {
                $elementType = $elementDao['type'];

                if (!in_array($elementType, PortableDocument::SUPPORTED_EDITABLE_TYPES)) {
                    continue;
                }

                switch (strval($elementType)) {
                    case 'date':
                        ElementSerializer\Date::setData($elementName, $elementDao, $document);
                        break;
                    case 'image':
                        ElementSerializer\Image::setData($elementName, $elementDao, $document);
                        break;
                    case 'snippet':
                        ElementSerializer\Snippet::setData($elementName, $elementDao, $document);
                        break;
                    default:
                        ElementSerializer\GenericType::setData($elementName, $elementDao, $document);
                }
            } catch (Exception $e) {
                $this->logger->error('[DocumentCopier] Exception while importing element ' . $elementName . ': ' .
                    $e->getMessage());

                continue;
            }
        }
    }

    /**
     * @param PortableDocument $dto
     * @param Document $document
     */
    private function importSettings(PortableDocument $dto, Document $document): void
    {
        foreach ($dto->getSettings() as $setting => $value) {
            $method = 'set' . ucfirst($setting);

            if (method_exists($document, $method)) {
                $document->{$method}($value);
            }
        }

        if ($document instanceof Document\Link) {
            $this->importLinkSetting($dto, $document);
        } elseif ($document instanceof Document\Hardlink) {
            $this->importSourceSetting($dto, $document);
        }
    }

    /**
     * @param PortableDocument $dto
     * @param Document\Link $document
     */
    private function importLinkSetting(PortableDocument $dto, Document\Link $document): void
    {
        $link = $dto->getSettings()['_link'] ?? null;
        $linkType = $dto->getSettings()['linkType'] ?? null;

        if ($linkType === 'internal') {
            $linkedDocument = Document::getByPath($link);

            if ($linkedDocument) {
                $document->setInternalType('document');
                $document->setInternal($linkedDocument->getId());
            }
        } elseif ($linkType === 'direct') {
            $document->setDirect($link);
        }
    }

    /**
     * @param PortableDocument $dto
     * @param Document\Hardlink $document
     */
    private function importSourceSetting(PortableDocument $dto, Document\Hardlink $document): void
    {
        $source = $dto->getSettings()['_source'] ?? null;

        $linkedDocument = Document::getByPath($source);

        if ($linkedDocument) {
            $document->setSourceId($linkedDocument->getId());
        }
    }

    /**
     * @param PortableDocument $dto
     * @param Document $document
     */
    private function importProperties(PortableDocument $dto, Document $document): void
    {
        foreach ($dto->getProperties() as $name => $property) {
            $data = $property['data'];

            if ($property['type'] === 'document') {
                $data = Document::getByPath($data);
            } elseif ($property['type'] === 'asset') {
                $data = Asset::getByPath($data);
            }

            $document->setProperty(
                $name,
                $property['type'],
                $data,
                false,
                $property['inheritable']
            );
        }
    }
}
