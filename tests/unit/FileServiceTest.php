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
use Pimcore\Model\Document;
use Symfony\Component\Filesystem\Exception\IOException;

class FileServiceTest extends Unit
{
    const DOCUMENT_PATH = '/codecept-document-copier/foo/bar';
    const ASSET_PATH = '/codecept-document-copier/my-dir/my-asset.png';
    const ROOT = __DIR__ . '/Resources/root1';
    const NEW_ROOT = __DIR__ . '/Resources/test_root1';

    /** @var FileService */
    private $fileService;

    /**
     * @throws Exception
     */
    protected function _before()
    {
        $documentRoot = Document::getByPath('/codecept-document-copier');

        if ($documentRoot) {
            $documentRoot->delete();
        }

        $assetRoot = Asset::getByPath('/codecept-document-copier');

        if ($assetRoot) {
            $assetRoot->delete();
        }

        $this->fileService = $this->construct(FileService::class, ['']);
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        $documentRoot = Document::getByPath('/codecept-document-copier');

        if ($documentRoot) {
            $documentRoot->delete();
        }

        $assetRoot = Asset::getByPath('/codecept-document-copier');

        if ($assetRoot) {
            $assetRoot->delete();
        }
    }

    public function testLoadDocument()
    {
        $dto = $this->fileService->loadDto(self::DOCUMENT_PATH, self::ROOT);
        $this->assertEquals(self::DOCUMENT_PATH, $dto->getRealFullPath());
        $this->assertEquals('page', $dto->getType());
        $this->assertEquals('My Title', $dto->getSettings()['title']);
    }

    /**
     * @throws Exception
     */
    public function testLoadAsset()
    {
        $this->assertNull(Asset::getByPath(self::ASSET_PATH));

        $asset = $this->fileService->loadAsset(self::ASSET_PATH, self::ROOT);

        $this->assertNotNull(Asset::getByPath(self::ASSET_PATH));
        $this->assertEquals(self::ASSET_PATH, $asset->getFullPath());
        $this->assertEquals(
            file_get_contents(self::ROOT . '/assets' . self::ASSET_PATH),
            $asset->getData()
        );
    }

    public function testLoadNonExistentDocument()
    {
        try {
            $this->fileService->loadDto(self::DOCUMENT_PATH . 'baz', self::ROOT);
            $this->assertTrue(false);  // Unreachable
        } catch (IOException $e) {
            $this->assertContains('does not exist', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function testLoadNonExistentAsset()
    {
        try {
            $this->fileService->loadAsset('/codecept-document-copier/my-dir/not-my-asset.png', self::ROOT);
            $this->assertTrue(false);  // Unreachable
        } catch (IOException $e) {
            $this->assertContains('does not exist', $e->getMessage());
        }
    }

    public function testSaveDocument()
    {
        $this->assertNotEmpty(file_get_contents(self::ROOT . '/documents' . self::DOCUMENT_PATH . '.json'));
        $dto = PortableDocument::fromJson(
            file_get_contents(self::ROOT . '/documents' . self::DOCUMENT_PATH . '.json')
        );

        $expectedFilePath = self::NEW_ROOT . '/documents' . self::DOCUMENT_PATH . '.json';
        $this->assertFalse(is_readable($expectedFilePath));

        $filePath = $this->fileService->saveDto($dto, self::NEW_ROOT);

        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));

        $this->assertEmpty(
            array_diff(
                json_decode(file_get_contents(self::ROOT . '/documents' . self::DOCUMENT_PATH . '.json'), true),
                json_decode(file_get_contents(self::NEW_ROOT . '/documents' . self::DOCUMENT_PATH . '.json'), true)
            )
        );

        unlink(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier/foo/bar.json');
        rmdir(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier/foo');
        rmdir(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier');
        rmdir(__DIR__ . '/Resources/test_root1/documents');
        rmdir(__DIR__ . '/Resources/test_root1');
    }

    /**
     * @throws Exception
     */
    public function testSaveAsset()
    {
        $this->assertNotEmpty(file_get_contents(self::ROOT . '/assets' . self::ASSET_PATH));
        $asset = $this->fileService->loadAsset(self::ASSET_PATH, self::ROOT);

        $expectedFilePath = self::NEW_ROOT . '/assets' . self::ASSET_PATH;
        $this->assertFalse(is_readable($expectedFilePath));

        $filePath = $this->fileService->saveAsset($asset, self::NEW_ROOT);

        $this->assertEquals($expectedFilePath, $filePath);
        $this->assertTrue(is_readable($expectedFilePath));

        $this->assertEquals(
            file_get_contents(self::ROOT . '/assets' . self::ASSET_PATH),
            file_get_contents($expectedFilePath)
        );

        unlink(__DIR__ . '/Resources/test_root1/assets/codecept-document-copier/my-dir/my-asset.png');
        rmdir(__DIR__ . '/Resources/test_root1/assets/codecept-document-copier/my-dir');
        rmdir(__DIR__ . '/Resources/test_root1/assets/codecept-document-copier');
        rmdir(__DIR__ . '/Resources/test_root1/assets');
        rmdir(__DIR__ . '/Resources/test_root1');
    }

    public function testDocumentRoundTrip()
    {
        $initialJson = file_get_contents(self::ROOT . '/documents' . self::DOCUMENT_PATH . '.json');
        $this->assertNotEmpty($initialJson);

        $loadedDto = $this->fileService->loadDto(self::DOCUMENT_PATH, self::ROOT);

        $this->fileService->saveDto($loadedDto, self::NEW_ROOT);
        $reSavedJson = file_get_contents(self::NEW_ROOT . '/documents' . self::DOCUMENT_PATH . '.json');

        $this->assertEmpty(
            array_diff(
                json_decode($initialJson, true),
                json_decode($reSavedJson, true)
            )
        );

        unlink(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier/foo/bar.json');
        rmdir(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier/foo');
        rmdir(__DIR__ . '/Resources/test_root1/documents/codecept-document-copier');
        rmdir(__DIR__ . '/Resources/test_root1/documents');
        rmdir(__DIR__ . '/Resources/test_root1');
    }
}
