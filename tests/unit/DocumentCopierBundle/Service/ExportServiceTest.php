<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 15:09
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\DependencyManager;
use Divante\DocumentCopierBundle\Service\ExportService;
use Divante\DocumentCopierBundle\Service\FileService;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Monolog\Logger;
use Pimcore\Model\Document\Page;
use Tests\DocumentCopierBundle\AbstractDocumentCopierTest;

class ExportServiceTest extends AbstractDocumentCopierTest
{
    /** @var ImportService */
    private $importService;

    /** @var ExportService */
    private $exportService;

    /**
     * @throws Exception
     */
    public function testInheritedProperties()
    {
        // given
        $dto = PortableDocument::fromJson(
            file_get_contents($this->getRootDirectory() . self::DOCUMENT_JSON_PATH)
        );
        $document = $this->importService->import($dto);

        $document->getParent()->setProperty('inheritablePropFromParent', 'text', 'ignore me', false, true);
        $document->getParent()->setProperty('regularPropFromParent', 'text', 'ignore me', false, false);
        $document->getParent()->save();

        // when
        $exportedDto = $this->exportService->export($document);

        // then
        $this->assertEquals(count($dto->getProperties()), count($exportedDto->getProperties()));
    }

    /**
     * @throws Exception
     */
    public function testRoundTrip()
    {
        $paths = [self::DOCUMENT_JSON_PATH, self::LINK_JSON_PATH, self::HARDLINK_JSON_PATH, self::EMAIL_JSON_PATH];

        foreach ($paths as $path) {
            // given
            $originalDto = PortableDocument::fromJson(
                file_get_contents($this->getRootDirectory() . $path)
            );
            $importedDocument = $this->importService->import($originalDto);

            // when
            $exportedDto = $this->exportService->export($importedDocument);

            // then
            $this->assertEmpty($this->jsonDiff(json_encode($originalDto), json_encode($exportedDto)));

            if ($path === self::DOCUMENT_JSON_PATH) {
                // and when
                $importedDocument->delete();
                $twiceImportedDocument = $this->importService->import($exportedDto);

                // then
                /** @var Page $twiceImportedDocument */
                $this->documentAssertions($twiceImportedDocument);
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function _before()
    {
        $this->cleanUp();

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fileService = $this->construct(FileService::class, ['']);
        $dependencyManager = $this->construct(DependencyManager::class, [$fileService]);
        $this->exportService = $this->construct(ExportService::class, [$logger, $dependencyManager]);
        $this->importService = $this->construct(ImportService::class, [$logger]);
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        $this->cleanUp();
    }
}
