<?php

namespace Elgentos\Magento\Command\Customer;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanTaxvatCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('customer:clean-taxvat')
          ->setDescription('Clean up taxvat attribute by stripping country codes, spaces and dots.')
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
            $dialog = $this->getHelper('dialog');
            $resource = \Mage::getSingleton('core/resource');
            $db = $resource->getConnection('core_write');
            
            $countryList = \Mage::getResourceModel('directory/country_collection')
                    ->loadData()
                    ->toOptionArray(false);
            
            $countryCodes = array();
            foreach($countryList as $country) {
                $countryCodes[] = $country['value'];
            }
            $this->countryCodes = $countryCodes;
            
            $taxVatAttributeId = $db->fetchOne("SELECT attribute_id FROM {$resource->getTableName('eav/attribute')} 
                WHERE attribute_code = ?", array('taxvat'));
                
            $rows = $db->fetchAll("SELECT * FROM {$resource->getTableName('customer_entity_varchar')} 
                WHERE entity_type_id = ? AND attribute_id = ?
                AND `value` IS NOT NULL", array(1, $taxVatAttributeId));
            
            foreach($rows as $row) {
                // Clean taxvat
                $newTaxvat = $this->clean($row['value']);
                // Set new taxvat
                $db->update($resource->getTableName('customer_entity_varchar'), array(
                    'value' => $newTaxvat,
                ), 'value_id = ' . $row['value_id']);
                $output->writeln('<info>Taxvat for customer ' . $row['entity_id'] .' updated from ' . $row['value'] . ' to ' . $newTaxvat . '</info>');
            }
        }
    }

    private function clean($taxvat) {
        // For some reason, a lot of people put their email address in the taxvat field (browser autofill?)
        if (\Zend_Validate::is($taxvat, 'EmailAddress')) {
            return null;
        }
        // Strip dots, commas, spaces and braces
        $taxvat = preg_replace('/[.,()\s]/', '', $taxvat);
        // If the string consists of only letters, return empty
        if (!preg_match('/[^A-Za-z]+/', $taxvat)) {
            return null;
        }
        if(in_array(strtoupper(substr($taxvat,0,2)), $this->countryCodes)) {
            // First two characters matches with a country code, strip it
            $taxvat = substr($taxvat,2);
        }
        // Taxvats cannot be shorter than 5 characters
        if(strlen($taxvat) < 5) {
            return null;
        }
        return strtoupper($taxvat);
    }
}