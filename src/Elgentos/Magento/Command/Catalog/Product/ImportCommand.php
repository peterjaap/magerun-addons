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
    protected $_attributeMappingFile = false;
    protected $_categoryMappingFile = false;
    protected $_continueOnError = true;
    protected $_websites = [];
    protected $_debugging = false;
    protected $_importBehavior = 'append';
    protected $_categoryDelimiter = '/';
    protected $_attributeSet = false;
    protected $_groupByAttributeCode = false;
    protected $_superAttributeCode = false;
    protected $_dropdownAttributes = [];

    protected function configure()
    {
        $this
            ->setName('catalog:product:import')
            ->setDescription('Interactive product import helper [elgentos]')
            ->addOption('import_behavior', 'i', InputOption::VALUE_OPTIONAL, 'Import Behavior (append/delete/replace)?', $this->_importBehavior)
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
            if ($this->_input->getOption('import_behavior')) {
                $importBehavior = $this->_input->getOption('import_behavior');
                if (in_array(
                        $importBehavior,
                        [
                            \Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
                            \Mage_ImportExport_Model_Import::BEHAVIOR_DELETE,
                            \Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE
                        ]
                    )
                ) {
                    $this->_importBehavior = $importBehavior;
                    $this->_output->writeln('<info>Starting import with behavior set to ' . $this->_importBehavior . '</info>');
                }
            }

            $files = $this->questionSelectFromOptionsArray(
                'Choose file(s) to be imported',
                $this->globFilesToBeImported()
            );

            foreach ($files as $file)
            {
                $csv = Reader::createFromPath($file)->setDelimiter(',');
                $this->_headers = $csv->fetchOne();

                $this->_attributeMappingFile = $this->getAttributeMappingFile($file);

                $this->_categoryMappingFile = $this->getCategoryMappingFile($file);

                $this->_matched = $this->getMatchedHeaders();

                $this->_unmappedHeaders = array_diff($this->_headers, array_keys($this->_matched));

                $askToSaveConfigFile = false;

                $this->_websites = $this->getWebsites();

                $this->_attributeSet = $this->getDefaultAttributeSet();

                $createConfigurables = $this->_dialogHelper->askConfirmation($this->_output, '<question>Create configurables based on simple products?</question> <comment>[yes]</comment>', true);

                if ($createConfigurables) {
                    $this->_groupByAttributeCode = $this->chooseAttributeFromList('group by attribute');
                    $this->_superAttributeCode = $this->chooseAttributeFromList('super attribute');
                    $this->_dropdownAttributes = $this->chooseAttributeFromList('dropdown attributes', true);
                }

                if (!$this->_matched) {
                    $this->matchHeaders($this->_headers);
                    $askToSaveConfigFile = true;
                } elseif (count($this->_unmappedHeaders)) {
                    if($this->_dialogHelper->askConfirmation($this->_output, '<question>You have ' . count($this->_unmappedHeaders) . ' unmapped headers, you want to map them now?</question> <comment>[yes]</comment> ', true)) {
                        $this->matchHeaders($this->_unmappedHeaders);
                        $askToSaveConfigFile = true;
                    }
                }

                if ($askToSaveConfigFile && $this->_dialogHelper->askConfirmation($this->_output, '<question>Save mapping to configuration file?</question> <comment>[yes]</comment> ', true)) {
                    $dumper = new Dumper();
                    $yaml = $dumper->dump($this->_matched);
                    file_put_contents($this->_attributeMappingFile, $yaml);
                }

                $csv->setOffset(1);

                if ($this->_debugging) {
                    $csv->setLimit(2);
                }

                $productDataArrays = [];

                $csv->each(function ($row) use(&$productDataArrays) {
                    $productData = $this->transformData($this->getDefaultProductData(), $row);

                    /* Allow adding/replacing of more arbitrary data */
                    $object = new \Varien_Object(['product_data' => $productData]);
                    \Mage::dispatchEvent('catalog_product_import_data_set_additional_before', ['object' => $object]);
                    $productData = $object->getProductData();

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

                /* Create configurables? */
                if ($createConfigurables) {
                    $productDataArrays = $this->addConfigurablesToDataArray($productDataArrays);
                }

                $this->importProductData($productDataArrays);
            }
        }
    }

    protected function matchHeaders($headers)
    {
        $attributeList = $this->getAttributeList();

        $attributeList['__categories'] = 'Categories';
        $attributeList['__stock_quantity'] = 'Stock Quantity';
        $attributeList['__local_image'] = 'Local Image';
        $attributeList['__http_image'] = 'Remote Image';
        $attributeList['__skip'] = 'Skip Attribute';
        $attributeList['__create_new_attribute'] = 'Create New Attribute';

        array_walk($headers, [$this, 'matchHeader'], $attributeList);
    }

    protected function matchHeader($header, $key, $attributeList)
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
            default:
                $this->_matched[$header] = $attributeCode;
        endswitch;
    }

    protected function createNewAttribute($header)
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

    protected function transformData($productData, $row)
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

            switch ($object->getMagentoAttribute()):
                case '__skipped':
                    break;
                case '__categories':
                    $productData['_category'] = $this->getMappedCategories($row[$originalHeader]);
                    break;
                case '__stock_quantity':
                    $productData['qty'] = $row[$originalHeader];
                    $productData['is_in_stock'] = ($productData['qty'] ? true : false);
                    break;
                case '__http_image':
                    if (isset($productData['_media_image']) && !empty($productData['_media_image'])) {
                        $productData['_media_image'] = $row[$originalHeader];
                        $productData['_media_target_filename'] = basename($productData['_media_image']);
                        $productData['image'] = $productData['_media_target_filename'];
                        $productData['small_image'] = $productData['_media_target_filename'];
                        $productData['thumbnail'] = $productData['_media_target_filename'];
                    }
                    break;
                case '__local_image':
                    break;
                default:
                    $productData[$object->getMagentoAttribute()] = $object->getValue();
            endswitch;
        }

        return $productData;
    }

    protected function globFilesToBeImported()
    {
        $importFilesDir = $this->getImportFilesDir();
        return glob($importFilesDir . '/*.csv');
    }

    protected function getImportFilesDir()
    {
        return \Mage::getBaseDir('var') . '/import';
    }

    protected function questionSelectFromOptionsArray($question, $options, $multiselect = true, $autocompleteOnValues = null)
    {
        $question = new ChoiceQuestion(
            '<question>' . $question . ($multiselect ? ' (comma separate multiple values)' : '') . '</question>',
            $options,
            0
        );
        if(!$this->isAssoc($options) || $autocompleteOnValues) {
            $question->setAutocompleterValues(array_values($options));
        } else {
            $question->setAutocompleterValues(array_keys($options));
        }
        $question->setErrorMessage('Answer is invalid.');
        $question->setMultiselect($multiselect);
        $attributeCode = $this->_questionHelper->ask($this->_input, $this->_output, $question);

        return $attributeCode;
    }

    protected function isAssoc(array $arr)
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function getAttributeMappingFile($file)
    {
        $mappingConfigFileParts = pathinfo($file);
        unset($mappingConfigFileParts['basename']);
        unset($mappingConfigFileParts['extension']);

        $mappingConfigFile = implode(DS, $mappingConfigFileParts) . '_attributeMapping.yml';

        return $mappingConfigFile;
    }

    protected function getCategoryMappingFile($file)
    {
        $mappingConfigFileParts = pathinfo($file);
        unset($mappingConfigFileParts['basename']);
        unset($mappingConfigFileParts['extension']);

        $mappingConfigFile = implode(DS, $mappingConfigFileParts) . '_categoryMapping.yml';

        return $mappingConfigFile;
    }

    protected function getDefaultAttributeSet()
    {
        $attributeSetNames = [];
        $attributeSets = \Mage::getModel('eav/entity_attribute_set')->getCollection()->addFieldToFilter('entity_type_id', \Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        foreach($attributeSets as $attributeSet) {
            $attributeSetNames[$attributeSet->getId()] = $attributeSet->getAttributeSetName();
        }
        ksort($attributeSetNames);

        return $this->questionSelectFromOptionsArray('Which attribute set to you want to use as the default?', $attributeSetNames, false, true);
    }

    protected function getWebsites()
    {
        $websiteArray = [];
        foreach(\Mage::app()->getWebsites() as $website) {
            $websiteArray[] = $website->getCode();
        }
        $allWebsites = $websiteArray;
        array_unshift($websiteArray, 'All');
        $websites = $this->questionSelectFromOptionsArray('To which websites do you want to add these products?', $websiteArray, true);

        if(count($websites) == 1 && $websites[0] == 'All') {
            $websites = $allWebsites;
        }

        return $websites;
    }

    protected function getMatchedHeaders()
    {
        if (file_exists($this->_attributeMappingFile)) {
            if($this->_dialogHelper->askConfirmation($this->_output, '<question>Use mapping found in configuration file?</question> <comment>[yes]</comment> ', true)) {
                $yaml = new Parser();
                return $yaml->parse(file_get_contents($this->_attributeMappingFile));
            }
        }
    }

    protected function getContinueOnError()
    {
        return $this->_continueOnError;
    }

    protected function getDefaultProductData()
    {
        $productData = [
            'sku' => 'RANDOM-' . rand(0,100000000),
            '_type' => 'simple',
            '_attribute_set' => $this->_attributeSet,
            '_product_websites' => $this->_websites,
            'name' => '',
            'price' => 0,
            'description' => '',
            'short_description' => '',
            'weight' => 0,
            'status' => \Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
            'visibility' => \Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'tax_class_id' => 0,
            'qty' => 0
        ];

        return $productData;
    }

    protected function getMappedCategories($categories)
    {
        if (is_string($categories)) {
            $categories = explode($this->_categoryDelimiter, $categories);
        }

        $categories = array_filter(array_map('trim', $categories));

        $categoryOptions = $this->getTreeCategories(2);

        $yaml = new Parser();
        if(file_exists($this->_categoryMappingFile)) {
            $mappedCategories = $yaml->parse(file_get_contents($this->_categoryMappingFile));
        } else {
            $mappedCategories = [];
        }

        $mappedCategoriesToReturn = [];

        foreach ($categories as $category) {
            if(!isset($mappedCategories[$category])) {
                $mappedValue = $this->questionSelectFromOptionsArray('To which category do you want to map ' . $category . '?', $categoryOptions, false, true);
                $mappedId = array_search($mappedValue, $categoryOptions);
                $mappedCategories[$category] = $mappedId;
            }
        }

        foreach ($categories as $category) {
            if(isset($mappedCategories[$category])) {
                $mappedCategoriesToReturn[$category] = $mappedCategories[$category];
            }
        }

        $dumper = new Dumper();
        $yaml = $dumper->dump($mappedCategories);
        file_put_contents($this->_categoryMappingFile, $yaml);

        return $mappedCategoriesToReturn;
    }

    /**
     * https://www.integer-net.com/importing-products-with-the-import-export-interface/
     * https://avstudnitz.github.io/AvS_FastSimpleImport/products.html
     * https://avstudnitz.github.io/AvS_FastSimpleImport/options.html
     * https://github.com/avstudnitz/AvS_FastSimpleImport/blob/master/src/app/code/community/AvS/FastSimpleImport/Model/Import.php
     */
    protected function importProductData($productDataArrays)
    {
        if (count($productDataArrays)) {
            $this->_output->writeln('<info>Starting import with behavior ' . $this->_importBehavior . '...</info>');
            /** @var $import AvS_FastSimpleImport_Model_Import */
            $import = \Mage::getModel('fastsimpleimport/import');
            try {
                $import
                    ->setDropdownAttributes($this->_dropdownAttributes)
                    ->setPartialIndexing(true)
                    ->setBehavior($this->_importBehavior)
                    ->setUseNestedArrays(true)
                    ->processProductImport($productDataArrays);

                $this->_output->writeln('<info>Successfully imported ' . count($productDataArrays) . ' products.</info>');
            } catch (Exception $e) {
                $this->_output->writeln('<error>' . implode(PHP_EOL, $import->getErrorMessages()) . '</error>');
            }
        } else {
            $this->_output->writeln('<error>Nothing to import.</error>');
        }
    }

    protected function getTreeCategories($parentId, $output = []){
        $allCats = \Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('is_active','1')
            ->addAttributeToFilter('include_in_menu','1')
            ->addAttributeToFilter('parent_id',array('eq' => $parentId));

        foreach ($allCats as $category)
        {
            $output[$category->getId()] .= ($category->getLevel()-2 ? str_repeat('--', $category->getLevel()-2) . ' ' : '') . $category->getName();
            $subcats = $category->getChildren();
            if ($subcats){
                $output = $this->getTreeCategories($category->getId(), $output);
            }
        }
        return $output;
    }

    protected function addConfigurablesToDataArray($productDataArrays)
    {
        /* Group products that belong together based on $groupByAttribute value */
        $groups = [];
        foreach ($productDataArrays as $productData) {
            $groups[$productData[$this->_groupByAttributeCode]][] = $productData;
        }

        /* Walk through the groups and fetch and set/unset relevant information */
        foreach ($groups as $groupKey => $products) {
            if(count($products) > 1) {
                // Get the highest price of the products in the array
                $price = max(array_map(function( $row ){ return $row['price']; }, $products));
                // Get the highest cost of the products in the array
                $cost = max(array_map(function( $row ){ return $row['cost']; }, $products));

                // Create array of SKUs for setting relation config <> simples
                $skus = array_unique(array_map(function ($row) { return $row['sku']; }, $products));
                $configSku = trim($this->longestCommonSubstring($skus), '-_ .');
                
                // Add configurable product to the data array
                $configurableProductData = [
                    'sku' => $configSku,
                    '_type' => 'configurable',
                    '_attribute_set' => $products[0]['_attribute_set'],
                    '_product_websites' => $products[0]['_product_websites'],
                    'price' => $price,
                    'cost' => $cost,
                    'name' => $products[0]['name'],
                    'description' => $products[0]['description'],
                    'short_description' => $products[0]['short_description'],
                    'status' => $products[0]['status'],
                    'visibility' => \Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    'tax_class_id' => $products[0]['tax_class_id'],
                    'is_in_stock' => 1,
                    '_super_products_sku' => $skus,
                    '_super_attribute_code' => $this->_superAttributeCode,
                    '_category' => array_values($products[0]['_category']),
                ];

                if (isset($products[0]['_media_image'])) {
                    $configurableProductData['_media_image'] = $products[0]['_media_image'];
                }
                if (isset($products[0]['_media_target_filename'])) {
                    $configurableProductData['_media_target_filename'] = $products[0]['_media_target_filename'];
                }
                if (isset($products[0]['image'])) {
                    $configurableProductData['image'] = $products[0]['_media_target_filename'];
                }
                if (isset($products[0]['small_image'])) {
                    $configurableProductData['small_image'] = $products[0]['small_image'];
                }
                if (isset($products[0]['thumbnail'])) {
                    $configurableProductData['thumbnail'] = $products[0]['thumbnail'];
                }

                /* Allow adding/replacing of arbitrary data */
                $object = new \Varien_Object(['product_data' => $configurableProductData, 'simples' => $products]);
                \Mage::dispatchEvent('catalog_product_import_data_set_configurable_before', ['object' => $object]);
                $productDataArrays[] = $object->getProductData();

                // Set simples of this configurable to not visibile individually and remove category relations
                foreach ($productDataArrays as &$product) {
                    if($product['_type'] == 'simple' && in_array($product['sku'], $skus)) {
                        $product['visibility'] = \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
                        $product['_category'] = null;

                        // Set URL key to name + super attribute value to avoid duplicate URL keys
                        if(
                            isset($product[$this->_superAttributeCode])
                            && !empty($product[$this->_superAttributeCode])
                            && !isset($product['url_key'])
                        ) {
                            $product['url_key'] = \Mage::getModel('catalog/product')->formatUrlKey($product['name'] . ' ' . $product[$this->_superAttributeCode]);
                        }
                    }
                }
            }
        }

        return $productDataArrays;
    }

    protected function chooseAttributeFromList($name = null, $multiselect = false)
    {
        $attributeList = $this->getAttributeList();
        $attributeCode = $this->questionSelectFromOptionsArray('Which attribute to you want to choose as ' . $name . '?', $attributeList, $multiselect);
        return $attributeCode;
    }

    protected function getAttributeList()
    {
        $attributes = \Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        $attributeList = [];

        /* Add non-eav atrributes */
        $attributeList['entity_id'] = 'Entity ID';

        /* Add eav attributes */
        foreach ($attributes as $attribute) {
            $attributeList[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
        }

        return $attributeList;
    }

    protected function longestCommonSubstring($words, $caseInsensitive = false)
    {
        $words = array_map('trim', $words);
        if ($caseInsensitive) {
            $words = array_map('strtolower', $words);
        }
        $sort_by_strlen = create_function('$a, $b', 'if (strlen($a) == strlen($b)) { return strcmp($a, $b); } return (strlen($a) < strlen($b)) ? -1 : 1;');
        usort($words, $sort_by_strlen);

        $longest_common_substring = array();
        $shortest_string = str_split(array_shift($words));

        while (sizeof($shortest_string)) {
            array_unshift($longest_common_substring, '');
            foreach ($shortest_string as $ci => $char) {
                foreach ($words as $wi => $word) {
                    if (!strstr($word, $longest_common_substring[0] . $char)) {
                        // No match
                        break 2;
                    }
                }
                $longest_common_substring[0] .= $char;
            }
            array_shift($shortest_string);
        }

        usort($longest_common_substring, $sort_by_strlen);
        return array_pop($longest_common_substring);
    }
}
