<?php

namespace Elgentos\Magento\Command\Media;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SyncCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('media:sync')
          ->setDescription('Sync media files from live server [elgentos]')
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
           $config = \Mage::app()->getConfig();
           $dialog = $this->getHelperSet()->get('dialog');
           
           // SSH or FTP?
           $mode = strtolower($dialog->ask($output, '<question>Mode (SSH or FTP) </question> <comment>[ssh]</comment>: ', 'ssh'));
           $values = array();
           $fields = array('host'=>false,'username'=>false,'path'=>true);
           // Also ask password for FTP
           if($mode == 'ftp') {
               $fields['password'] = false;
           }
           
           foreach($fields as $field=>$optional) {
               // Fetch config value from app/etc/local.xml (if available)
               $node = $config->getNode('global/production/' . $mode . '/' . $field);
               $value = (string)$node[0];
               if(!empty($value)) {
                    $value = $dialog->ask($output, '<question>' . strtoupper($mode) . ' ' . ucwords($field) . ' </question> <comment>[' . $value .']</comment>: ', $value);
               } else {
                   $value = $dialog->ask($output, '<question>' . strtoupper($mode) . ' ' . ucwords($field) . ' </question>: ');
               }
               if(!$optional && empty($value)) {
                   $output->writeln('<error>Field ' . $field . ' can not be empty!</error>');
                   exit;
               }
               $values[$field] = $value;
           }
           $values['path'] = trim($values['path'], '/');
           
           if($mode == 'ssh') {
               // Syncing over SSH using rsync
               $package = exec('which rsync');
               if(empty($package)) {
                   $output->writeln('Package rsync is not installed!');
                   exit;
               }
               $exec = 'rsync -avz --ignore-existing --exclude=*cache* ' . $values['username'] . '@' . $values['host'] . ':' . $values['path'] . '/media ' . $this->getApplication()->getMagentoRootFolder();
               $output->writeln($exec);
           } elseif($mode == 'ftp') {
               // Syncing over FTP using ncftpget
               $package = exec('which ncftpget');
               if(empty($package)) {
                   $output->writeln('Package ncftpget is not installed!');
                   exit;
               }
               // Unfortunately no exclude option with ncftpget so cache files are also synced
               $exec = 'ncftpget -R -v -u "' . $values['username'] . '" -p "' . $values['password'] . '" ' . $values['host'] . ' ' . $values['path'] . '/media/* ' . $this->getApplication()->getMagentoRootFolder() . '/media/';
           }
           
           $output->writeln('Syncing media files to local server...');
           $process = new Process($exec);
           $process->setTimeout(3600*10);
           $process->setIdleTimeout(3600);
           $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    echo 'ERROR > '.$buffer;
                } else {
                    echo $buffer;
                }
            });
            $output->writeln('<info>Finished</info>');
        }
    }
}