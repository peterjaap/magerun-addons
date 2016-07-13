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
    protected function _getMediaFiles($mediaBaseDir)
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
     * Get file hashes
     *
     * @param array $files
     * @param null|\Closure $callback
     * @return array
     */
    protected function _getMediaFileHashes(array &$files, $callback = null)
    {
        return array_map(function($file) use ($callback) {

            $size = filesize($file);
            $md5sum = md5_file($file);

            $hash = $md5sum . ':' . $size;

            $data = [
                    'file' => $file,
                    'hash' => $hash,
                    'md5sum' => $md5sum,
                    'size' => $size
            ];

            $callback && call_user_func($callback, $data);

            return $data;
        }, $files);
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
                ->where('a.attribute_code like ?', '%image%')
                ->where('a.attribute_code not like ?', '%label%');

        $values = [];
        $result = $connection->query($select);
        while ($row = $result->fetch()) {
            if ('no_selection' == $row['value']) {
                continue;
            }

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
