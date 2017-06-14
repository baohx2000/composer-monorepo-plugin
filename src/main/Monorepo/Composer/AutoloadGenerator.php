<?php

namespace Monorepo\Composer;

use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Config;
use Composer\Util\Filesystem;

class AutoloadGenerator extends \Composer\Autoload\AutoloadGenerator
{
    public function buildPackageMap(InstallationManager $installationManager, PackageInterface $mainPackage, array $packages)
    {
        $packageMap = parent::buildPackageMap($installationManager, $mainPackage, $packages);

        $packageMap[0][1] = $installationManager->getInstallPath($mainPackage); // hack the install path

        return $packageMap;
    }

    protected function getAutoloadRealFile($useClassMap, $useIncludePath, $targetDirLoader, $useIncludeFiles, $vendorPathCode, $appBaseDirCode, $suffix, $useGlobalIncludePath, $prependAutoloader, $staticPhpVersion = 70000)
    {
        $file = parent::getAutoloadRealFile($useClassMap, $useIncludePath, $targetDirLoader, false, $vendorPathCode, $appBaseDirCode, $suffix, $useGlobalIncludePath, $prependAutoloader, $staticPhpVersion);

        if (! $useIncludeFiles) {
            return $file;
        }

        return $file .= <<<INCLUDE_FILES

\$includeFiles = require __DIR__ . '/autoload_files.php';
foreach (\$includeFiles as \$file) {
    composerRequireOnce$suffix(\$file);
}

function composerRequireOnce$suffix(\$file)
{
    require_once \$file;
}

INCLUDE_FILES;
    }

    public function dump(Config $config, InstalledRepositoryInterface $localRepo, PackageInterface $mainPackage, InstallationManager $installationManager, $targetDir, $scanPsr0Packages = false, $suffix = '')
    {
        parent::dump($config, $localRepo, $mainPackage, $installationManager, $targetDir, $scanPsr0Packages, $suffix);

        // we need to hack up the class loader to add a realpath
        $filesystem = new Filesystem();
        $vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));
        $targetDir = $vendorPath.'/'.$targetDir;
        $lines = file("$targetDir/ClassLoader.php");
        foreach($lines as $i => $line) {
            if ($line == "function includeFile(\$file)\n") {
                array_splice($lines, $i+2, 0, "    \$file = realpath(\$file);\n");
            }
        }
        file_put_contents("$targetDir/ClassLoader.php", implode("", $lines));
    }
}
