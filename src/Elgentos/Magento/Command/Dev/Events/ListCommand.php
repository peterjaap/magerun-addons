<?php

namespace Elgentos\Magento\Command\Dev\Events;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends AbstractMagentoCommand
{

    protected function configure()
    {
        $this
            ->setName('dev:events:list')
            ->setDescription('List all configured events that are listened for [elgentos]')
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
            $config = \Mage::getConfig();

            foreach ($config->getNode('frontend/events') as $events) {
                foreach($events as $event => $observers) {
                    foreach($observers->observers as $data) {
                        foreach($data as $module => $observer) {
                            $output->writeln('<info>' . $event . '</info> (' . $module . ') ' . $observer->class . '::' . $observer->method);
                            if(strtolower($event) != $event) {
                                $output->writeln('<error>' . $event . ' has an uppercased event name configured! This has changed to all lowercase in SUPEE-7405 / Magento 1.9.2.3</error>');
                            }
                        }
                    }
                }
            }
        }
    }
}