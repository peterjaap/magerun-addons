<?php

namespace Elgentos\Magento\Command\Extension;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('extension:enable')
          ->setDescription('Enable an extension that was disabled through extension:disable')
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
            $moduleDir = $this->getApplication()->getMagentoRootFolder() . 'app/etc/modules';
            $moduleFiles = scandir($moduleDir);
            $moduleFilenames = $moduleNames = array();
            foreach($moduleFiles as $moduleFile) {
                if(strtolower(substr($moduleFile,-9)) == '.disabled') {
                    $xml = simplexml_load_file($moduleDir . '/' . $moduleFile);
                    $keys = array_keys((array)$xml->modules);
                    $moduleNames[] = $keys[0];
                    $moduleFilenames[] = $moduleFile; 
                }
            }
            
            $moduleIndex = $dialog->select(
                $output,
                'Select extension to enable',
                $moduleNames,
                0
            );
            
            exec('mv ' . $moduleDir . '/' . $moduleFilenames[$moduleIndex] . ' ' . $moduleDir . '/' . str_replace('.disabled','',$moduleFilenames[$moduleIndex]));
            
            $output->writeln('<info>Enabled ' . $moduleNames[$moduleIndex] . '</info>');
        }
    }
}