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
        $cmd = 'grep -irl ';
        $paths = array(
            \Mage::getBaseDir() . 'app/code/community/*',
            \Mage::getBaseDir() . 'app/code/local/*'
        );
        $query = array(
            '"collection->addFieldToFilter(\'"',
            '"collection->addFieldToFilter(\'\`"'
        );
        $this->detectMagento($output);
        if ($this->initMagento()) {
            foreach ($paths as $_searchPath) {
                $_text = '';
                $_count = 0;
                $_search = '';
                foreach ($query as $_searchQuery) {
                    exec('grep -irl '. $_searchQuery. ' '. $_searchPath, $_output, $_status);
                    if (1 === $_status) {
                        $_text = $_searchQuery. ' not found. You\'re not affected by APPSEC-1063, good job!'. "\n";
                        continue;
                    }
                    if (0 === $_status) {
                        $_count=$_count + count($_output);
                        $_total=$_total + $_count;
                        $_text = 'These files affected by APPSEC-1063:'. "\n";
                        foreach ($_output as $_line) {
                            $_search = $_search.'['. "\033[1;32m".  $_appsec. "\033[0m". '] '. $_searchQuery. ' found in '. "\033[1;31m". str_replace($_securityNotice['magentopath'],' ', $_line). "\033[0m\n";
                            $_text = $_text . $_search;
                        }
                    } else {
                        $_text = 'Command '. $cmd . ' failed with status: ' . $_status. "\n";
                    }
                }
                $output->writeln($_text);
            }
        }
    }
}