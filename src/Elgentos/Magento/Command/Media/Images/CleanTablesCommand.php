<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CleanTablesCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('media:images:cleantables')
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
        if ($this->initMagento()) {
            $eavAttribute = new \Mage_Eav_Model_Mysql4_Entity_Attribute();
            $thumbnailAttrId = $eavAttribute->getIdByCode('catalog_product', 'thumbnail');
            $smallImageAttrId = $eavAttribute->getIdByCode('catalog_product', 'small_image');
            $imageAttrId = $eavAttribute->getIdByCode('catalog_product', 'image');
            $prefix_table = \Mage::getConfig()->getTablePrefix();

            $dialog = $this->getHelperSet()->get('dialog');

            $resource = \Mage::getModel('core/resource');
            $db = $resource->getConnection('core_write');

            $cleanUpTableRowsMediaGallery = $dialog->askConfirmation($output,
                '<question>Clean catalog_product_entity_media_gallery(_value)?</question> <comment>[yes]</comment> ',
                true);

            $cleanUpTableRowsVarchar = $dialog->askConfirmation($output,
                '<question>Clean catalog_product_entity_varchar?</question> <comment>[yes]</comment> ', true);

            $dryRun = $dialog->askConfirmation($output,
                '<question>Dry run?</question> <comment>[no]</comment> ', false);

            if ($cleanUpTableRowsMediaGallery) {
                /* Clean up images from media gallery tables */
                $images = $db->fetchAll('SELECT value,value_id FROM '.$prefix_table.'catalog_product_entity_media_gallery');
                foreach ($images as $image) {
                    if (!file_exists(\Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . $image['value'])) {
                        $output->writeln($image['value'] . ' does not exist on disk; deleting row from database.');
                        if(!$dryRun) {
                            $db->query('DELETE FROM '.$prefix_table.'catalog_product_entity_media_gallery WHERE value_id = ?', $image['value_id']);
                            $db->query('DELETE FROM '.$prefix_table.'catalog_product_entity_media_gallery_value WHERE value_id = ?', $image['value_id']);
                        }
                    }
                }
            }

            if ($cleanUpTableRowsVarchar) {
                /* Clean up images from varchar table */
                $images = $db->fetchAll('SELECT value,value_id FROM '.$prefix_table.'catalog_product_entity_varchar WHERE attribute_id = ? OR attribute_id = ? OR attribute_id = ?', array($thumbnailAttrId, $smallImageAttrId, $imageAttrId));
                foreach ($images as $image) {
                    if (!file_exists(\Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . $image['value'])) {
                        $output->writeln($image['value'] . ' does not exist on disk; deleting row from database.');
                        if(!$dryRun) {
                            $db->query('DELETE FROM '.$prefix_table.'catalog_product_entity_varchar WHERE value_id = ?',  $image['value_id']);
                        }
                    }
                }
            }
        }
    }
}