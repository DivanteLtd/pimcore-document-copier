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
use Divante\DocumentCopierBundle\Service\FileService;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DocumentImportCommand extends ContainerAwareCommand
{
    /** @var ImportService */
    protected $importService;

    /** @var FileService */
    protected $fileService;

    /** @var DependencyManager */
    protected $dependencyManager;

    /** @var string */
    protected $kernelProjectDir;

    /**
     * DocumentImportCommand constructor.
     * @param ImportService $importService
     * @param FileService $fileService
     * @param string $kernelProjectDir
     * @param DependencyManager $dependencyManager
     */
    public function __construct(
        ImportService $importService,
        FileService $fileService,
        string $kernelProjectDir,
        DependencyManager $dependencyManager
    ) {
        $this->importService = $importService;
        $this->fileService = $fileService;
        $this->kernelProjectDir = $kernelProjectDir;
        $this->dependencyManager = $dependencyManager;

        parent::__construct();
    }

    /**  */
    protected function configure()
    {
        $this
            ->setName('document-copier:import')
            ->setDescription('Import document from JSON')
            ->addOption(
                'path',
                'f',
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
        if (intval($input->getOption('recursiveDepth')) > 0) {
            $this->importWithDependencies(
                strval($input->getOption('path')),
                intval($input->getOption('recursiveDepth')),
                $output,
                $input->getOption('root')
            );
        } else {
            $this->importSingleDocument(
                strval($input->getOption('path')),
                $output,
                $input->getOption('root')
            );
        }
    }

    /**
     * @param string $path
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     */
    private function importSingleDocument(string $path, OutputInterface $output, ?string $rootDirectory = null): void
    {
        try {
            $dto = $this->fileService->loadDto(strval($path), strval($rootDirectory));
            $document = $this->importService->import($dto);

            if ($document) {
                $output->writeln('<info>Successfully imported document ' . $document->getRealFullPath() . ' (' .
                    $document->getId() . ')</info>');
            } else {
                $output->writeln('<error>Failed to import document ' . $dto->getRealFullPath() .
                    ' (see log for more details)</error>');
            }
        } catch (Exception $e) {
            $output->writeln('<error>Exception while importing ' . $path . '</error>');
        }
    }

    /**
     * @param string $path
     * @param int $maxDepth
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     * @throws Exception
     */
    private function importWithDependencies(
        string $path,
        int $maxDepth,
        OutputInterface $output,
        ?string $rootDirectory = null
    ): void {
        $dto = $this->fileService->loadDto($path, $rootDirectory);

        $dependencies = $this->dependencyManager->findDependencies($dto, $maxDepth, $rootDirectory);
        $output->writeln('<info>Found ' . count($dependencies) . ' dependencies at max depth of ' .
            $maxDepth . '</info>');

        // Before resolving dependencies, make sure every imported document has ID in database
        $this->importService->initDocument($dto, true);
        foreach ($dependencies as $dependencyDto) {
            if ($dependencyDto['type'] === 'document') {
                $portableDependency = $this->fileService->loadDto(
                    strval($dependencyDto['path']),
                    $rootDirectory
                );

                $this->importService->initDocument($portableDependency, true);
            }
        }

        // Import dependencies
        foreach ($dependencies as $dependencyDto) {
            if ($dependencyDto['type'] === 'document') {
                $this->importSingleDocument($dependencyDto['path'], $output, $rootDirectory);
            } elseif ($dependencyDto['type'] === 'asset') {
                $this->importSingleAsset($dependencyDto['path'], $output, $rootDirectory);
            }
        }

        // Import the document
        $this->importSingleDocument($path, $output, $rootDirectory);
    }

    /**
     * @param string $path
     * @param OutputInterface $output
     * @param string|null $rootDirectory
     */
    private function importSingleAsset(string $path, OutputInterface $output, ?string $rootDirectory = null): void
    {
        try {
            $asset = $this->fileService->loadAsset($path, $rootDirectory);
            $output->writeln('<info>Successfully imported asset ' . $asset->getFullPath() .
                ' (' . $asset->getId() . ')</info>');
        } catch (Exception $e) {
            $output->writeln('<error>Error while loading asset ' . $path . ': ' . $e->getMessage() . '</error>');
        }
    }
}
