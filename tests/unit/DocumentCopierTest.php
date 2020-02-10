<?php
namespace Tests\AppBundle\Service\DocumentCopier;

use Codeception\Test\Unit;
use Divante\DocumentCopierBundle\Command\DocumentImportCommand;
use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\DependencyManager;
use Divante\DocumentCopierBundle\Service\ExportService;
use Divante\DocumentCopierBundle\Service\FileService;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Monolog\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Symfony\Component\Console\Tester\CommandTester;


class DocumentCopierTest extends Unit
{
    /** @var ImportService */
    private $importService;

    /** @var ExportService */
    private $exportService;

    /** @var DocumentImportCommand */
    private $importCommand;

    const JSON_PAGE = '/Resources/root1/documents/codecept-document-copier/foo/bar.json';

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

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fileService = $this->construct(FileService::class, ['']);
        $dependencyManager = $this->construct(DependencyManager::class, [$fileService]);

        $this->importService = $this->construct(ImportService::class, [$logger]);
        $this->exportService = $this->construct(ExportService::class, [$logger, $dependencyManager]);

        $this->importCommand = $this->construct(
            DocumentImportCommand::class,
            [$this->importService, $fileService, '', $dependencyManager]
        );
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

    /**
     * @throws Exception
     */
    public function testImport()
    {
        $dto = PortableDocument::fromJson(file_get_contents(__DIR__ . self::JSON_PAGE));
        /** @var Document\Page $document */
        $document = $this->importService->import($dto);

        $this->documentAssertions($document);
    }

    /**
     * @throws Exception
     */
    public function testOverwrite()
    {
        $doc1 = new Document\Page();
        $doc1->setKey('codecept-document-copier');
        $doc1->setParent(Document::getByPath('/'));
        $doc1->save();

        $doc2 = new Document\Page();
        $doc2->setKey('foo');
        $doc2->setParent($doc1);
        $doc2->save();

        $doc3 = new Document\Page();
        $doc3->setKey('bar');
        $doc3->setParent($doc2);
        $doc3->setTitle('overwrite me');
        $doc3->setProperty('navigation_name', 'text', 'overwrite me');
        $doc3->setProperty('my_property', 'text', 'still here');
        $element = Document\Tag::factory('input', 'myInput', $doc3->getId());
        $element->setDataFromResource('overwrite me');
        $doc3->setElement('myInput', $element);
        $doc3->save();

        $dto = PortableDocument::fromJson(file_get_contents(__DIR__ . self::JSON_PAGE));
        /** @var Document\Page $document */
        $document = $this->importService->import($dto);

        $this->documentAssertions($document);
        $this->assertEquals('still here', $document->getProperty('my_property'));
    }

    /**
     * @throws Exception
     */
    public function testOverwriteDifferentType()
    {
        $doc1 = new Document\Page();
        $doc1->setKey('codecept-document-copier');
        $doc1->setParent(Document::getByPath('/'));
        $doc1->save();

        $doc2 = new Document\Page();
        $doc2->setKey('foo');
        $doc2->setParent($doc1);
        $doc2->save();

        $doc3 = new Document\Snippet();
        $doc3->setKey('bar');
        $doc3->setParent($doc2);
        $doc3->setProperty('navigation_name', 'text', 'overwrite me');
        $element = Document\Tag::factory('input', 'myInput', $doc3->getId());
        $element->setDataFromResource('overwrite me');
        $doc3->setElement('myInput', $element);
        $doc3->save();

        $dto = PortableDocument::fromJson(file_get_contents(__DIR__ . self::JSON_PAGE));
        /** @var Document\Page $document */
        $document = $this->importService->import($dto);

        $this->documentAssertions($document);
    }

    /**
     * @throws Exception
     */
    public function testRoundTrip()
    {
        $paths = [
            self::JSON_PAGE,
            '/Resources/root1/documents/codecept-document-copier/snippets/snip.json',
            '/Resources/root1/documents/codecept-document-copier/links/internal-link.json',
            '/Resources/root1/documents/codecept-document-copier/links/hardlink-with-inheritance.json',
            '/Resources/root1/documents/codecept-document-copier/emails/dear-foo.json',
        ];

        foreach ($paths as $path) {
            $originalDto = PortableDocument::fromJson(file_get_contents(__DIR__ . $path));
            $importedDocument = $this->importService->import($originalDto);

            $exportedDto = $this->exportService->export($importedDocument);

            $this->assertEmpty(
                array_diff(
                    json_decode(json_encode($originalDto), true),
                    json_decode(json_encode($exportedDto), true)
                )
            );

            $importedDocument->delete();
            /** @var Document\Page $twiceImportedDocument */
            $twiceImportedDocument = $this->importService->import($exportedDto);

            if ($path === self::JSON_PAGE) {
                $this->documentAssertions($twiceImportedDocument);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testInheritedProperties()
    {
        $dto = PortableDocument::fromJson(file_get_contents(__DIR__ . self::JSON_PAGE));
        $document = $this->importService->import($dto);

        $document->getParent()->setProperty('inheritablePropFromParent', 'text', 'ignore me', false, true);
        $document->getParent()->setProperty('regularPropFromParent', 'text', 'ignore me', false, false);
        $document->getParent()->save();

        $exportedDto = $this->exportService->export($document);

        $this->assertEquals(count($dto->getProperties()), count($exportedDto->getProperties()));
    }

    public function testImportWithDependencies()
    {
        $this->assertNull(Document::getByPath(FileServiceTest::DOCUMENT_PATH));
        $this->assertNull(Asset::getByPath(FileServiceTest::ASSET_PATH));

        foreach ([0, 1, 2, 10] as $recursveDepth) {
            $commandTester = new CommandTester($this->importCommand);
            $commandTester->execute([
                '--path' => FileServiceTest::DOCUMENT_PATH,
                '--root' => __DIR__ . '/Resources/root1',
                '--recursiveDepth' => $recursveDepth,
            ]);

            $document = Document::getByPath(FileServiceTest::DOCUMENT_PATH);
            $this->assertNotNull($document);

            if ($recursveDepth > 0) {
                $asset = Asset::getByPath(FileServiceTest::ASSET_PATH);
                $this->assertNotNull($asset);
            }

            $this->documentAssertions($document, $recursveDepth);
        }
    }

    /**
     * @throws Exception
     */
    public function testImportLinkDocuments()
    {
        $commandTester = new CommandTester($this->importCommand);
        $commandTester->execute([
            '--path' => '/codecept-document-copier/links',
            '--root' => __DIR__ . '/Resources/root1',
            '--recursiveDepth' => 3,
        ]);

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
    public function testImportEmail()
    {
        $dto = PortableDocument::fromJson(
            file_get_contents(__DIR__ . '/Resources/root1/documents/codecept-document-copier/emails/dear-foo.json')
        );
        /** @var Document\Email $document */
        $document = $this->importService->import($dto);

        $this->assertTrue($document instanceof Document\Email);
        $this->assertEquals('/codecept-document-copier/emails/dear-foo', $document->getRealFullPath());
        $this->assertEquals('Test e-mail', $document->getSubject());
        $this->assertEquals('sender@example.com', $document->getFrom());
        $this->assertEquals('reply@example.com', $document->getReplyTo());
        $this->assertEquals('recipient@example.com; otherrecipient@example.com', $document->getTo());
        $this->assertEquals('carboncopy@example.com', $document->getCc());
        $this->assertEquals('blankcopy@example.com; otherblankcopy@example.com', $document->getBcc());
        $this->assertEquals('Hello world from e-mail', $document->getElement('myInput')->getData());
        $this->assertEquals('Dear %Text(firstName)', $document->getElement('myTextarea')->getData());
    }

    /**
     * @param Document\Page $document
     * @param int $dependenciesDepth
     */
    private function documentAssertions(Document\Page $document, int $dependenciesDepth = 0)
    {
        // Document type and path
        $this->assertTrue($document instanceof Document\Page);
        $this->assertEquals('/codecept-document-copier/foo/bar', $document->getRealFullPath());

        // Settings
        $this->assertEquals('My Title', $document->getTitle());
        $this->assertEquals('Żółć', $document->getDescription());
        $this->assertEquals('/myprettyurl', $document->getPrettyUrl());
        $this->assertEquals('@AppBundle\Controller\DocumentController', $document->getController());
        $this->assertEquals('default', $document->getAction());
        $this->assertEquals('Document/poc.html.twig', $document->getTemplate());
        $this->assertEquals(true, boolval($document->isPublished()));

        // Properties
        $this->assertEquals('test', $document->getProperty('navigation_name'));
        $this->assertEquals('testing', $document->getProperty('textProp'));
        $this->assertTrue($document->getProperty('checkboxProp'));
        $this->assertFalse($document->getProperty('uncheckedProp'));

        // Elements
        $this->assertEquals(false, $document->getElement('myCheckbox')->getData());
        $this->assertEquals(1576450800, $document->getElement('myDate1')->getData()->getTimestamp());
        $this->assertEquals(1576490449, $document->getElement('myDate2')->getData()->getTimestamp());
        $this->assertEquals('hello world', $document->getElement('myInput')->getData());
        $this->assertEquals('/foo/baz', $document->getElement('myLink1')->getData()['path']);
        $this->assertEquals('Click me', $document->getElement('myLink1')->getData()['text']);
        $this->assertEquals('https://example.com', $document->getElement('myLink2')->getData()['path']);
        $this->assertEquals('Example', $document->getElement('myLink2')->getData()['text']);
        $this->assertContains('a', $document->getElement('myMultiselect')->getData());
        $this->assertContains('b', $document->getElement('myMultiselect')->getData());
        $this->assertCount(2, $document->getElement('myMultiselect')->getData());
        $this->assertEquals('14', $document->getElement('myNumeric')->getData());
        $this->assertEquals('b', $document->getElement('mySelect')->getData());
        $this->assertEquals([["A1", " A2"], [" B1", "B2 "], [" ", " "]], $document->getElement('myTable')->getData());
        $this->assertEquals('lorem ipsum', $document->getElement('myTextarea')->getData());
        $this->assertContains('<p>dolor sit amet</p>', $document->getElement('myWysiwyg')->getData());
        $this->assertEquals(['1', '2'], $document->getElement('myBlock')->getData());
        $this->assertEquals('Block A', $document->getElement('myBlock:1.heading')->getData());
        $this->assertEquals('Text for block A', $document->getElement('myBlock:1.content')->getData());
        $this->assertEquals('Block B', $document->getElement('myBlock:2.heading')->getData());
        $this->assertEquals('Text for block B', $document->getElement('myBlock:2.content')->getData());


        if ($dependenciesDepth >= 1) {
            // Elements
            /** @var Document\Tag\Image $imageElement */
            $imageElement = $document->getElement('myImage');
            $this->assertEquals('image', $imageElement->getType());
            $this->assertIsInt($imageElement->getData()['id']);

            $image = Asset\Image::getById($imageElement->getData()['id']);
            $this->assertNotNull($image);
            $this->assertEquals('my-asset.png', $image->getFilename());
            $this->assertEquals('/codecept-document-copier/my-dir/my-asset.png', $image->getFullPath());

            /** @var Document\Tag\Snippet $snippetElement */
            $snippetElement = $document->getElement('mySnippet');
            $this->assertEquals('snippet', $snippetElement->getType());
            $this->assertIsInt($snippetElement->getData());

            $snippet = Document::getById($snippetElement->getData());
            $this->assertNotNull($snippet);
            $this->assertEquals('snip', $snippet->getKey());
            $this->assertEquals('/codecept-document-copier/snippets/snip', $snippet->getRealFullPath());
            $this->assertEquals('Hello world from snippet', $snippet->getElement('myInput')->getData());

            // Children
            $this->assertCount(1, $document->getChildren());
            /** @var Document\Page $child */
            $child = $document->getChildren()[0];
            $this->assertEquals('/codecept-document-copier/foo/bar/bar-child', $child->getRealFullPath());
            $this->assertEquals('Hello world from child document', $child->getElement('myInput')->getData());

            // Properties
            $docProp = $document->getProperty('docProp');
            $this->assertNotNull($docProp);
            $this->assertEquals('/codecept-document-copier/foo/baz', $docProp->getRealFullPath());
            $this->assertEquals('Hello world from baz', $docProp->getElement('myInput')->getData());

            $assetProp = $document->getProperty('assetProp');
            $this->assertNotNull($assetProp);
            $this->assertEquals('my-asset.png', $assetProp->getFilename());
            $this->assertEquals('/codecept-document-copier/my-dir/my-asset.png', $assetProp->getFullPath());

            if ($dependenciesDepth >= 2) {
                /** @var Document\Tag\Snippet $anotherSnippetElement */
                $anotherSnippetElement = $child->getElement('mySnippet');
                $this->assertEquals('snippet', $anotherSnippetElement->getType());
                $this->assertIsInt($anotherSnippetElement->getData());

                $anotherSnippet = Document::getById($anotherSnippetElement->getData());
                $this->assertNotNull($anotherSnippet);
                $this->assertEquals('another-snip', $anotherSnippet->getKey());
                $this->assertEquals(
                    'Hello world from another snippet',
                    $anotherSnippet->getElement('myInput')->getData()
                );

                $this->assertNotNull($docProp->getProperty('selfReferencingProp'));
                $this->assertNotNull($docProp->getProperty('loopProp'));

                if ($dependenciesDepth >= 3) {
                    $repeatedSnippetElement = $anotherSnippet->getElement('mySnippet');
                    $this->assertEquals('snippet', $repeatedSnippetElement->getType());
                    $this->assertIsInt($repeatedSnippetElement->getData());
                    $this->assertEquals($snippetElement->getData(), $repeatedSnippetElement->getData());
                }
            }
        }
    }
}
