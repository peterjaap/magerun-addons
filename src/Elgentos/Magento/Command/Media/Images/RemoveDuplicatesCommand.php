<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveDuplicatesCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this->setName('media:images:removeduplicates')
                ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run? (yes|no)')
                ->addOption('db-only', null, InputOption::VALUE_NONE, 'Only update database? (will not remove any file)')
                ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Only search first L files (useful for testing)')
                ->setDescription('Remove duplicate image files from disk and database. [elgentos]');
    }

    /**
     * Remove duplicate images
     *
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
        $dbOnly = !!$input->getOption('db-only');
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

        $mediaBaseDir = \Mage::getSingleton('catalog/product_media_config')
                ->getBaseMediaPath();

        // Just lookup all files which could be reduced
        $mediaFileInfo = $this->_getMediaFiles($mediaBaseDir, $input, $output);

        if ($dryRun) {
            // Will not do real action here
            return 0;
        }

        // Update db
        $this->_updateCatalogDbGallery($mediaBaseDir, $mediaFileInfo['files'], $input, $output);

        if ($dbOnly) {
            // Only update database
            return 0;
        }

        $this->_unlinkMediaFiles($mediaFileInfo['files'], $input, $output);
    }

    /**
     * Remove files from filesystem
     *
     * @param array $mediaFilesToUpdate
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _unlinkMediaFiles(&$mediaFilesToUpdate, InputInterface $input, OutputInterface $output)
    {
        if (count($mediaFilesToUpdate) < 1) {
            // Nothing to do
            return 0;
        }

        $quiet = $input->getOption('quiet');

        !$quiet && $output->writeln('<comment>Remove files from filesystem</comment>');

        $progress = new ProgressBar($output, count($mediaFilesToUpdate));

        $unlinkedCount = array_reduce($mediaFilesToUpdate, function($unlinkedCount, $info) use ($progress, $quiet) {

            $unlinked = array_map('unlink', $info['others']);

            !$quiet && $progress->advance();

            return $unlinkedCount + count(array_filter($unlinked));
        }, 0);

        if (!$quiet) {
            $progress->finish();

            if ($unlinkedCount < 1) {
                $output->writeln("\n <error>NO FILES DELETED! do you even have write permissions?</error>\n");
            } else {
                $output->writeln("\n <info>...and it's gone... removed {$unlinkedCount} files</info>\n");
            }
        }

        return $unlinkedCount;
    }

    /**
     * Update database records to match
     *
     * @param string $mediaBaseDir
     * @param array $mediaFilesToUpdate
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _updateCatalogDbGallery($mediaBaseDir, &$mediaFilesToUpdate, InputInterface $input, OutputInterface $output)
    {
        if (count($mediaFilesToUpdate) < 1) {
            // Nothing to do
            return 0;
        }

        $quiet = $input->getOption('quiet');

        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        /** @var \Magento_Db_Adapter_Pdo_Mysql $connection */
        $connection = $resource->getConnection('core_write');

        $varcharTable = $resource->getTableName('catalog/product') . '_varchar';
        $galleryTable = $resource->getTableName('catalog/product') . '_media_gallery';

        $progress = new ProgressBar($output, count($mediaFilesToUpdate));

        !$quiet && $output->writeln('<comment>Update database to use same image</comment>');

        /**
         * Read values upfront
         * This is a serious speed advantage
         * Mainly because there are no indexes on values
         */
        $select = $connection->select()
                ->from(['v' => $varcharTable], ['value_id', 'value'])
                ->join(['a' => $resource->getTableName('eav/attribute')], 'v.attribute_id = a.attribute_id', [])
                ->where('a.attribute_code like ?', '%image%');

        $values = [];
        $result = $connection->query($select);
        while ($row = $result->fetch()) {
            if (!isset($values[$row['value']])) {
                $values[$row['value']] = [];
            }
            $values[$row['value']][] = $row['value_id'];
        }

        $select = $connection->select()
                ->from(['v' => $galleryTable], ['value_id', 'value']);

        $gallery = [];
        $result = $connection->query($select);
        while ($row = $result->fetch()) {
            if (!isset($gallery[$row['value']])) {
                $gallery[$row['value']] = [];
            }
            $gallery[$row['value']][] = $row['value_id'];
        }

        $updateCount = array_reduce($mediaFilesToUpdate, function($updateCount, $info) use ($mediaBaseDir, $connection, $varcharTable, $galleryTable, &$values, &$gallery, $progress, $quiet) {

            $file = str_replace($mediaBaseDir, '', $info['file']);

            $valueIds = [];
            $galleryIds = [];
            array_map(
                    function($file) use ($mediaBaseDir, &$valueIds, &$galleryIds, &$values, &$gallery) {
                        $file = str_replace($mediaBaseDir, '', $file);

                        if (isset($values[$file])) {
                            $valueIds = array_merge($valueIds, $values[$file]);
                            unset($values[$file]);
                        }
                        if (isset($gallery[$file])) {
                            $galleryIds = array_merge($galleryIds, $gallery[$file]);
                            unset($gallery[$file]);
                        }
                    },
                    $info['others']
            );

            // Do update for one file in a single transaction
            $connection->beginTransaction();
            try {
                if (count($valueIds)) {
                    $updateCount += $connection->update($varcharTable, ['value' => $file], $connection->quoteInto('value_id in(?)', $valueIds));
                }
                if (count($galleryIds)) {
                    $updateCount += $connection->update($galleryTable, ['value' => $file], $connection->quoteInto('value_id in(?)', $galleryIds));
                }

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollback();
            }

            !$quiet && $progress->advance();

            return $updateCount;
        }, 0);

        if (!$quiet) {
            $progress->finish();

            if ($updateCount < 1) {
                $output->writeln("\n <info>no references found to these files</info>\n");
            } else {
                $output->writeln("\n <info>updated {$updateCount} records...</info>\n");
            }
        }

        return $updateCount;
    }

    /**
     * Fetch all media information on filesystem
     *
     * @param string $mediaBaseDir
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    protected function _getMediaFiles($mediaBaseDir, InputInterface $input, OutputInterface $output)
    {
        $quiet = $input->getOption('quiet');
        $limit = (int)$input->getOption('limit');

        !$quiet && $output->writeln('<comment>Building files and hashes</comment>');

        // Get all files without cache
        $mediaFiles = array_filter(
                glob($mediaBaseDir . DS . '*' . DS . '*' . DS . '**'),
                function($file) use ($mediaBaseDir) {
                    if (is_dir($file)) {
                        // Skip directories
                        return false;
                    }

                    if (strpos($file, 'cache') !== false) {
                        // Skip cache directory
                        return false;
                    }

                    return true;
                }
        );

        // Slice and dice for fast testing
        $limit && ($mediaFiles = array_slice($mediaFiles, 0, $limit));

        $mediaFilesCount = count($mediaFiles);
        $progressBar = new ProgressBar($output, $mediaFilesCount);
        $progressBar->setRedrawFrequency(50);

        $mediaFilesHashes = array_map(function($file) use ($quiet, $progressBar) {

            $size = filesize($file);
            $md5sum = md5_file($file);

            $hash = $md5sum . ':' . $size;

            !$quiet && $progressBar->advance();

            return [
                'hash' => $hash,
                'md5sum' => $md5sum,
                'size' => $size
            ];
        }, $mediaFiles);
        $progressBar->finish();

        !$quiet && $output->writeln("\n<comment>Creating duplicates index</comment>");

        $progressBar = new ProgressBar($output, $mediaFilesCount);
        $progressBar->setRedrawFrequency(50);

        $mediaFilesReduced = [];
        $mediaFilesSize = 0;
        $mediaFilesReducedSize = 0;

        array_walk($mediaFilesHashes, function($hashInfo, $index) use (&$mediaFilesReduced, &$mediaFilesSize, &$mediaFilesReducedSize, $quiet, $progressBar, &$mediaFiles) {

            $hash = $hashInfo['hash'];
            $file = $mediaFiles[$index];

            $mediaFilesSize += $hashInfo['size'];

            if (!isset($mediaFilesReduced[$hash])) {
                // Keep first file, remove all others
                $mediaFilesReducedSize += $hashInfo['size'];

                $mediaFilesReduced[$hash] = [
                    'size' => $hashInfo['size'],
                    'md5sum' => $hashInfo['md5sum'],
                    'file' => $file,
                    'others' => []
                ];
            } else {
                $mediaFilesReduced[$hash]['others'][] = $file;
            }

            !$quiet && $progressBar->advance();
        });
        $progressBar->finish();

        $mediaFilesReducedCount = count($mediaFilesReduced);

        $mediaFilesToReduce = array_filter($mediaFilesReduced, function($record) {return !!count($record['others']);});

        // Display some nice stats before proceeding
        if (!$quiet) {
            $output->writeln("\n");

            if ($mediaFilesCount <= $mediaFilesReducedCount) {
                $output->writeln('<info>No files to reduce</info> <comment>YOUR MEDIA IS OPTIMIZED AS HELL!</comment>');
            } else {
                $measureFileSize = new \Zend_Measure_Binary($mediaFilesSize);
                $measureFileReducedSize = new \Zend_Measure_Binary($mediaFilesReducedSize);

                $mediaFilesSizeFormatted = $measureFileSize->convertTo(\Zend_Measure_Binary::MEGABYTE);
                $mediaFilesReducedSizeFormatted = $measureFileReducedSize->convertTo(\Zend_Measure_Binary::MEGABYTE);

                $pad1Length = max(strlen($mediaFilesCount), strlen($mediaFilesSizeFormatted));
                $pad2Length = max(strlen($mediaFilesReducedCount), strlen($mediaFilesReducedSizeFormatted));

                $output->writeln('<info>Statistics: (before -> after)</info>');
                $output->writeln(' <comment>files:</comment> ' .
                        str_pad($mediaFilesCount, $pad1Length, ' ', STR_PAD_LEFT) . ' -> ' .
                        str_pad($mediaFilesReducedCount, $pad2Length, ' ', STR_PAD_LEFT) .
                        ' (' . round((1 - $mediaFilesReducedCount / $mediaFilesCount) * 100, 1) . '%)');

                $output->writeln(' <comment>size:</comment>  ' .
                        str_pad($mediaFilesSizeFormatted, $pad1Length, ' ', STR_PAD_LEFT) . ' -> ' .
                        str_pad($mediaFilesReducedSizeFormatted, $pad2Length, ' ', STR_PAD_LEFT) .
                        ' (' . round((1 - $mediaFilesReducedSize / $mediaFilesSize) * 100, 1) . '%)');
            }

            $output->writeln("\n");
        }

        return [
            'stats' => [
                'count' => [
                    'before' => $mediaFilesCount,
                    'after' => $mediaFilesReducedCount,
                    'percent' => 1 - $mediaFilesReducedCount / $mediaFilesCount
                ],
                'size' => [
                    'before' => $mediaFilesSize,
                    'after' => $mediaFilesReducedSize,
                    'percent' => 1 - $mediaFilesReducedSize / $mediaFilesSize
                ]
            ],
            'files' => $mediaFilesToReduce
        ];
    }

}
