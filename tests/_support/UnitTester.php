<?php
namespace Tests;

use FilesystemIterator;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
 */
class UnitTester extends \Codeception\Actor
{
    use _generated\UnitTesterActions;

    /**
     * @return string
     */
    public static function getRootDirectory(): string
    {
        return __DIR__ . '/../../app/Resources/test_root';
    }

    /**
     * @return string
     */
    public static function getNewRootDirectory(): string
    {
        return __DIR__ . '/../../app/Resources/new_root';
    }

    /**  */
    public static function clearNewRootDirectory(): void
    {
        $dir = self::getNewRootDirectory();

        if (!is_dir($dir)) {
            return;
        }

        $directoryIterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($recursiveIterator as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        if (is_dir($dir)) {
            throw new \RuntimeException('Could not delete directory ' . $dir);
        }
    }

    /**
     * @param string $json
     * @param string $otherJson
     * @return array
     */
    public static function jsonDiff(string $json, string $otherJson): array
    {
        return array_diff(json_decode($json, true), json_decode($otherJson, true));
    }

    /**
     * @throws \Exception
     */
    public static function cleanUp(): void
    {
        $documentRoot = Document::getByPath('/codecept-document-copier');

        if ($documentRoot) {
            $documentRoot->delete();
        }

        $assetRoot = Asset::getByPath('/codecept-document-copier');

        if ($assetRoot) {
            $assetRoot->delete();
        }

        self::clearNewRootDirectory();
    }
}
