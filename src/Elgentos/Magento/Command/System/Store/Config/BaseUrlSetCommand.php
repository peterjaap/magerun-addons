<?php

namespace Elgentos\Magento\Command\System\Store\Config;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class BaseUrlSetCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('sys:store:config:base-url:set')
          ->setDescription('Set base-urls for installed storeviews [elgentos]')
	  ->addOption('base_url','b',InputOption::VALUE_REQUIRED,'Fill out default base URL?', null)
	  ->addOption('skinjsmedia_defaults','s',InputOption::VALUE_OPTIONAL,'Reset skin/js/media base URLs to default?', null)    
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
           $config = $this->_getModel('core/config','Mage_Core_Model_Config');
	        $dialog = $this->getHelperSet()->get('dialog');

            if($input->getOption('skinjsmedia_defaults'))
            {
                foreach(array('secure','unsecure') as $secure) {
                    foreach (array('skin', 'media', 'js') as $type) {
                        $config->saveConfig(
                            'web/' . $secure . '/base_' . $type . '_url',
                            '{{' . $secure .'_base_url}}' . $type . '/',
                            'default',
                            0
                        );
                    }
                }
                $output->writeln('<info>Skin/media/js paths have been reset tot their defaults.</info>');
            }

	   $baseUrl = $input->getOption('base_url');
	   if($baseUrl) {
		$useSecureFrontend = 0;
           foreach(\Mage::getModel('core/store')->getCollection() as $store) {
               $unsecureBaseURL = $secureBaseURL = $baseUrl;
               if($store->getCode() != 'default') {
                   list($domain,$tld) = explode('.', $unsecureBaseURL, 2);
                   $domain .= '-' . $store->getCode();
                   $unsecureBaseURL = $domain . '.' . $tld;
                   $secureBaseURL = $unsecureBaseURL;
               }
               $config->saveConfig(
                   'web/unsecure/base_url',
                   $unsecureBaseURL,
                   ($store->getStoreId() == 0 ? 'default' : 'stores'),
                   $store->getStoreId()
               );
               $output->writeln('<info>Unsecure base URL for store ' . $store->getName() . ' [' . $store->getCode() . '] set to ' . $unsecureBaseURL . '</info>');

               $config->saveConfig(
                   'web/secure/base_url',
                   $secureBaseURL,
                   ($store->getStoreId() == 0 ? 'default' : 'stores'),
                   $store->getStoreId()
               );
               $output->writeln('<info>Secure base URL for store ' . $store->getName() . ' [' . $store->getCode() . '] set to ' . $secureBaseURL . '</info>');

               $config->saveConfig(
                   'web/secure/use_in_frontend',
                   ($useSecureFrontend ? '1' : '0'),
                   ($store->getStoreId() == 0 ? 'default' : 'stores'),
                   $store->getStoreId()
               );

               $config->saveConfig(
                   'web/secure/use_in_adminhtml',
                   ($useSecureFrontend ? '1' : '0'),
                   ($store->getStoreId() == 0 ? 'default' : 'stores'),
                   $store->getStoreId()
               );
           }
		return;
	   }

           $types = array('Main shop','Storeview');
           $typeIndex = $dialog->select(
                $output,
                'Do you want to set the base URL for the main shop or for a storeview?',
                $types,
                0
           );
           $type = $types[$typeIndex];
            
           if($type == 'Storeview') {
                $store = $this->getHelper('parameter')->askStore($input, $output);
           } else {
                $store = \Mage::getModel('core/store')->load(0);
           }

           $baseURL = $dialog->ask($output, '<question>Base URL: </question>');
           
           $parsed = parse_url($baseURL);
           $path = null;
           
           /* Check if given string can be parsed to a host name */
           if(isset($parsed['host'])) {
               $hostname = $parsed['host'];
               /* Take path into consideration for Magento installs in subdirs */
               if(isset($parsed['path'])) $path = $parsed['path'];
           } else {
               /* If hostname is not recognized, assume path for hostname */
               $parts = explode('/', $parsed['path']);
               if(count($parts)==1) {
                   $hostname = $parts[0];
               } elseif(count($parts)==2) {
                   $hostname = $parts[0];
                   $path = $parts[1];
               }
           }
           $path = trim($path,'/');
           
           /* Set & ask for confirmation of default HTTP and HTTPS hostnames */
           $defaultUnsecure = 'http://' . $hostname . '/';
           if($path) $defaultUnsecure .= $path . '/';
           $unsecureBaseURL = $dialog->ask($output, '<question>Unsecure base URL?</question> <comment>[' . $defaultUnsecure . ']</comment>', $defaultUnsecure);
           $defaultSecure = str_replace('http','https',$defaultUnsecure);
           $secureBaseURL = $dialog->ask($output, '<question>Secure base URL?</question> <comment>[' . $defaultSecure . ']</comment>', $defaultSecure);
           $useSecureFrontend = $dialog->askConfirmation($output, '<question>Use secure base URL in frontend?</question> <comment>[no]</comment> ', false);
           $useSecureBackend = $dialog->askConfirmation($output, '<question>Use secure base URL in backend?</question> <comment>[no]</comment> ', false);
           $resetSkinMediaJsPaths = $dialog->askConfirmation($output, '<question>Reset skin/media/js paths to secure/unsecure defaults?</question> <comment>[yes]</comment> ', true);
           
           $config->saveConfig(
                'web/unsecure/base_url',
                $unsecureBaseURL,
                ($store->getStoreId() == 0 ? 'default' : 'stores'),
                $store->getStoreId()
           );
           $output->writeln('<info>Unsecure base URL for store ' . $store->getName() . ' [' . $store->getCode() . '] set to ' .  $unsecureBaseURL . '</info>');
           
           $config->saveConfig(
                'web/secure/base_url',
                $secureBaseURL,
                ($store->getStoreId() == 0 ? 'default' : 'stores'),
                $store->getStoreId()
           );
           $output->writeln('<info>Secure base URL for store ' . $store->getName() . ' [' . $store->getCode() . '] set to ' .  $secureBaseURL . '</info>');
           
           $config->saveConfig(
                'web/secure/use_in_frontend',
                ($useSecureFrontend ? '1' : '0'),
                ($store->getStoreId() == 0 ? 'default' : 'stores'),
                $store->getStoreId()
           );
           
           $config->saveConfig(
                'web/secure/use_in_adminhtml',
                ($useSecureBackend ? '1' : '0'),
                ($store->getStoreId() == 0 ? 'default' : 'stores'),
                $store->getStoreId()
           );

            if($resetSkinMediaJsPaths)
            {
                foreach(array('secure','unsecure') as $secure) {
                    foreach (array('skin', 'media', 'js') as $type) {
                        $config->saveConfig(
                            'web/' . $secure . '/base_' . $type . '_url',
                            '{{' . $secure .'_base_url}}' . $type . '/',
                            ($store->getStoreId() == 0 ? 'default' : 'stores'),
                            $store->getStoreId()
                        );
                    }
                }
                $output->writeln('<info>Skin/media/js paths have been reset tot their defaults.</info>');
            }
        }
    }
}
