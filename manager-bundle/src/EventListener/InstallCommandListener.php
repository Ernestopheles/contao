<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\ManagerBundle\EventListener;

use Contao\CoreBundle\Command\InstallCommand;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Installs or overrides files for the Contao Managed Edition on contao:install command.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class InstallCommandListener implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function onConsoleTerminate(ConsoleTerminateEvent $event)
    {
        $command = $event->getCommand();

        if (!$command instanceof InstallCommand) {
            return;
        }

        $this->addInitializePhp();
        $this->installWebDir($event->getInput(), $event->getOutput());
    }

    /**
     * Adds the initialize.php file.
     */
    private function addInitializePhp()
    {
        $fs = new Filesystem();
        $rootDir = dirname($this->container->getParameter('kernel.root_dir'));

        $fs->dumpFile(
            $rootDir.'/system/initialize.php',
            <<<'EOF'
<?php

use Contao\ManagerBundle\ContaoManager\Plugin as ManagerBundlePlugin;
use Contao\CoreBundle\Response\InitializeControllerResponse;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\HttpFoundation\Request;

if (!defined('TL_SCRIPT')) {
    die('Your script is not compatible with Contao 4.');
}

/** @var Composer\Autoload\ClassLoader */
$loader = require __DIR__.'/../vendor/autoload.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);
ManagerBundlePlugin::autoloadModules(__DIR__.'/modules');

$request = Request::create('/_contao/initialize', 'GET', [], $_COOKIE, [], $_SERVER);
$request->attributes->set('_scope', ('BE' === TL_MODE ? 'backend' : 'frontend'));

$kernel = new ContaoKernel('prod', false);
$kernel->setRootDir(dirname(__DIR__).'/app');
$response = $kernel->handle($request);

// Send the response if not generated by the InitializeController
if (!($response instanceof InitializeControllerResponse)) {
    $response->send();
    $kernel->terminate($request, $response);
    exit;
}

EOF
        );
    }

    /**
     * Executes the contao:install-web-dir command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function installWebDir(InputInterface $input, OutputInterface $output)
    {
        $input->setArgument('command', 'contao:install-web-dir');

        $phpFinder = new PhpExecutableFinder();

        if (false === ($phpPath = $phpFinder->find())) {
            throw new \RuntimeException('The php executable could not be found.');
        }

        $process = new Process(
            sprintf(
                '%s vendor/bin/contao-console%s %s%s --env=prod',
                $phpPath,
                $output->isDecorated() ? ' --ansi' : '',
                $this->getVerbosityFlag($output),
                'contao:install-web-dir'
            )
        );

        $process->run(
            function ($type, $buffer) use ($output) {
                $output->write($buffer, false);
            }
        );

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('An error occurred while executing the "contao:install-web-dir" command.');
        }
    }

    /**
     * Returns the verbosity flag depending on the console IO verbosity.
     *
     * @param OutputInterface $output
     *
     * @return string
     */
    private function getVerbosityFlag(OutputInterface $output)
    {
        switch (true) {
            case $output->isDebug():
                return ' -vvv';

            case $output->isVeryVerbose():
                return ' -vv';

            case $output->isVerbose():
                return ' -v';

            default:
                return '';
        }
    }
}
