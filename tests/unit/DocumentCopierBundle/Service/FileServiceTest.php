<?php
/**
 * @category    enea5
 * @date        20/01/2020 12:54
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\Unit\AppBundle\Service\DocumentCopier;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\FileService;
use Exception;
use Pimcore\Model\Asset;
use Symfony\Component\Filesystem\Exception\IOException;
use Tests\Unit\DocumentCopierBundle\AbstractDocumentCopierTest;

class FileServiceTest extends AbstractDocumentCopierTest
{
    /** @var FileService */
    private $fileService;

    public function testLoadDocument()
    {
        // when
        $dto = $this->fileService->loadDto(self::DOCUMENT_PATH, $this->getRootDirectory());

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
        $asset = $this->fileService->loadAsset(self::ASSET_PATH, $this->getRootDirectory());

        // then
        $this->assertNotNull(Asset::getByPath(self::ASSET_PATH));
        $this->assertEquals(self::ASSET_PATH, $asset->getFullPath());
        $this->assertEquals(
            file_get_contents($this->getRootDirectory() . '/assets' . self::ASSET_PATH),
            $asset->getData()
        );
    }

    public function testLoadNonExistentDocument()
    {
        try {
            // when
            $this->fileService->loadDto(self::INVALID_DOCUMENT_PATH, $this->getRootDirectory());

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
            $this->fileService->loadAsset(self::INVALID_ASSET_PATH, $this->getRootDirectory());

            $this->assertTrue(false);  // unreachable
        } catch (IOException $e) {
            // then
            $this->assertContains('does not exist', $e->getMessage());
        }
    }

    public function testSaveDocument()
    {
        // given
        $this->assertNotEmpty(file_get_contents($this->getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json'));

        $dto = PortableDocument::fromJson(
            file_get_contents($this->getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json')
        );

        $expectedFilePath = $this->getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json';
        $this->assertFalse(is_readable($expectedFilePath));

        // when
        $filePath = $this->fileService->saveDto($dto, $this->getNewRootDirectory());

        // then
        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));
        $this->assertEmpty(
            $this->jsonDiff(
                file_get_contents($this->getRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json'),
                file_get_contents($this->getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json')
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testSaveAsset()
    {
        // given
        $asset = $this->fileService->loadAsset(self::ASSET_PATH, $this->getRootDirectory());
        $this->assertNotNull($asset);

        $expectedFilePath = $this->getNewRootDirectory(). '/assets' . self::ASSET_PATH;
        $this->assertFalse(is_readable($expectedFilePath));

        // when
        $filePath = $this->fileService->saveAsset($asset, $this->getNewRootDirectory());

        // then
        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));

        $this->assertEquals(
            file_get_contents($this->getRootDirectory() . '/assets' . self::ASSET_PATH),
            file_get_contents($expectedFilePath)
        );
    }

    public function testDocumentRoundTrip()
    {
        // given
        $initialJson = file_get_contents($this->getRootDirectory(). '/documents' . self::DOCUMENT_PATH . '.json');

        // when
        $loadedDto = $this->fileService->loadDto(self::DOCUMENT_PATH, $this->getRootDirectory());
        $this->fileService->saveDto($loadedDto, $this->getNewRootDirectory());

        // then
        $resavedJson = file_get_contents($this->getNewRootDirectory() . '/documents' . self::DOCUMENT_PATH . '.json');
        $this->assertEmpty($this->jsonDiff($initialJson, $resavedJson));
    }

    /**
     * @throws Exception
     */
    protected function _before()
    {
        $this->cleanUp();
        $this->fileService = $this->construct(FileService::class, ['']);
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        $this->cleanUp();
    }
}
