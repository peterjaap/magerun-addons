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

class MergeCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('eav:attributes:merge')
            ->setDescription('Merge an attribute with another attribute [elgentos]')
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
            /** @var $db Zend_Db_Adapter_Mysqli */
            $db = \Mage::getModel('core/resource')->getConnection('core_write');

            $dialog = $this->getHelperSet()->get('dialog');
            $questionHelper = $this->getHelper('question');
            $productEntityTypeId = \Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();

            /* Fetch attribute list */
            $attributesCollection = \Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
            $attributeList = array_map(function ($item) { return $item->getAttributeCode(); }, array_filter($attributesCollection, function ($item) { return $item->getIsUserDefined(); }));

            $question = new ChoiceQuestion(
                '<question>Choose your source attribute (the attribute you want to **merge & remove**).</question>',
                $attributeList,
                0
            );

            $question->setAutocompleterValues($attributeList);
            $question->setErrorMessage('Answer is invalid.');

            $sourceAttributeCode = $questionHelper->ask($input, $output, $question);
            /** @var Mage_Eav_Model_Attribute $sourceAttribute */
            $sourceAttribute = \Mage::getModel('eav/entity_attribute')->loadByCode($productEntityTypeId, $sourceAttributeCode);

            $question = new ChoiceQuestion(
                '<question>Choose your goal attribute (the attribute you want to **keep**).</question>',
                $attributeList,
                0
            );

            $question->setAutocompleterValues($attributeList);
            $question->setErrorMessage('Answer is invalid.');

            $goalAttributeCode = $questionHelper->ask($input, $output, $question);
            /** @var Mage_Eav_Model_Attribute $sourceAttribute */
            $goalAttribute = \Mage::getModel('eav/entity_attribute')->loadByCode($productEntityTypeId, $goalAttributeCode);

            if (!$goalAttribute->getId()) {
                $output->writeln('No goal attribute found!');
                return;
            }

            if (!$sourceAttribute->getId()) {
                $output->writeln('No source attribute found!');
                return;
            }

            if ($goalAttribute->getId() == $sourceAttribute->getId()) {
                $output->writeln('Goal attribute cannot be the same as the source attribute');
                return;
            }

            if ($goalAttribute->getFrontendInput() !== $sourceAttribute->getFrontendInput()) {
                $output->writeln('Goal attribute must have the same frontend input as the source attribute.');
                return;
            }

            if ($goalAttribute->getBackendType() !== $sourceAttribute->getBackendType()) {
                $output->writeln('Goal attribute must have the same backend type as the source attribute.');
                return;
            }

            $moveAttributeValues = false;
            if ($dialog->askConfirmation($output, '<question>Do you want to move the attribute values from ' . $sourceAttributeCode . ' to ' . $goalAttributeCode . ' (otherwise they will be deleted)?</question> <comment>[y]</comment> ',true)) {
                $moveAttributeValues = true;
            }

            if (!$dialog->askConfirmation($output, '<question>Are you sure you want to merge ' . $sourceAttributeCode . ' into ' . $goalAttributeCode . ' and remove ' . $sourceAttributeCode . '?</question> <comment>[y]</comment> ',true)) {
                return;
            }

            if ($moveAttributeValues)
            {
                // Move attribute values from source to goal
                try {
                    $db->update('eav_attribute_option', ['attribute_id' => $goalAttribute->getId()], $db->quoteInto('attribute_id = ?', $sourceAttribute->getId()));
                } catch (Exception $e) {
                    $output->writeln($e->getMessage());
                    return;
                }
            } else {
                // Delete attribute values
                try {
                    $db->delete('eav_attribute_option', $db->quoteInto('attribute_id = ?', $sourceAttribute->getId()));
                } catch (Exception $e) {
                    $output->writeln($e->getMessage());
                    return;
                }
            }

            try {
                // Update tables that contain references
                $db->update('catalog_product_entity_' . $goalAttribute->getBackendType(), ['attribute_id' => $goalAttribute->getId()], $db->quoteInto('attribute_id = ? AND ', $sourceAttribute->getId()) . $db->quoteInto('entity_type_id = ?', $productEntityTypeId));
            } catch (Exception $e) {
                $output->writeln($e->getMessage());
                return;
            }

            // Delete attribute
            try {
                $db->delete('eav_attribute', $db->quoteInto('attribute_id = ?', $sourceAttribute->getId()));
            } catch (Exception $e) {
                $output->writeln($e->getMessage());
                return;
            }

            // Success!
            $output->writeln('<info>Attribute ' . $sourceAttributeCode . ' has been merged into ' . $goalAttributeCode . ' and has been deleted.');
            $output->writeln('Be sure you have moved ' . $goalAttributeCode . ' into the right attribute sets. You can use eav:attributes:add-to-set for that.</info>');
            $output->writeln('You might have attribute options with the same label. Please use eav:attributes:undupe-options to unduplicate these.');
        }
    }
}
