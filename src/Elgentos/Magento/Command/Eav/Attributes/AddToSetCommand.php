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

class AddToSetCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('eav:attributes:add-to-set')
            ->setDescription('Add attribute to attribute sets [elgentos]')
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
            $dialog = $this->getHelperSet()->get('dialog');
            $questionHelper = $this->getHelper('question');
            $productEntityTypeId = \Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();

            /* Fetch attribute list */
            $attributes = \Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
            $attributeList = [];
            foreach ($attributes as $attribute) {
                $attributeList[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
            }
            $question = new ChoiceQuestion(
                '<question>Which attribute do you want to add to (an) attribute set(s)??</question>',
                $attributeList,
                0
            );
            $question->setAutocompleterValues(array_keys($attributeList));
            $question->setErrorMessage('Answer is invalid.');
            $attributeCode = $questionHelper->ask($input, $output, $question);
            $attributeId = \Mage::getModel('eav/entity_attribute')->load($attributeCode, 'attribute_code')->getId();

            /* Fetch attribute group list */
            $attributeGroupNames = [];
            $attributeGroups = \Mage::getModel('eav/entity_attribute_group')->getCollection();
            foreach($attributeGroups as $attributeGroup) {
                $attributeGroupNames[] = $attributeGroup->getAttributeGroupName();
            }
            $attributeGroupNames = array_unique($attributeGroupNames);
            $question = new ChoiceQuestion(
                '<question>Which attribute group do you want to add ' . $attributeCode .' to?</question>',
                $attributeGroupNames,
                0
            );
            $question->setAutocompleterValues(array_values($attributeGroupNames));
            $question->setErrorMessage('Answer is invalid.');
            $attributeGroupName = $questionHelper->ask($input, $output, $question);

            /* Fetch attribute sets */
            $attributeSetNames = [];
            $attributeSets = \Mage::getModel('eav/entity_attribute_set')->getCollection()->addFieldToFilter('entity_type_id', $productEntityTypeId);
            foreach($attributeSets as $attributeSet) {
                $attributeSetNames[$attributeSet->getId()] = $attributeSet->getAttributeSetName();
            }
            ksort($attributeSetNames);
            $attributeSetNames[99999] = 'All';
            $question = new ChoiceQuestion(
                '<question>Which attribute set(s) do you want to add ' . $attributeCode .' to (multiple attribute sets should be divided by commas)?</question>',
                $attributeSetNames,
                0
            );
            $question->setAutocompleterValues(array_values($attributeSetNames));
            $question->setErrorMessage('Answer is invalid.');
            $question->setMultiselect(true);
            $selectedAttributeSetNames = $questionHelper->ask($input, $output, $question);
            if (in_array('All', $selectedAttributeSetNames)) {
                $selectedAttributeSetNames = array_slice($attributeSetNames, 0, -1);
            }
            foreach($selectedAttributeSetNames as $attributeSetName) {
                $attributeSetId = array_search($attributeSetName, $attributeSetNames);

                /* Fetch attribute group ID for this attribute set */
                $attributeGroupId = \Mage::getModel('eav/entity_attribute_group')
                    ->getCollection()
                    ->addFieldToFilter('attribute_set_id', $attributeSetId)
                    ->addFieldToFilter('attribute_group_name', $attributeGroupName)
                    ->getFirstItem()
                    ->getId();

                if (!$attributeGroupId) {
                    $output->writeln('<error>Attribute group ' . $attributeGroupName . ' does not exist in attribute set ' . $attributeSetName . '</error>');
                } else {
                    /* All info is collected, add it! */
                    try {
                        \Mage::getModel('eav/entity_attribute')
                            ->setEntityTypeId($productEntityTypeId)
                            ->setAttributeSetId($attributeSetId)
                            ->setAttributeGroupId($attributeGroupId)
                            ->setAttributeId($attributeId)
                            ->setSortOrder(100)
                            ->save();

                        $output->writeln('<info>Attribute ' . $attributeCode . ' has been successfully added to attribute set ' . $attributeSetName . ' in group ' . $attributeGroupName . '</info>');
                    } catch(\Exception $e) {
                        $output->writeln('<error>' . $e->getMessage() . '</error>');
                    }
                }
            }
        }
    }
}
