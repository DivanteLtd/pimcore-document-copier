<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 15:25
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace unit\DocumentCopierBundle\Command;

use Codeception\Test\Unit;
use Divante\DocumentCopierBundle\Command\DocumentImportCommand;
use Divante\DocumentCopierBundle\Service\DependencyManager;
use Divante\DocumentCopierBundle\Service\FileService;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Monolog\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\Service\DocumentCopier\FileServiceTest;
use Tests\UnitTester;
use unit\DocumentCopierBundle\Service\ImportServiceTest;

class ImportDocumentCommandTest extends Unit
{
    /** @var DocumentImportCommand */
    private $importCommand;

    public function testImportWithDependencies()
    {
        // given
        $this->assertNull(Document::getByPath(FileServiceTest::DOCUMENT_PATH));
        $this->assertNull(Asset::getByPath(FileServiceTest::ASSET_PATH));

        foreach ([0, 1, 2, 10] as $recursveDepth) {
            // when
            $commandTester = new CommandTester($this->importCommand);
            $commandTester->execute([
                '--path' => FileServiceTest::DOCUMENT_PATH,
                '--root' => __DIR__ . '/Resources/root1',
                '--recursiveDepth' => $recursveDepth,
            ]);

            // then
            $document = Document::getByPath(FileServiceTest::DOCUMENT_PATH);
            $this->assertNotNull($document);

            if ($recursveDepth > 0) {
                $asset = Asset::getByPath(FileServiceTest::ASSET_PATH);
                $this->assertNotNull($asset);
            }

            (new ImportServiceTest())->documentAssertions($document, $recursveDepth);
        }
    }

    /**
     * @throws Exception
     */
    public function testImportLinkDocuments()
    {
        // when
        $commandTester = new CommandTester($this->importCommand);
        $commandTester->execute([
            '--path' => '/codecept-document-copier/links',
            '--root' => __DIR__ . '/Resources/root1',
            '--recursiveDepth' => 3,
        ]);

        // then
        $document = Document::getByPath('/codecept-document-copier/links/internal-link');
        $this->assertTrue($document instanceof Document\Link);
        $this->assertTrue($document->getLinktype() === 'internal');
        $linkedDocument = Document::getById($document->getInternal());
        $this->assertEquals('/codecept-document-copier/foo/bar', $linkedDocument->getRealFullPath());

        $document = Document::getByPath('/codecept-document-copier/links/direct-link');
        $this->assertTrue($document instanceof Document\Link);
        $this->assertTrue($document->getLinktype() === 'direct');
        $this->assertEquals('https://example.com', $document->getLink());

        $document = Document::getByPath('/codecept-document-copier/links/hardlink-with-inheritance');
        $this->assertTrue($document instanceof Document\Hardlink);
        $this->assertTrue($document->getChildrenFromSource());
        $this->assertTrue($document->getPropertiesFromSource());
        $linkedDocument = Document::getById($document->getSourceId());
        $this->assertEquals('/codecept-document-copier/foo/bar', $linkedDocument->getRealFullPath());

        $document = Document::getByPath('/codecept-document-copier/links/hardlink-without-inheritance');
        $this->assertTrue($document instanceof Document\Hardlink);
        $this->assertFalse($document->getChildrenFromSource());
        $this->assertFalse($document->getPropertiesFromSource());
        $linkedDocument = Document::getById($document->getSourceId());
        $this->assertEquals('/codecept-document-copier/foo/bar', $linkedDocument->getRealFullPath());
    }

    /**
     * @throws Exception
     */
    protected function _before()
    {
        UnitTester::cleanUp();

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $importService = $this->construct(ImportService::class, [$logger]);
        $fileService = $this->construct(FileService::class, ['']);
        $dependencyManager = $this->construct(DependencyManager::class, [$fileService]);

        $this->importCommand = $this->construct(
            DocumentImportCommand::class,
            [$importService, $fileService, '', $dependencyManager]
        );
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        UnitTester::cleanUp();
    }
}
