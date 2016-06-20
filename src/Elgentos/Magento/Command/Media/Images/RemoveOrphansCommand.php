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
            $globIterator = new \GlobIterator($dir . DS . '[A-z0-9]' . DS . '[A-z0-9]' . DS . '*');

            $prefix_table = \Mage::getConfig()->getTablePrefix();
            $total = $deleted = 0;

            foreach ($globIterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $filename = DS . implode(DS, array_slice(explode(DS, $fileInfo->getPathname()), -3));

                $count = (int)$db->query('SELECT COUNT(*) FROM ' . $prefix_table . 'catalog_product_entity_media_gallery WHERE BINARY value = ?',
                    $filename)->fetchColumn();
                if ($count === 0) {
                    if (!$dryRun) {
                        unlink($fileInfo->getPathname());
                        if (!file_exists($fileInfo->getPathname())) {
                            $output->writeln($fileInfo->getPathname() . ' has been deleted.');
                        } else {
                            $output->writeln($fileInfo->getPathname() . ' still exists; no write permissions?');
                            exit;
                        }
                    } else {
                        $output->writeln($fileInfo->getPathname() . ' would be deleted.');
                    }

                    $deleted++;
                }
                $total++;
            }
            $output->writeln('Deleted ' . $deleted . ' of total ' . $total);
        }
    }
}
