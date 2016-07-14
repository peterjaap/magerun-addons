<?php

namespace Elgentos\Magento\Command\Media\Images;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanTablesCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('media:images:cleantables')
                ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run? (yes|no)')
                ->setDescription('Clean media tables by deleting rows with references to non-existing images [elgentos]');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            return 1;
        }

        // Get options
        $dryRun = $input->getOption('dry-run');
        $interactive = !$input->getOption('no-interaction');

        if (!$dryRun && $interactive) {

            /** @var \Symfony\Component\Console\Helper\QuestionHelper $dialog */
            $dialog = $this->getHelperSet()
                    ->get('question');

            $dryRun = $dialog->ask(
                    $input,
                    $output,
                    new ConfirmationQuestion('<question>Dry run?</question> <comment>[no]</comment>', false)
            );
        }

        $this->_setTotalSteps($dryRun ? 2 : 4);

        $mediaBaseDir = $this->_getMediaBase();

        $filesToRemove = $this->_getRecordsToRemove($mediaBaseDir, $input, $output);
        $this->_showStats($filesToRemove['stats'], $output);

        if ($dryRun) {
            return 0;
        }

        $this->_deleteValuesFromCatalogDb($filesToRemove['values'], $input, $output);
        $this->_deleteGalleryFromCatalogDb($filesToRemove['gallery'], $input, $output);

        return 0;
    }

    /**
     * Update database records to match
     *
     * @param array $mediaValuesToDelete
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _deleteValuesFromCatalogDb(&$mediaValuesToDelete, InputInterface $input, OutputInterface $output)
    {
        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        $varcharTable = $resource->getTableName('catalog/product') . '_varchar';

        return $this->_deleteFromCatalogDb($mediaValuesToDelete, $varcharTable, $input, $output);
    }

    /**
     * Update database records to match
     *
     * @param array $mediaGalleryToDelete
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _deleteGalleryFromCatalogDb(&$mediaGalleryToDelete, InputInterface $input, OutputInterface $output)
    {
        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        $varcharTable = $resource->getTableName('catalog/product') . '_media_gallery';

        return $this->_deleteFromCatalogDb($mediaGalleryToDelete, $varcharTable, $input, $output);
    }

    /**
     * Update database records to match
     *
     * @param array $mediaValuesToDelete
     * @param string $table
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _deleteFromCatalogDb(&$mediaValuesToDelete, $table, InputInterface $input, OutputInterface $output)
    {
        if (count($mediaValuesToDelete) < 1) {
            // Nothing to do
            return 0;
        }

        $quiet = $input->getOption('quiet');

        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        /** @var \Magento_Db_Adapter_Pdo_Mysql $connection */
        $connection = $resource->getConnection('core_write');

        $progress = new ProgressBar($output, count($mediaValuesToDelete));

        $totalSteps = $this->_getTotalSteps();
        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("<comment>Delete values from {$table}</comment> ({$currentStep}/{$totalSteps})");

        $deleteCount = array_reduce($mediaValuesToDelete, function($deleteCount, $valueIds) use ($connection, $table, $progress, $quiet) {

            // Delete for one file in a single transaction
            $connection->beginTransaction();
            try {
                if (count($valueIds)) {
                    $deleteCount += $connection->delete($table, $connection->quoteInto('value_id in(?)', $valueIds));
                }

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollback();
            }

            !$quiet && $progress->advance();

            return $deleteCount;
        }, 0);

        if (!$quiet) {
            $progress->finish();

            if ($deleteCount < 1) {
                $output->writeln("\n <info>no records deleted</info>\n");
            } else {
                $output->writeln("\n <info>deleted {$deleteCount} records...</info>\n");
            }
        }

        return $deleteCount;
    }

    /**
     * Show stats
     *
     * @param array $stats
     * @param OutputInterface $output
     * @return void
     */
    protected function _showStats(&$stats, OutputInterface $output)
    {
        $countBefore = $stats['count']['before'];
        $countAfter = $stats['count']['after'];
        $countPercentage = $stats['count']['percent'];

        if ($countBefore <= $countAfter) {
            $output->writeln('<info>No files to remove</info> <comment>YOUR MEDIA IS OPTIMIZED AS HELL!</comment>');
            return;

        }

        $output->writeln('<info>Statistics: (before -> after)</info>');
        $output->writeln(' <comment>records:</comment> ' .
                $countBefore . ' -> ' . $countAfter .
                ' (' . round($countPercentage * 100, 1) . '%)');

        $output->writeln("\n");
    }


    /**
     * Get media files to remove
     *
     * @param string $mediaBaseDir
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    protected function _getRecordsToRemove($mediaBaseDir, InputInterface $input, OutputInterface $output)
    {
        $quiet = $input->getOption('quiet');

        $totalSteps = $this->_getTotalSteps();
        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("<comment>Looking up files</comment> ({$currentStep}/{$totalSteps})");

        $mediaFiles = array_map(
                function(){return true;},
                array_flip(
                        array_map(function($file) use ($mediaBaseDir) {
                        return str_replace($mediaBaseDir, '', $file);
                    },
                    $this->_getMediaFiles($mediaBaseDir)))
        );

        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("<comment>Reading database data</comment> ({$currentStep}/{$totalSteps})");

        $values = $this->_getProductImageValues();
        $gallery = $this->_getProductImageGallery();

        $valuesToRemove = array_diff_key($values, $mediaFiles);
        $galleryToRemove = array_diff_key($gallery, $mediaFiles);

        $beforeCount = array_reduce(array_merge($values, $gallery), function($totalCount, $valueIds) {
            return $totalCount + count($valueIds);
        }, 0);
        $afterCount = $beforeCount - array_reduce(array_merge($valuesToRemove, $galleryToRemove), function($totalCount, $valueIds) {
            return $totalCount + count($valueIds);
        }, 0);

        return [
                'stats' => [
                        'count' => [
                                'before' => $beforeCount,
                                'after' => $afterCount,
                                'percent' => 1 - $afterCount / $beforeCount
                        ]
                ],
                'values' => $valuesToRemove,
                'gallery' => $galleryToRemove
        ];
    }

}
