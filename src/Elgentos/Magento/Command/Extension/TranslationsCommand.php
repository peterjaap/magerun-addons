<?php

namespace Elgentos\Magento\Command\Extension;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationsCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
          ->setName('extension:translations')
          ->setDescription('Find untranslated strings in extension [elgentos]')
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
            
            // Ask for which language we are checking
            $defaultLanguage = 'nl_NL';
            $language = $dialog->ask($output, '<question>Which language do you want to check translations for?</question> <comment>[' . $defaultLanguage .']</comment> ', $defaultLanguage);
            
            // Get a list of installed modules
            $modules = array_keys((array)\Mage::getConfig()->getNode('modules')->children());
            $moduleIndex = $dialog->select(
                $output,
                'Select extension to check for translations',
                $modules,
                0
            );
            
            // Split package & module name
            $fullModuleName = $modules[$moduleIndex];
            list($package, $module) = explode('_', $fullModuleName);
            
            // Look for the extension the 3 code pools, working from the core to local
            $codePools = array('core','community','local');
            foreach ($codePools as $testCodePool) {
                $moduleDir = $this->getApplication()->getMagentoRootFolder() . DS . 'app' . DS . 'code' . DS . $testCodePool . DS . $package . DS . $module . DS;
                $configXmlFile = $moduleDir . 'etc' . DS . 'config.xml';
                if (file_exists($configXmlFile)) {
                    $configXml = simplexml_load_string(file_get_contents($configXmlFile));
                    $codePool = $testCodePool;
                    break;
                }
            }

            // Give error when not found
            if (!isset($codePool)) {
                $output->writeln('<error>Extension ' . $fullModuleName . ' not found.</error>');
                exit;
            }
            
            // Feedback where it was found
            $output->writeln('<info>Extension ' . $fullModuleName . ' found in codepool ' . $codePool . '</info>');
            $output->writeln('<info>All found translation strings in the extension ' . $fullModuleName . ':</info>');
            
            // Look for translatable strings in the code
            $finds = shell_exec("find " . $moduleDir . " -type f -print0 | xargs -0 grep '__'");
            $finds = explode("\n", $finds);
            
            // Look for translatable strings in the design files
            // First, we have to guess which (if any) directory holds the phtml files. The assumption is that the model, block or helper name is the same as the template dir name
            $types = array('models','blocks','helpers');
            foreach ($types as $type) {
                if (isset($configXml->global->$type)) {
                    $tag = (array)$configXml->global->$type;
                    $designDirName = array_shift(array_keys($tag));
                    if ($designDirName) {
                        break;
                    }
                }
            }
            
            // It also assumes the phtml files are placed in base/default
            $designDir = $this->getApplication()->getMagentoRootFolder() . DS . 'app' . DS . 'design' . DS . 'frontend' . DS . 'base' . DS . 'default' . DS . 'template' . DS . $designDirName;
            if (is_dir($designDir)) {
                $designFinds = shell_exec("find " . $designDir . " -type f -print0 | xargs -0 grep '__'");
                $designFinds = explode("\n", $designFinds);
                
                $finds = array_merge($finds, $designFinds);
            }
            
            foreach ($finds as $find) {
                @list($filename, $foundtext) = explode(":", $find, 2);
                if ($filename && $foundtext) {
                    $filename = str_replace($designDir, '', $filename);
                    $filenameLengths[] = strlen($filename);
                }
            }
            
            // If specific translation files have been set in config, find those as well
            $translateFiles = array();
            if (file_exists($configXmlFile)) {
                if (is_object($configXml) and isset($configXml -> adminhtml) and isset($configXml -> adminhtml -> translate -> modules -> {$fullModuleName} -> files)) {
                    $translateFiles[] = (string)$configXml -> adminhtml -> translate -> modules -> {$fullModuleName}
                    -> files -> {'default'};
                }
                if (is_object($configXml) and isset($configXml -> frontend) and isset($configXml -> frontend -> translate -> modules -> {$fullModuleName} -> files)) {
                    $translateFiles[] = (string)$configXml -> frontend -> translate -> modules -> {$fullModuleName}
                    -> files -> {'default'};
                }
            }
            // Read out the translations and place them in an array
            foreach ($translateFiles as $transFile) {
                if (($handle = fopen($this->getApplication()->getMagentoRootFolder() . DS . 'app' . DS . 'locale' . DS . $language . DS . $transFile, "r")) !== false) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                        if (count($data) == 2) {
                            $translations[$data[0]] = $data[1];
                        }
                    }
                }
            }
            
            // Make an overview of which files contain which translations, if a translation is present and if so, what the translation is
            $maxLength = max($filenameLengths);
            $notfounds = array();
            foreach ($finds as $find) {
                @list($filename, $foundtext) = explode(":", $find, 2);
                if ($filename && $foundtext) {
                    $filename = str_replace($dir, '', $filename);
                    $foundtext = trim($foundtext);
                    $beginTrans = stripos($foundtext, '__(\'');
        
                    if ($beginTrans) {
                        $output->writeln('Filename: ' . str_pad($filename . ": ", ($maxLength + 10), ' ', STR_PAD_RIGHT));
                        $foundtext = stripslashes($foundtext);
                        $foundtext = substr($foundtext, $beginTrans + 4);
                        $endTrans = stripos($foundtext, '\'');
                        $foundtext = substr($foundtext, 0, $endTrans);
                        $output->writeln($foundtext);
                        $translation = $translations[$foundtext];
                        if ($translation == $foundtext || !$translation) {
                            $translation = "NOT FOUND";
                            $notfounds[] = $foundtext;
                        }
                        $output->writeln(str_pad(" ", $maxLength + 10, ' ', STR_PAD_RIGHT) . "Translation " . $language . ": " . $translation);
                        $output->writeln(str_pad("=", $maxLength + 50, '=', STR_PAD_RIGHT));
                    }
                }
            }

            // Generate a structure to create the locale files with
            $output->writeln('');
            if (count($translateFiles)>0) {
                $output->writeln('Copy/paste this into ' . implode(' or ', $translateFiles) . ' and add the translations;');
            } else {
                $output->writeln('Copy/paste this into app/locale/' . $language . '/' . $fullModuleName . '.csv and add the translations;');
            }
            $notfounds = array_unique($notfounds);
            sort($notfounds);
            foreach ($notfounds as $nf) {
                $output->writeln('"' . $nf . '","' . $nf . '"');
            }
        }
    }
}
