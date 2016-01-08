<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RemoveOrphansCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('media:images:removeorphans')
            ->setDescription('Remove orphaned files from disk (orphans are files which do exist but are not found the database). [elgentos]');
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
            $dialog = $this->getHelperSet()->get('dialog');

            $resource = \Mage::getModel('core/resource');
            $db = $resource->getConnection('core_write');

            $dryRun = $dialog->askConfirmation($output,
                '<question>Dry run?</question> <comment>[no]</comment> ', false);

            $dir = \Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product';
            $files = glob($dir . DS . '[A-z0-9]' . DS . '[A-z0-9]' . DS . '*');
            $prefix_table = \Mage::getConfig()->getTablePrefix();
            $total = $deleted = 0;
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $filename = DS . implode(DS, array_slice(explode(DS, $file), -3));

                $results = $db->fetchAll('SELECT * FROM '.$prefix_table.'catalog_product_entity_media_gallery WHERE value = ?', $filename);
                if (count($results) == 0) {
                    if(!$dryRun) {
                        unlink($file);
                        if (!file_exists($file)) {
                            $output->writeln($file . ' has been deleted.');
                        } else {
                            die($file . ' still exists; no write permissions?');
                        }
                    } else {
                        $output->writeln($file . ' would be deleted.');
                    }

                    $deleted++;
                }
                $total++;
            }
            $output->writeln('Deleted ' . $deleted . ' of total ' . $total);
        }
    }
}