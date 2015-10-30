<?php

namespace Elgentos\Magento\Command\Dev;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PossibleSqlInjection extends AbstractMagentoCommand
{

    protected function configure()
    {
        $this
            ->setName('dev:possible-sql-injection')
            ->setDescription('APPSEC-1063, addressing possible SQL injection')
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $scanPaths = array(
                \Mage::getBaseDir('code'),
                \Mage::getBaseDir('design') . DS . 'adminhtml',
            );
            $modmanDir = \Mage::getBaseDir() . DS . '.modman';
            if (is_dir($modmanDir)) {
                $scanPaths[] = $modmanDir;
            }
            $_count = 0;
            /**
            * Trudge through the filesystem.
            */
            foreach ($scanPaths as $scanPath) {
                /**
                * For each file within this path...
                */
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scanPath));
                foreach ($files as $file => $object) {
                    // Skip any non-PHP files
                    if (strrpos($file, '.php') === false) {
                        continue;
                    }
                    $fileContents = file_get_contents($file);
                    /**
                    * Check for APPSEC-1063 - Thanks @timvroom and @rhoerr
                    */
                    if (preg_match_all('/addFieldToFilter[\n\r\s]*\([\n\r\s]*[\'"]?[\`\(]/i', $fileContents, $matches)) {
                        $_text = sprintf('['. "\033[1;31mAPPSEC-1063\033[0m". '] possible SQL vulnerability in %s'. "\n", $file);
                        foreach ($matches[0] as $m) {
                            $_text = $_text . sprintf('CODE: %s', $m);
                        }
                        $_count++;
                        $output->writeln($_text);
                    }
                }
            }
            if ($_count == 0) {
                $output->writeln("\033[1;32mYou're not affected by APPSEC-1063, good job!\033[0m");
            }
        }
    }
}