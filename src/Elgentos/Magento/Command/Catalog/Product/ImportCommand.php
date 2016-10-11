<?php

namespace Elgentos\Magento\Command\Catalog\Product;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\ParseException;
use League\Csv\Reader;

class ImportCommand extends AbstractMagentoCommand
{
    protected $_matched = [];
    protected $_headers = false;
    protected $_unmappedHeaders = false;
    protected $_configFile = false;
    protected $_continueOnError = true;
    protected $_websites = [];
    protected $_debugging = false;

    protected function configure()
    {
        $this
            ->setName('catalog:product:import')
            ->setDescription('Interactive product import helper [elgentos]')
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

            /* Set pre-requisites */
            if (!\Mage::helper('core')->isModuleEnabled('AvS_FastSimpleImport')) {
                $this->_output->writeln('<error>Required module AvS_FastSimpleImport isn\'t installed.</error>');
            }

            $files = $this->questionSelectFromOptionsArray(
                'Choose file(s) to be imported',
                $this->globFilesToBeImported()
            );

            foreach ($files as $file)
            {
                $csv = Reader::createFromPath($file)->setDelimiter(',');
                $this->_headers = $csv->fetchOne();

                $this->_configFile = $this->getConfigFile($file);

                $this->_matched = $this->getMatchedHeaders();

                $this->_unmappedHeaders = array_diff($this->_headers, array_keys($this->_matched));

                $askToSaveConfigFile = false;

                $this->_websites = $this->getWebsites();

                if (!$this->_matched) {
                    $this->matchHeaders($this->_headers);
                    $askToSaveConfigFile = true;
                } elseif (count($this->_unmappedHeaders)) {
                    if($this->_dialogHelper->askConfirmation($this->_output, '<question>You have ' . count($this->_unmappedHeaders) . ' unmapped headers, you want to map them now?</question> <comment>[yes]</comment> ', true)) {
                        $this->matchHeaders($this->_unmappedHeaders);
                        $askToSaveConfigFile = true;
                    }
                }

                if($askToSaveConfigFile && $this->_dialogHelper->askConfirmation($this->_output, '<question>Save mapping to configuration file?</question> <comment>[yes]</comment> ', true)) {
                    $dumper = new Dumper();
                    $yaml = $dumper->dump($this->_matched);
                    file_put_contents($this->_configFile, $yaml);
                }

                $csv->setOffset(1);

                if ($this->_debugging) {
                    $csv->setLimit(2);
                }

                $productDataArrays = [];

                $csv->each(function ($row) use(&$productDataArrays) {
                    $productData = $this->transformData($this->getDefaultProductData(), $row);

                    /* Allow adding/replacing of more arbirtrary data */
                    $object = new \Varien_Object(['product_data' => $productData]);
                    \Mage::dispatchEvent('catalog_product_import_data_set_additional_before', ['object' => $object]);
                    $productData = $object->getProductData();

                    if (empty($productData['ean'])) return true;

                    try {
                        $askToContinue = false;
                        if ($this->_debugging) {
                            print_r($productData);
                            $askToContinue = true;
                        }
                        if (!$askToContinue || $this->_dialogHelper->askConfirmation($this->_output, '<question>Do you want to import this product?</question> <comment>[yes]</comment>', true)) {
                            $productDataArrays[] = $productData;
                            $this->_output->writeln('<info>Queued product for import; ' . $productData['name'] . ' (' . $productData['sku'] . ')</info>');
                        }
                        return true;
                    } catch(Exception $e) {
                        $this->_output->writeln('<error>Could not save product; ' . $e->getMessage() . ' - skipping</error>');
                        return ($this->getContinueOnError() ? true : false);
                    }
                });

                $this->importProductData($productDataArrays);
            }
        }
    }

    private function matchHeaders($headers)
    {
        $attributes = \Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        $attributeList = [];

        /* Add non-eav atrributes */
        $attributeList['entity_id'] = 'Entity ID';

        /* Add eav attributes */
        foreach ($attributes as $attribute) {
            $attributeList[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
        }

        $attributeList['__skip'] = 'Skip Attribute';
        $attributeList['__create_new_attribute'] = 'Create New Attribute';

        array_walk($headers, [$this, 'matchHeader'], $attributeList);
    }

    private function matchHeader($header, $i, $attributeList)
    {
        $attributeCode = $this->questionSelectFromOptionsArray('Which attribute to you want to match the ' . $header .' column to?', $attributeList, false);

        switch ($attributeCode):
            case '__create_new_attribute':
                $attributeCode = $this->createNewAttribute($header);
                if ($attributeCode) {
                    $this->_matched[$header] = $attributeCode;
                }
            break;
            case '__skip':
                $this->_matched[$header] = '__skipped';
                $this->_output->writeln('Header ' . $header . ' is skipped');
                return;
            break;
            default:
                $this->_matched[$header] = $attributeCode;
            break;
        endswitch;
    }

    private function createNewAttribute($header)
    {
        $command = $this->getApplication()->find('eav:attribute:create');
        $attributeCode = \Mage::getModel('catalog/product')->formatUrlKey($header);
        $attributeCode = $this->_dialogHelper->ask($this->_output, '<question>Attribute Code? </question> ' . ($attributeCode ? '<comment>[' . $attributeCode. ']</comment>' : null), $attributeCode);
        $arguments = new ArrayInput(
            [
                'command' => 'eav:attribute:create',
                '--attribute_code' => $attributeCode,
                '--label' => $header
            ]
        );

        $command->run($arguments, $this->_output);

        return $attributeCode;
    }

    private function transformData($productData, $row)
    {
        $row = array_combine($this->_headers, $row);

        foreach($this->_matched as $originalHeader => $magentoAttribute)
        {
            $object = new \Varien_Object([
                'product_data' => $productData,
                'row' => $row,
                'original_header' => $originalHeader,
                'magento_attribute' => $magentoAttribute,
                'value' => $row[$originalHeader]
            ]);

            \Mage::dispatchEvent('catalog_product_import_data_set_attributedata_before', ['object' => $object]);

            $productData[$object->getMagentoAttribute()] = $object->getValue();
        }

        return $productData;
    }

    private function globFilesToBeImported()
    {
        $importFilesDir = $this->getImportFilesDir();
        return glob($importFilesDir . '/*.csv');
    }

    private function getImportFilesDir()
    {
        return \Mage::getBaseDir('var') . '/import';
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
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function getConfigFile($file)
    {
        $mappingConfigFileParts = pathinfo($file);
        unset($mappingConfigFileParts['basename']);
        unset($mappingConfigFileParts['extension']);

        $mappingConfigFile = implode(DS, $mappingConfigFileParts) . '.yml';

        return $mappingConfigFile;
    }

    private function getWebsites()
    {
        $websiteArray = [];
        foreach(\Mage::app()->getWebsites() as $website) {
            $websiteArray[] = $website->getCode();
        }
        $websites = $this->questionSelectFromOptionsArray('To which websites do you want to add these products?', $websiteArray, true);
        return $websites;
    }

    private function getMatchedHeaders()
    {
        if (file_exists($this->_configFile)) {
            if($this->_dialogHelper->askConfirmation($this->_output, '<question>Use mapping found in configuration file?</question> <comment>[yes]</comment> ', true)) {
                $yaml = new Parser();
                return $yaml->parse(file_get_contents($this->_configFile));
            }
        }
    }

    private function getContinueOnError()
    {
        return $this->_continueOnError;
    }

    private function getDefaultProductData()
    {
        $productData = [
            'sku' => 'RANDOM-' . rand(0,100000000),
            '_type' => 'simple',
            '_attribute_set' => 'Default',
            '_product_websites' => $this->_websites,
            'name' => '',
            'price' => 0,
            'description' => '',
            'short_description' => '',
            'weight' => 0,
            'status' => \Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
            'visibility' => \Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'tax_class_id' => 0,
            'qty' => 0
        ];

        return $productData;
    }

    private function importProductData($productDataArrays)
    {
        if (count($productDataArrays)) {
            /** @var $import AvS_FastSimpleImport_Model_Import */
            $import = \Mage::getModel('fastsimpleimport/import');
            try {
                $import
                    ->setPartialIndexing(true)
                    ->setBehavior(\Mage_ImportExport_Model_Import::BEHAVIOR_APPEND)
                    ->setUseNestedArrays(true)
                    ->processProductImport($productDataArrays);

                $this->_output->writeln('Successfully imported ' . count($productDataArrays) . ' products.');
            } catch (Exception $e) {
                $this->_output->writeln($import->getErrorMessages());
            }
        } else {
            $this->_output->writeln('<error>Nothing to import.</error>');
        }
    }
}
