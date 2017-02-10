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
          ->setDescription('Clean up taxvat attribute by stripping country codes, spaces and dots. [elgentos]')
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
            
            $taxVatAttributeId = $db->fetchOne("SELECT attribute_id FROM eav_attribute 
                WHERE attribute_code = ?", array('taxvat'));
                
            $rows = $db->fetchAll("SELECT * FROM customer_entity_varchar 
                WHERE entity_type_id = ? AND attribute_id = ?
                AND `value` IS NOT NULL", array(1, $taxVatAttributeId));
            
            foreach($rows as $row) {
                // Clean taxvat
                $newTaxvat = $this->clean($row['value']);
                if($newTaxvat != $row['value']) {
                    // Set new taxvat
                    $db->update('customer_entity_varchar', array(
                        'value' => $newTaxvat,
                    ), 'value_id = ' . $row['value_id']);
                    $output->writeln('<info>Taxvat for customer ' . $row['entity_id'] . ' updated from ' . $row['value'] . ' to ' . $newTaxvat . '</info>');
                }
            }
        }
    }

    private function clean($taxvat) {
        // For some reason, a lot of people put their email address in the taxvat field (browser autofill?)
        if (\Zend_Validate::is($taxvat, 'EmailAddress')) {
            return null;
        }

        // Strip dashes, dots, commas, spaces and braces
        $taxvat = preg_replace('/[-\.,()\s]/', '', $taxvat);
        $taxvat = trim($taxvat);

        // If the string consists of only letters, return empty
        if (!preg_match('/[^A-Za-z]+/', $taxvat)) {
            return null;
        }

        // If first two characters match with a country code, strip it
        if(in_array(strtoupper(substr($taxvat,0,2)), $this->countryCodes)) {
            $taxvat = substr($taxvat,2);
        }
        $taxvat = trim($taxvat);

        /* Support for Dutch VAT numbers */

        // Numbers have to end on (not start with) B01/B02/etc
        if(substr($taxvat,0,2) == 'B0') {
            $taxvat = substr($taxvat,3) . substr($taxvat,0,3);
        }
        $taxvat = trim($taxvat);

        // If B0* is encountered somewhere in the string, strip the remaining characters
        if($needle = stripos($taxvat,'B0')) {
            if(strlen(substr($taxvat,$needle))>3) {
                $taxvat = substr($taxvat,0,($needle+3));
            }
        }
        $taxvat = trim($taxvat);

        // Remove 'BTW' from start of string
        if(substr($taxvat,0,3) == 'BTW') {
            $taxvat = substr($taxvat,3);
        }
        $taxvat = trim($taxvat);
        /* End Dutch validation rules */

        // Taxvats cannot be shorter than 5 characters
        if(strlen($taxvat) < 5) {
            return null;
        }

        return strtoupper($taxvat);
    }
}