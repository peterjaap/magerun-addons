<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RemoveDuplicatesCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('media:images:removeduplicates')
            ->setDescription('Remove duplicate image files from disk and database (needs fdupes lib!). [elgentos]');
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

            $directory = \Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product';

            // Find duplicates and strip out filenames and chunk them
            $outputText = shell_exec('find ' . $directory . ' -type d -exec fdupes -n {} \;'); // find duplicates
            $before = substr(shell_exec('find ' . $directory . ' -type f | wc -l'), 0, -1);
            $total = shell_exec('du -h ' . $directory);
            $total = explode("\n", $total);
            array_pop($total);
            $total = array_pop($total);
            $total = explode("\t", $total);
            $total = array_shift($total);
            $totalBefore = $total;
            $chunks = explode("\n\n", $outputText);

            /* Run through duplicates and replace database rows */
            foreach ($chunks as $chunk) {
                $files = explode("\n", $chunk);
                $original = array_shift($files);
                foreach ($files as $file) {
                    // update database where filename=file set filename=original
                    $original = '/' . implode('/', array_slice(explode('/', $original), -3));
                    $file = '/' . implode('/', array_slice(explode('/', $file), -3));
                    $oldFileOnServer = \Mage::getBaseDir('media') . '/catalog/product' . $file;
                    $newFileOnServer = \Mage::getBaseDir('media') . '/catalog/product' . $original;
                    if (file_exists($newFileOnServer) && file_exists($oldFileOnServer)) {
                        if(!$dryRun) {
                            $db->beginTransaction();
                            $resultVarchar = $db->update('catalog_product_entity_varchar', array('value' => $original), $db->quoteInto('value =?', $file));
                            $db->commit();
                            $db->beginTransaction();
                            $resultGallery = $db->update('catalog_product_entity_media_gallery', array('value' => $original), $db->quoteInto('value =?', $file));
                            $db->commit();
                        } else {
                            $resultVarchar = ':unknown_in_dry_run:';
                            $resultGallery = ':unknown_in_dry_run:';
                        }
                        $output->writeln('Replaced ' . $file . ' with ' . $original . ' (' . $resultVarchar . '/' . $resultGallery . ')');
                        if(!$dryRun) {
                            unlink($oldFileOnServer);
                            if (file_exists($oldFileOnServer)) {
                                die('File ' . $oldFileOnServer . ' not deleted');
                            }
                        }
                    } else {
                        if(!$dryRun) {
                            if (!file_exists($oldFileOnServer)) {
                                $output->writeln('File ' . $oldFileOnServer . ' does not exist.');
                            }
                            if (!file_exists($newFileOnServer)) {
                                $output->writeln('File ' . $newFileOnServer . ' does not exist.');
                            }
                        }
                    }
                }
            }

            // Calculate difference
            $after = substr(shell_exec('find ' . $directory . ' -type f | wc -l'), 0, -1);
            $total = shell_exec('du -h ' . $directory);
            $total = explode("\n", $total);
            array_pop($total);
            $total = array_pop($total);
            $total = explode("\t", $total);
            $total = array_shift($total);
            $totalAfter = $total;

            $output->writeln('In directory ' . $directory . ' the script has deleted ' . ($before - $after) . ' files - went from ' . $totalBefore . ' to ' . $totalAfter);
        }
    }
}
