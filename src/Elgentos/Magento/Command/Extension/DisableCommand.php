<?php

namespace Elgentos\Magento\Command\Extension;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DisableCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('extension:disable')
          ->setDescription('Actually disable an extension')
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
            $dialog = $this->getHelper('dialog');
            $moduleDir = 'app/etc/modules';
            $moduleFiles = scandir($moduleDir);
            $moduleFilenames = $moduleNames = array();
            foreach($moduleFiles as $moduleFile) {
                if(strtolower(substr($moduleFile,-4)) == '.xml') {
                    $xml = simplexml_load_file($moduleDir . '/' . $moduleFile);
                    $keys = array_keys((array)$xml->modules);
                    $moduleNames[] = $keys[0];
                    $moduleFilenames[] = $moduleFile; 
                }
            }
            
            $moduleIndex = $dialog->select(
                $output,
                'Select extension to disable',
                $moduleNames,
                0
            );
            
            exec('mv ' . $moduleDir . '/' . $moduleFilenames[$moduleIndex] . ' ' . $moduleDir . '/' . $moduleFilenames[$moduleIndex] . '.disabled');
            
            $output->writeln('<info>Disabled ' . $moduleNames[$moduleIndex] . '</info>');
        }
    }
}