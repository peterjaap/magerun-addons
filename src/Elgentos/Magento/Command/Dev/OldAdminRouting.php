<?php

namespace Elgentos\Magento\Command\Dev;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class OldAdminRouting extends AbstractMagentoCommand
{

    protected function configure()
    {
        $this
            ->setName('dev:old-admin-routing')
            ->setDescription('Find extensions that use the old-style admin routing (not compatible with SUPEE-6788)')
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
            $routers = \Mage::getConfig()->getNode('admin/routers');
            foreach($routers[0] as $router) {
                $name = $router->args->module;
                if($name != 'Mage_Adminhtml') {
                    $offendingExtensions[] = $router->args->module;
                }
            }

            if(count($offendingExtensions)) {
                $output->writeln("\033[0;31mThese extensions use old-style admin routing which is not compatible with SUPEE-6788 / Magento 1.9.2.2+;\033[0;31m");
                foreach($offendingExtensions as $extension) {
                    $output->writeln($extension);
                }
            } else {
                $output->writeln("\033[1;32mYay! All extension are compatible, good job!\033[0m");
            }
        }
    }
}