<?php

namespace Elgentos\Magento\Command\Dev;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TemplateVars extends AbstractMagentoCommand
{
    private static $varsWhitelist = array(
        'web/unsecure/base_url',
        'web/secure/base_url',
        'trans_email/ident_general/name',
        'trans_email/ident_sales/name',
        'trans_email/ident_sales/email',
        'trans_email/ident_custom1/name',
        'trans_email/ident_custom1/email',
        'trans_email/ident_custom2/name',
        'trans_email/ident_custom2/email',
        'general/store_information/name',
        'general/store_information/phone',
        'general/store_information/address'
    );

    private static $blocksWhitelist = array(
        'core/template',
        'catalog/product_new'
    );

    private $_regexBlock = '/{{block[^}]*?type=["\'](.*?)["\']/i';
    private $_regexVar = '/{{config[^}]*?path=["\'](.*?)["\']/i';
    private $_sqlSelect = "SELECT %s FROM %s WHERE %s LIKE '%%{{config %%' OR  %s LIKE '%%{{block %%'";

    private $_list = ['block' => [], 'variable' => []];

    protected function configure()
    {
        $this
            ->setName('dev:template-vars')
            ->setDescription('Find non-whitelisted template vars (for SUPEE-6788 compatibility)')
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
            $resource = \Mage::getSingleton('core/resource');
            $db = $resource->getConnection('core_read');

            // check table contents
            $cmsCheck = $this->insertIntoSqlString('content', $resource->getTableName('cms/block'));
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content');

            $cmsCheck = $this->insertIntoSqlString('content', $resource->getTableName('cms/page'));
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content');

            $emailCheck = $this->insertIntoSqlString('template_text', $resource->getTableName('core/email_template'));
            $result = $db->fetchAll($emailCheck);
            $this->check($result, 'template_text');

            // check template files
            $localeDir = \Mage::getBaseDir('locale');
            $this->walkDir(scandir($localeDir), $localeDir);

            $nonWhitelistedBlocks = array_diff($this->_list['block'], self::$blocksWhitelist);
            $nonWhitelistedVars = array_diff($this->_list['variable'], self::$varsWhitelist);

            // Todo; add custom whitelisted blocks/vars to the above whitelists

            if(count($nonWhitelistedBlocks) > 0) {
                $output->writeln('Found blocks that are not whitelisted by default; ');
                foreach ($nonWhitelistedBlocks as $blockName) {
                    $output->writeln($blockName);
                }
                $output->writeln('');
            }

            if(count($nonWhitelistedVars) > 0) {
                $output->writeln('Found template/block variables that are not whitelisted by default; ');
                foreach ($nonWhitelistedVars as $varName) {
                    $output->writeln($varName);
                }
            }

            if(count($nonWhitelistedBlocks) == 0 && count($nonWhitelistedVars) == 0) {
                $output->writeln('Yay! All blocks and variables are whitelisted.');
            }

        }
    }

    /**
     * Walk through the directory and validate any non csv file
     *
     * @param array  $dir
     * @param string $path
     */
    private function walkDir(array $dir, $path = '') {
        foreach ($dir as $subdir) {
            if (strpos($subdir, '.') !== 0) {
                if(is_dir($path . DS . $subdir)) {
                    $this->walkDir(scandir($path . DS . $subdir), $path . DS . $subdir);
                } elseif (is_file($path . DS . $subdir) && pathinfo($subdir, PATHINFO_EXTENSION) !== 'csv') {
                    $this->check([file_get_contents($path . DS . $subdir)], null);
                }
            }
        }
    }

    /**
     * Search for the variables
     *
     * @param        $result
     * @param string $field
     */
    private function check($result, $field = 'content') {
        if ($result) {
            foreach ($result as $res) {
                $target = ($field === null) ? $res: $res[$field];
                if (preg_match_all($this->_regexBlock, $target, $matches)) {
                    foreach ($matches[1] as $match) {
                        if (!in_array($match, $this->_list['block'])) {
                            $this->_list['block'][] = $match;
                        }
                    }
                }
                if (preg_match_all($this->_regexVar, $target, $matches)) {
                    foreach ($matches[1] as $match) {
                        if (!in_array($match, $this->_list['variable'])) {
                            $this->_list['variable'][] = $match;
                        }
                    }
                }
            }
        }
    }

    /**
     * Shorthand for sprintf the sql select
     *
     * @param $field
     * @param $table
     *
     * @return string
     */
    private function insertIntoSqlString( $field, $table){
        return sprintf($this->_sqlSelect, $field, $table, $field, $field);
    }
}