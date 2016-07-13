<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractCommand extends AbstractMagentoCommand
{

    /**
     * Get media base
     *
     * @return string
     */
    protected function _getMediaBase()
    {
        return $this->_getModel('catalog/product_media_config', '\Mage_Catalog_Model_Product_Media_Config')
                ->getBaseMediaPath();
    }


    /**
     * Get media files on disc
     *
     * @param string $mediaBaseDir
     * @return array
     */
    protected function _getMediaFilesOnDisc($mediaBaseDir)
    {
        // Get all files without cache
        return array_filter(
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

        !$quiet && $output->writeln('<comment>Building files and hash table</comment>');

        // Get all files without cache
        $mediaFiles = $this->_getMediaFilesOnDisc($mediaBaseDir);

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

    /**
     * Get product image values from catalog_product_entity_varchar table
     *
     * @return array value => [value_id...]
     * @throws \Zend_Db_Statement_Exception
     */
    protected function _getProductImageValues()
    {
        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        /** @var \Magento_Db_Adapter_Pdo_Mysql $connection */
        $connection = $resource->getConnection('core_write');

        $varcharTable = $resource->getTableName('catalog/product') . '_varchar';

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

        return $values;
    }

    /**
     * Get product image gallery from catalog_product_entity_media_gallery table
     *
     * @return array value => [value_id...]
     * @throws \Zend_Db_Statement_Exception
     */
    protected function _getProductImageGallery()
    {

        /** @var \Mage_Core_Model_Resource $resource */
        $resource = $this->_getModel('core/resource', '\Mage_Core_Model_Resource');

        /** @var \Magento_Db_Adapter_Pdo_Mysql $connection */
        $connection = $resource->getConnection('core_write');

        $galleryTable = $resource->getTableName('catalog/product') . '_media_gallery';

        $select = $connection->select()
                ->from(['v' => $galleryTable], ['value_id', 'value']);

        $values = [];
        $result = $connection->query($select);
        while ($row = $result->fetch()) {
            if (!isset($values[$row['value']])) {
                $values[$row['value']] = [];
            }
            $values[$row['value']][] = $row['value_id'];
        }

        return $values;
    }

}
