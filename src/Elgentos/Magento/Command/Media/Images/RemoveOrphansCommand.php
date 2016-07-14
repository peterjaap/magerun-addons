<?php

namespace Elgentos\Magento\Command\Media\Images;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveOrphansCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('media:images:removeorphans')
                ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run? (yes|no)')
                ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Only search first L files (useful for testing)')
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

        $filesToRemove = $this->_getMediaToRemove($mediaBaseDir, $input, $output);

        $this->_showStats($filesToRemove['stats'], $output);

        if ($dryRun) {
            return 0;
        }

        $this->_removeMediaFiles($filesToRemove['files'], $input, $output);

        return 0;
    }

    /**
     * Remove orphans from disk
     *
     * @param array $filesToRemove
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function _removeMediaFiles(&$filesToRemove, InputInterface $input, OutputInterface $output)
    {
        if (count($filesToRemove) < 1) {
            // Nothing to do
            return 0;
        }

        $quiet = $input->getOption('quiet');

        $totalSteps = $this->_getTotalSteps();
        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("<comment>Remove files from filesystem</comment> ({$currentStep}/{$totalSteps})");

        $progress = new ProgressBar($output, count($filesToRemove));

        $unlinkedCount = array_reduce($filesToRemove, function($unlinkedCount, $info) use ($progress, $quiet) {

            $unlinked = unlink($info);

            !$quiet && $progress->advance();

            return $unlinkedCount + $unlinked;
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
     * Get media files to remove
     *
     * @param string $mediaBaseDir
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    protected function _getMediaToRemove($mediaBaseDir, InputInterface $input, OutputInterface $output)
    {
        $quiet = $input->getOption('quiet');
        $limit = (int)$input->getOption('limit');

        $totalSteps = $this->_getTotalSteps();
        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("<comment>Looking up files</comment> ({$currentStep}/{$totalSteps})");

        $mediaFiles = $this->_getMediaFiles($mediaBaseDir);

        $limit && ($mediaFiles = array_slice($mediaFiles, 0, $limit));

        $mediaFilesCount = count($mediaFiles);
        $progressBar = new ProgressBar($output, $mediaFilesCount);
        $progressBar->setRedrawFrequency(50);

        $mediaFilesHashes = $this->_getMediaFileHashes($mediaFiles, function() use ($progressBar, $quiet) {
            !$quiet && $progressBar->advance();
        });
        !$quiet && $progressBar->finish();

        $currentStep = $this->_getCurrentStep();
        $this->_advanceNextStep();
        !$quiet && $output->writeln("\n<comment>Reading database data</comment> ({$currentStep}/{$totalSteps})");

        $values = $this->_getProductImageValues();
        $gallery = $this->_getProductImageGallery();

        $mediaFilesToRemove = [];
        $sizeBefore = 0;
        $sizeAfter = 0;

        array_walk($mediaFilesHashes, function($hashInfo) use ($mediaBaseDir, &$mediaFilesToRemove, &$sizeBefore, &$sizeAfter, &$values, &$gallery) {

            $sizeBefore += $hashInfo['size'];
            $file = str_replace($mediaBaseDir, '', $hashInfo['file']);

            if (isset($values[$file]) || isset($gallery[$file])) {
                // Exists in gallery or values
                $sizeAfter += $hashInfo['size'];
                return;
            }

            // Add to list of files to remove
            $mediaFilesToRemove[] = $hashInfo['file'];
        });

        $mediaFilesToRemoveCount = $mediaFilesCount - count($mediaFilesToRemove);

        return [
                'stats' => [
                        'count' => [
                                'before' => $mediaFilesCount,
                                'after' => $mediaFilesToRemoveCount,
                                'percent' => 1 - $mediaFilesToRemoveCount / $mediaFilesCount
                        ],
                        'size' => [
                                'before' => $sizeBefore,
                                'after' => $sizeAfter,
                                'percent' => 1 - $sizeAfter / $sizeBefore
                        ]
                ],
                'files' => $mediaFilesToRemove
        ];
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

        $sizeBefore = $stats['size']['before'];
        $sizeAfter = $stats['size']['after'];
        $sizePercentage = $stats['size']['percent'];

        if ($countBefore <= $countAfter) {
            $output->writeln('<info>No files to remove</info> <comment>YOUR MEDIA IS OPTIMIZED AS HELL!</comment>');
            return;

        }

        $measureBefore = new \Zend_Measure_Binary($sizeBefore);
        $measureAfter = new \Zend_Measure_Binary($sizeAfter);

        $formattedBefore = $measureBefore->convertTo(\Zend_Measure_Binary::MEGABYTE);
        $formattedAfter = $measureAfter->convertTo(\Zend_Measure_Binary::MEGABYTE);

        $pad1Length = max(strlen($countBefore), strlen($formattedBefore));
        $pad2Length = max(strlen($countAfter), strlen($formattedAfter));

        $output->writeln('<info>Statistics: (before -> after)</info>');
        $output->writeln(' <comment>files:</comment> ' .
                str_pad($countBefore, $pad1Length, ' ', STR_PAD_LEFT) . ' -> ' .
                str_pad($countAfter, $pad2Length, ' ', STR_PAD_LEFT) .
                ' (' . round($countPercentage * 100, 1) . '%)');

        $output->writeln(' <comment>size:</comment>  ' .
                str_pad($formattedBefore, $pad1Length, ' ', STR_PAD_LEFT) . ' -> ' .
                str_pad($formattedAfter, $pad2Length, ' ', STR_PAD_LEFT) .
                ' (' . round($sizePercentage * 100, 1) . '%)');

        $output->writeln("\n");
    }

}
