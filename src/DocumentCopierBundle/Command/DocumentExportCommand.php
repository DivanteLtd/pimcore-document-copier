<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Command;

use Divante\DocumentCopierBundle\Service\DependencyManager;
use Divante\DocumentCopierBundle\Service\ExportService;
use Divante\DocumentCopierBundle\Service\FileService;
use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Class DocumentExportCommand
 * @package Divante\DocumentCopierBundle\Command
 */
class DocumentExportCommand extends ContainerAwareCommand
{
    /** @var ExportService */
    protected $exportService;

    /** @var FileService */
    protected $fileService;

    /** @var DependencyManager */
    protected $dependencyManager;

    /** @var string */
    protected $kernelProjectDir;

    /**
     * DocumentImportCommand constructor.
     * @param ExportService $importService
     * @param FileService $fileService
     * @param DependencyManager $dependencyManager
     * @param string $kernelProjectDir
     */
    public function __construct(
        ExportService $importService,
        FileService $fileService,
        DependencyManager $dependencyManager,
        string $kernelProjectDir
    ) {
        $this->exportService = $importService;
        $this->fileService = $fileService;
        $this->kernelProjectDir = $kernelProjectDir;
        $this->dependencyManager = $dependencyManager;

        parent::__construct();
    }

    /**  */
    protected function configure()
    {
        $this
            ->setName('document-copier:export')
            ->setDescription('Export document to JSON')
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Document path'
            )
            ->addOption(
                'root',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Resources root',
                $this->fileService->getDefaultRootDirectory()
            )
            ->addOption(
                'recursiveDepth',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Max depth of dependency tree. If 0, dependent documents and assets aren\'t exported',
                0
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $document = Document::getByPath($input->getOption('path'));

        if (!$document) {
            $output->writeln('<error>Document does not exist (' . $input->getOption('path') . ')</error>');
            return;
        }

        try {
            $this->exportSingleDocument($document, $output, $input->getOption('root'));

            if (intval($input->getOption('recursiveDepth')) > 0) {
                $dependencies = $this->dependencyManager->findDependencies(
                    $document,
                    intval($input->getOption('recursiveDepth')),
                    $input->getOption('root')
                );

                $output->writeln('<info>Found ' . count($dependencies) . ' dependencies at max depth of ' .
                    $input->getOption('recursiveDepth') . '</info>');

                $this->exportDependencies($dependencies, $output, $input->getOption('root'));
            }
        } catch (IOException $e) {
            $output->writeln('<error>Error while writing JSON to file: ' . $e->getMessage() . '</error>');
            return;
        } catch (Exception $e) {
            $output->writeln('<error>Export error: ' . $e->getMessage() . '</error>');
            return;
        }
    }

    /**
     * @param Document $document
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     * @return bool
     */
    private function exportSingleDocument(
        Document $document,
        OutputInterface $output,
        ?string $rootDirectory = null
    ): bool {
        try {
            $dto = $this->exportService->export($document);
            $filePath = $this->fileService->saveDto($dto, $rootDirectory);
            $output->writeln('<info>Successfully exported document ' . $document->getRealFullPath() .
                ' (' . $document->getId() . ') to file: ' . $filePath . '</info>');

            return true;
        } catch (IOException $e) {
            $output->writeln('<error>Error while writing JSON to file: ' . $e->getMessage() . '</error>');

            return false;
        }
    }

    /**
     * @param Asset $asset
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     * @return bool
     */
    private function exportSingleAsset(
        Asset $asset,
        OutputInterface $output,
        ?string $rootDirectory = null
    ): bool {
        try {
            $filePath = $this->fileService->saveAsset($asset, $rootDirectory);
            $output->writeln('<info>Successfully exported asset ' . $asset->getFullPath() .
                ' (' . $asset->getId() . ') to file: ' . $filePath . '</info>');

            return true;
        } catch (IOException $e) {
            $output->writeln('<error>Error while saving asset to file: ' . $e->getMessage() . '</error>');

            return false;
        }
    }

    /**
     * @param array $dependencies
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     */
    private function exportDependencies(
        array $dependencies,
        OutputInterface $output,
        ?string $rootDirectory = null
    ): void {
        $dependencies = array_unique($dependencies, SORT_REGULAR);

        foreach ($dependencies as $dependencyDto) {
            if ($dependencyDto['type'] === 'document') {
                $dependency = Document::getByPath($dependencyDto['path']);

                if ($dependency instanceof Document) {
                    $this->exportSingleDocument($dependency, $output, $rootDirectory);
                }
            } elseif ($dependencyDto['type'] === 'asset') {
                $dependency = Asset::getByPath($dependencyDto['path']);

                if ($dependency instanceof Asset) {
                    $this->exportSingleAsset($dependency, $output, $rootDirectory);
                }
            }
        }
    }
}
