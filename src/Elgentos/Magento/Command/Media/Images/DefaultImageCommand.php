<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DefaultImageCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('media:images:defaultimage')
            ->setDescription('Set the default for a product where an image is available but isn\'t selected. [elgentos]');
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
            $eavAttribute = new \Mage_Eav_Model_Mysql4_Entity_Attribute();
            $thumbnailAttrId = $eavAttribute->getIdByCode('catalog_product', 'thumbnail');
            $smallImageAttrId = $eavAttribute->getIdByCode('catalog_product', 'small_image');
            $imageAttrId = $eavAttribute->getIdByCode('catalog_product', 'image');
            $mediaGalleryAttributeId = $eavAttribute->getIdByCode('catalog_product', 'media_gallery');
            $prefix_table = \Mage::getConfig()->getTablePrefix();

            $dialog = $this->getHelperSet()->get('dialog');

            $resource = \Mage::getModel('core/resource');
            $db = $resource->getConnection('core_write');

            $dryRun = $dialog->askConfirmation($output,
                '<question>Dry run?</question> <comment>[no]</comment> ', false);

            $products = $db->fetchAll('SELECT sku,entity_id FROM '.$prefix_table.'catalog_product_entity');
            foreach ($products as $product) {
                $chooseDefaultImage = false;
                $images = $db->fetchAll('select * from '.$prefix_table.'catalog_product_entity_varchar where `entity_id` = ? AND (`attribute_id` = ? OR `attribute_id` = ? OR `attribute_id` = ?)',
                    array($product['entity_id'], $imageAttrId, $smallImageAttrId, $thumbnailAttrId));
                if (count($images) == 0) {
                    $chooseDefaultImage = true;
                } else {
                    foreach ($images as $image) {
                        if ($image['value'] == 'no_selection') {
                            $chooseDefaultImage = true;
                            break;
                        }
                    }
                }
                if ($chooseDefaultImage) {
                    $defaultImage = $db->fetchOne('SELECT value FROM '.$prefix_table.'catalog_product_entity_media_gallery WHERE entity_id = ? AND attribute_id = ? LIMIT 1', array($product['entity_id'], $mediaGalleryAttributeId));
                    if ($defaultImage && !$dryRun) {
                        $db->query('INSERT INTO '.$prefix_table.'catalog_product_entity_varchar SET entity_type_id = ?, attribute_id = ?, store_id = ?, entity_id = ?, value = ? ON DUPLICATE KEY UPDATE value = ?',
                            array(4, $imageAttrId, 0, $product['entity_id'], $defaultImage, $defaultImage));
                        $db->query('INSERT INTO '.$prefix_table.'catalog_product_entity_varchar SET entity_type_id = ?, attribute_id = ?, store_id = ?, entity_id = ?, value = ? ON DUPLICATE KEY UPDATE value = ?',
                            array(4, $smallImageAttrId, 0, $product['entity_id'], $defaultImage, $defaultImage));
                        $db->query('INSERT INTO '.$prefix_table.'catalog_product_entity_varchar SET entity_type_id = ?, attribute_id = ?, store_id = ?, entity_id = ?, value = ? ON DUPLICATE KEY UPDATE value = ?',
                            array(4, $thumbnailAttrId, 0, $product['entity_id'], $defaultImage, $defaultImage));
			$output->writeln('New default image has been set for ' . $product['sku']);
                    } elseif($defaultImage) {
                        $output->writeln('New default image would be set for ' . $product['sku']);
                    }
		}
            }
        }
    }
}
