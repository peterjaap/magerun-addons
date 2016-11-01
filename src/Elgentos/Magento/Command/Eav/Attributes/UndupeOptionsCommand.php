<?php

namespace Elgentos\Magento\Command\Eav\Attributes;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;

class UndupeOptionsCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('eav:attributes:undupe-options')
            ->setDescription('Find & unduplicate duplicate attribute options [elgentos]')
        ;
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
            $output->writeln('<error>Tread carefully; multi-language support is not built in, you may loose translated attribute option data!</error>');
            /** @var $db Zend_Db_Adapter_Mysqli */
            $db = \Mage::getModel('core/resource')->getConnection('core_write');

            $dialog = $this->getHelperSet()->get('dialog');
            $questionHelper = $this->getHelper('question');
            $productEntityTypeId = \Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();

            /* Fetch attribute list */
            $attributesCollection = \Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
            $attributeList = array_map(function ($item) { return $item->getAttributeCode(); }, array_filter($attributesCollection, function ($item) { return $item->getIsUserDefined() && stripos($item->getFrontendInput(), 'select') !== false; }));

            $question = new ChoiceQuestion(
                '<question>For which attribute do you want to search for duplicate options?</question>',
                $attributeList,
                0
            );

            $question->setAutocompleterValues($attributeList);
            $question->setErrorMessage('Answer is invalid.');

            $attributeCode = $questionHelper->ask($input, $output, $question);
            /** @var Mage_Eav_Model_Attribute $sourceAttribute */
            $attribute = \Mage::getModel('eav/entity_attribute')->loadByCode($productEntityTypeId, $attributeCode);

            if ($attribute->getSourceModel()) {
                $attributeOptions = $attribute->getSource()->getAllOptions();
            } else {
                $output->writeln('<error>Attribute ' . $attributeCode . ' does not use a source model; consider adding eav/entity_attribute_source_table as a source_model in eav_attribute.');
                return;
            }

            $attributeLabels = array_column($attributeOptions, 'label');

            $duplicateOptions = array_keys(array_filter(array_count_values($attributeLabels), function ($occurrences) { return $occurrences > 1; }));

            if (! count($duplicateOptions)) {
                $output->writeln('<info>There are no duplicate attribute values in the attribute ' . $attributeCode . '</info>');
                return;
            }

            foreach ($duplicateOptions as $label) {
                $optionIdResults = $db->fetchAll('SELECT DISTINCT eav_attribute_option.option_id FROM eav_attribute_option INNER JOIN eav_attribute_option_value ON eav_attribute_option.option_id = eav_attribute_option_value.option_id WHERE attribute_id = ? AND value = ? AND store_id = ?', array($attribute->getId(), $label, 0));
                $optionIds = array_column($optionIdResults, 'option_id');

                $targetOptionId = array_pop($optionIds);

                foreach ($optionIds as $optionId) {
                    try {
                        $db->update('catalog_product_entity_' . $attribute->getBackendType(), ['value' => $targetOptionId], $db->quoteInto('attribute_id = ? AND ', $attribute->getId()) . $db->quoteInto('value = ?', $optionId));
                    } catch (Exception $e) {
                        $output->writeln($e->getMessage());
                        return;
                    }
                    try {
                        $db->delete('eav_attribute_option', $db->quoteInto('option_id = ?', $optionId));
                    } catch (Exception $e) {
                        $output->writeln($e->getMessage());
                        return;
                    }

                    $output->writeln('<info>Removed duplicates of option ' . $label . ' for attribute ' . $attributeCode . '</info>');
                }
            }
        }
    }
}
