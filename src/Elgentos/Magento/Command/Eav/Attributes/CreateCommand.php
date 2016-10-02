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

class CreateCommand extends AbstractMagentoCommand
{

    protected function configure()
    {
        $this
            ->setName('eav:attribute:create')
            ->setDescription('Create EAV attribute [elgentos]')
            ->addOption('type','t',InputOption::VALUE_OPTIONAL,'Type?', null)
            ->addOption('label','l',InputOption::VALUE_OPTIONAL,'Label?', null)
            ->addOption('attribute_code','ac',InputOption::VALUE_OPTIONAL,'Attributecode?', null)
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
            /* Make objects available throughout class */
            $this->_input = $input;
            $this->_output = $output;
            $this->_dialogHelper = $this->getHelperSet()->get('dialog');
            $this->_questionHelper = $this->getHelper('question');

            $attributeTypes = array(
                'text',
                'dropdown',
                'multiselect',
                'yes/no',
                'date',
                'textarea',
                'price'
            );

            $attributeType = null;
            if ($this->_input->getOption('type')) {
                $attributeType = $this->_input->getOption('type');
            }

            if (!$attributeType || !in_array($attributeType, $attributeTypes)) {
                $attributeType = $this->questionSelectFromOptionsArray('What kind of attribute do you want to create?',
                    $attributeTypes,
                    false
                );
            }

            $fields = $this->createAttribute($attributeType);

            $setup = new \Mage_Catalog_Model_Resource_Setup('core_setup');

            $attributeCode = $fields['attribute_code'];
            unset($fields['attribute_code']);
            try {
                $setup->addAttribute('catalog_product', $attributeCode, $fields);
                $this->_output->writeln('<info>Successfully added ' . $attributeCode . '</info>');
            } catch (Exception $e) {
                $this->_output->writeln('<error>Adding attribute ' . $attributeCode . ' failed; ' . $e->getMessage() . '</error>');
            }
        }
    }

    public function createAttribute($attributeType)
    {
        switch ($attributeType):
            case 'text':
                $attributeTypeSpecificFields = $this->getTextAttributeFields($attributeType);
                break;
            case 'dropdown':
                $attributeTypeSpecificFields = $this->getDropdownAttributeFields($attributeType);
                break;
            case 'multiselect':
                $attributeTypeSpecificFields = $this->getMultiselectAttributeFields($attributeType);
                break;
            case 'yes/no':
                $attributeTypeSpecificFields = $this->getYesnoAttributeFields($attributeType);
                break;
            case 'date':
                $attributeTypeSpecificFields = $this->getDateAttributeFields($attributeType);
                break;
            case 'textarea':
                $attributeTypeSpecificFields = $this->getTextareaAttributeFields($attributeType);
                break;
            case 'price':
                $attributeTypeSpecificFields = $this->getPriceAttributeFields($attributeType);
                break;
            default:
                return;
                break;
        endswitch;

        $fields = array_merge($this->getDefaultAttributeFields(), $attributeTypeSpecificFields);

        $fieldsToAskInputFor = array_intersect_key($fields, $this->getFieldsToAskInputFor());

        foreach ($fieldsToAskInputFor as $field => $defaultValue)
        {
            $question = ucwords(str_replace('_', ' ', $field));
            if (is_bool($defaultValue)) {
                $value = $this->_dialogHelper->askConfirmation($this->_output, '<question>' . $question . '?</question> <comment>[' . ($defaultValue ? 'yes' : 'no') . ']</comment> ', $defaultValue);
            } else {
                $value = $this->_dialogHelper->ask($this->_output, '<question>' . $question . '? </question> ' . ($defaultValue ? '<comment>[' . $defaultValue . ']</comment>' : null), $defaultValue);
            }

            $fields[$field] = $value;
        }

        if($attributeType == 'dropdown' || $attributeType == 'multiselect') {
            $addOptions = $this->_dialogHelper->askConfirmation($this->_output, '<question>Do you want to add options for ' . $fields['attribute_code'] . '?</question> <comment>[no]</comment> ', false);
            if ($addOptions) {
                $options = $this->_dialogHelper->ask($this->_output, '<question>Fill out the option values (comma separated)</question>');
                if (strlen($options)) {
                    $optionValues = explode(',', $options);
                    if ($optionValues && is_array($optionValues)) {
                        $optionValues = array_map('trim', $optionValues);
                        $fields['option'] = array('values' => $optionValues);
                    }
                }
            }
        }

        return $fields;
    }

    public function getTextAttributeFields()
    {
        return array(
            'type' => 'varchar',
            'input' => 'text',
        );
    }

    public function getDropdownAttributeFields()
    {
        return array(
            'type' => 'int',
            'input' => 'select',
        );
    }

    public function getMultiselectAttributeFields()
    {
        return array(
            'backend' => 'eav/entity_attribute_backend_array',
            'type' => 'varchar',
            'input' => 'multiselect',
        );
    }

    public function getYesnoAttributeFields()
    {
        return array(
            'type' => 'int',
            'input' => 'boolean',
            'source' => 'eav/entity_attribute_source_boolean'
        );
    }

    public function getDateAttributeFields()
    {
        return array(
            'backend' => 'eav/entity_attribute_backend_datetime',
            'type' => 'datetime',
            'frontend' => 'eav/entity_attribute_frontend_datetime',
            'input' => 'date',
        );
    }

    public function getTextareaAttributeFields()
    {
        return array(
            'type' => 'text',
            'input' => 'textarea',
        );
    }

    public function getPriceAttributeFields()
    {
        return array(
            'backend' => 'catalog/product_attribute_backend_price',
            'type' => 'decimal',
            'input' => 'price',
        );
    }

    private function getDefaultAttributeFields()
    {
        return array(
            'attribute_code' => $this->_input->getOption('attribute_code'),
            'attribute_model' => NULL,
            'backend' => NULL,
            'type' => NULL,
            'table' => NULL,
            'frontend' => NULL,
            'input' => NULL,
            'label' => $this->_input->getOption('label'),
            'frontend_class' => NULL,
            'source' => NULL,
            'required' => false,
            'user_defined' => true,
            'default' => '',
            'unique' => false,
            'note' => NULL,
            'input_renderer' => NULL,
            'global' => true,
            'visible' => true,
            'searchable' => true,
            'filterable' => true,
            'comparable' => false,
            'visible_on_front' => true,
            'is_html_allowed_on_front' => false,
            'is_used_for_price_rules' => false,
            'filterable_in_search' => false,
            'used_in_product_listing' => true,
            'used_for_sort_by' => false,
            'is_configurable' => false,
            'apply_to' => NULL,
            'visible_in_advanced_search' => false,
            'position' => '0',
            'wysiwyg_enabled' => false,
            'used_for_promo_rules' => false
        );
    }

    private function getFieldsToAskInputFor()
    {
        $fields = array(
            'attribute_code' => NULL,
            'label' => NULL,
            'required' => false,
            'unique' => false,
            'global' => true,
            'visible' => true,
            'searchable' => true,
            'filterable' => true,
            'comparable' => false,
            'visible_on_front' => true,
            'is_html_allowed_on_front' => false,
            'is_used_for_price_rules' => false,
            'filterable_in_search' => false,
            'used_in_product_listing' => true,
            'used_for_sort_by' => false,
            'is_configurable' => false,
            'visible_in_advanced_search' => false,
            'used_for_promo_rules' => false
        );

        /* If attribute code is given, do not ask for it (for compatibility with catalog:product:import) */
        if ($this->_input->getOption('attribute_code')) {
            unset($fields['attribute_code']);
        }

        return $fields;
    }

    private function questionSelectFromOptionsArray($question, $options, $multiselect = true)
    {
        $question = new ChoiceQuestion(
            '<question>' . $question . ($multiselect ? ' (comma separate multiple values)' : '') . '</question>',
            $options,
            0
        );
        if($this->isAssoc($options)) {
            $question->setAutocompleterValues(array_keys($options));
        } else {
            $question->setAutocompleterValues(array_values($options));
        }
        $question->setErrorMessage('Answer is invalid.');
        $question->setMultiselect($multiselect);
        $attributeCode = $this->_questionHelper->ask($this->_input, $this->_output, $question);

        return $attributeCode;
    }

    private function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
