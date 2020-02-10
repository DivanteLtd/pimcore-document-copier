<?php
/**
 * @category    enea5
 * @date        20/01/2020 12:54
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\AppBundle\Service\DocumentCopier;

use Codeception\Test\Unit;
use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\FileService;
use Exception;
use Pimcore\Model\Asset;
use Symfony\Component\Filesystem\Exception\IOException;
use Tests\UnitTester;

class FileServiceTest extends Unit
{
    /** @var FileService */
    private $fileService;

    const DOCUMENT_PATH = '/codecept-document-copier/foo/bar';
    const INVALID_DOCUMENT_PATH = '/codecept-document-copier/foo/not-bar';
    const ASSET_PATH = '/codecept-document-copier/my-dir/my-asset.png';
    const INVALID_ASSET_PATH = '/codecept-document-copier/my-dir/not-my-asset.png';

    public function testLoadDocument()
    {
        // when
        $dto = $this->fileService->loadDto(self::DOCUMENT_PATH, UnitTester::getRootDirectory());

        // then
        $this->assertEquals(self::DOCUMENT_PATH, $dto->getRealFullPath());
        $this->assertEquals('page', $dto->getType());
        $this->assertEquals('My Title', $dto->getSettings()['title']);
    }

    /**
     * @throws Exception
     */
    public function testLoadAsset()
    {
        // given
        $this->assertNull(Asset::getByPath(self::ASSET_PATH));

        // when
        $asset = $this->fileService->loadAsset(self::ASSET_PATH, UnitTester::getRootDirectory());

        // then
        $this->assertNotNull(Asset::getByPath(self::ASSET_PATH));
        $this->assertEquals(self::ASSET_PATH, $asset->getFullPath());
        $this->assertEquals(
            file_get_contents(UnitTester::getRootDirectory() . '/assets' . self::ASSET_PATH),
            $asset->getData()
        );
    }

    public function testLoadNonExistentDocument()
    {
        try {
            // when
            $this->fileService->loadDto(self::INVALID_DOCUMENT_PATH, UnitTester::getRootDirectory());

            $this->assertTrue(false);  // unreachable
        } catch (IOException $e) {
            // then
            $this->assertContains('does not exist', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function testLoadNonExistentAsset()
    {
        try {
            // when
            $this->fileService->loadAsset(self::INVALID_ASSET_PATH, UnitTester::getRootDirectory());

            $this->assertTrue(false);  // unreachable
        } catch (IOException $e) {
            // then
            $this->assertContains('does not exist', $e->getMessage());
        }
    }

    public function testSaveDocument()
    {
        // given
        $this->assertNotEmpty(file_get_contents(UnitTester::getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json'));

        $dto = PortableDocument::fromJson(
            file_get_contents(UnitTester::getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json')
        );

        $expectedFilePath = UnitTester::getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json';
        $this->assertFalse(is_readable($expectedFilePath));

        // when
        $filePath = $this->fileService->saveDto($dto, UnitTester::getNewRootDirectory());

        // then
        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));
        $this->assertEmpty(
            UnitTester::jsonDiff(
                file_get_contents(UnitTester::getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json'),
                file_get_contents(UnitTester::getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json')
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testSaveAsset()
    {
        // given
        $asset = $this->fileService->loadAsset(self::ASSET_PATH, UnitTester::getRootDirectory());
        $this->assertNotNull($asset);

        $expectedFilePath = UnitTester::getNewRootDirectory(). '/assets' . self::ASSET_PATH;
        $this->assertFalse(is_readable($expectedFilePath));

        // when
        $filePath = $this->fileService->saveAsset($asset, UnitTester::getNewRootDirectory());

        // then
        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));

        $this->assertEquals(
            file_get_contents(UnitTester::getRootDirectory() . '/assets' . self::ASSET_PATH),
            file_get_contents($expectedFilePath)
        );
    }

    public function testDocumentRoundTrip()
    {
        // given
        $initialJson = file_get_contents(UnitTester::getRootDirectory(). '/documents' . self::DOCUMENT_PATH . '.json');

        // when
        $loadedDto = $this->fileService->loadDto(self::DOCUMENT_PATH, UnitTester::getRootDirectory());
        $this->fileService->saveDto($loadedDto, UnitTester::getNewRootDirectory());

        // then
        $resavedJson = file_get_contents(UnitTester::getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json');
        $this->assertEmpty(UnitTester::jsonDiff($initialJson, $resavedJson));
    }

    /**
     * @throws Exception
     */
    protected function _before()
    {
        UnitTester::cleanUp();
        $this->fileService = $this->construct(FileService::class, ['']);
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        UnitTester::cleanUp();
    }
}
