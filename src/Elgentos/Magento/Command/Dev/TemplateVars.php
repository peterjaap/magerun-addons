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
    /** @var InputInterface $input */
    protected $_input;

    private $varsWhitelist = array();
    private $blocksWhitelist = array();

    protected function configure()
    {
        $this->setName('dev:template-vars')
             ->setDescription('Find non-whitelisted template vars (for SUPEE-6788 compatibility) [elgentos]')
             ->addOption('addblocks', null, InputOption::VALUE_OPTIONAL, 'Set true to whitelist in db table permission_block the blocks found.', false)
             ->addOption('addvariables', null, InputOption::VALUE_OPTIONAL, 'Set true to whiteliset in db table permission_variable the variables found.', false);
    }

    /**
    * @param \Symfony\Component\Console\Input\InputInterface $input
    * @param \Symfony\Component\Console\Output\OutputInterface $output
    * @return int|void
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_input = $input;
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $resource = \Mage::getSingleton('core/resource');
            $db = $resource->getConnection('core_read');
            $dbwrite = $resource->getConnection('core_write');
            $sql = "SELECT %s FROM %s WHERE %s LIKE '%%{{config %%' OR  %s LIKE '%%{{block %%'";
            $cmsBlockTable                     = 'cms_block';
            $cmsPageTable                      = 'cms_page';
            $emailTemplate                     = 'core_email_template';
            $configTable                       = 'core_config_data';
            $blockPermissionTable = 'admin_permission_block';
            $variablePermissionTable = 'admin_permission_variable';
            $catalogProductEntityTextTable     = 'catalog_product_entity_text';
            $catalogProductEntityVarcharTable  = 'catalog_product_entity_varchar';
            $catalogCategoryEntityTextTable    = 'catalog_category_entity_text';
            $catalogCategoryEntityVarcharTable = 'catalog_category_entity_varchar';
            $catalogTables                     = array($catalogProductEntityTextTable, $catalogProductEntityVarcharTable, $catalogCategoryEntityTextTable, $catalogCategoryEntityVarcharTable);
            $sql = "SELECT %s FROM %s WHERE %s LIKE '%%{{config %%' OR  %s LIKE '%%{{block %%'";
            foreach ($catalogTables as $catalogTable) {
                $catalogCheck = sprintf($sql, 'value', $catalogTable, 'value', 'value');
                $result = $db->fetchAll($catalogCheck);
                $this->check($result, 'value', $list);
            }
            $list = array('block' => array(), 'variable' => array());
            $cmsCheck = sprintf($sql, 'content', $cmsBlockTable, 'content', 'content');
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content', $list);
            $cmsCheck = sprintf($sql, 'content', $cmsPageTable, 'content', 'content');
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content', $list);
            $emailCheck = sprintf($sql, 'template_text', $emailTemplate, 'template_text', 'template_text');
            $result = $db->fetchAll($emailCheck);
            $this->check($result, 'template_text', $list);
            $configCheck = sprintf($sql, 'value', $configTable, 'value', 'value');
            $result = $db->fetchAll($configCheck);
            $this->check($result, 'value', $list);
            $localeDir = \Mage::getBaseDir('locale');
            $scan = scandir($localeDir);
            $this->walkDir($scan, $localeDir, $list);

            $this->blocksWhitelist = $db->fetchPairs('SELECT block_id, block_name FROM '.$blockPermissionTable);
            $this->varsWhitelist = $db->fetchPairs('SELECT variable_id, variable_name FROM '.$variablePermissionTable);

            $nonWhitelistedBlocks = array_diff($list['block'], $this->blocksWhitelist);
            $nonWhitelistedVars = array_diff($list['variable'], $this->varsWhitelist);

            $sqlWhitelistBlocks = "INSERT IGNORE INTO permission_block (block_name, is_allowed) VALUES (:block_name, 1);";
            $sqlWhitelistVars = "INSERT IGNORE INTO permission_variable (variable_name, is_allowed) VALUES (:variable_name, 1);";
            if (count($nonWhitelistedBlocks) > 0) {
                $output->writeln("\033[0;31mFound blocks that are not whitelisted by default;\033[0;31m");
                foreach ($nonWhitelistedBlocks as $blockName) {
                    $output->writeln($blockName);
                    if ($this->_input->getOption('addblocks')) {
                        $dbwrite->query($sqlWhitelistBlocks, array('block_name' => $blockName));
                        $output->writeln("\033[1;33mWhitelisted " . $blockName . ".\033[1;33m");
                    }
                }
                $output->writeln("\033[0m");
            }
            if (count($nonWhitelistedVars) > 0) {
                $output->writeln("\033[0;31mFound template/block variables that are not whitelisted by default;\033[0;31m");
                foreach ($nonWhitelistedVars as $varName) {
                    $output->writeln($varName);
                    if ($this->_input->getOption('addvariables')) {
                        $dbwrite->query($sqlWhitelistVars, array('variable_name' => $varName));
                        $output->writeln("\033[1;33mWhitelisted " . $varName . ".\033[1;33m");
                    }
                }
            }
            if (count($nonWhitelistedBlocks) == 0 && count($nonWhitelistedVars) == 0) {
                $output->writeln("\033[1;32mYay! All blocks and variables are whitelisted.\033[0m");
            }
            $output->writeln("\033[0m");
        }
    }

    private function walkDir(array $dir, $path = '', &$list)
    {
        foreach ($dir as $subdir) {
            if (strpos($subdir, '.') !== 0) {
                if (is_dir($path . DS . $subdir)) {
                    $this->walkDir(scandir($path . DS . $subdir), $path . DS . $subdir, $list);
                } elseif (is_file($path . DS . $subdir) && pathinfo($subdir, PATHINFO_EXTENSION) !== 'csv') {
                    $this->check(array(file_get_contents($path . DS . $subdir)), null, $list);
                }
            }
        }
    }

    private function check($result, $field = 'content', &$list)
    {
        if ($result) {
            $blockMatch = '/{{block[^}]*?type=["\'](.*?)["\']/i';
            $varMatch = '/{{config[^}]*?path=["\'](.*?)["\']/i';
            foreach ($result as $res) {
                $target = ($field === null) ? $res: $res[$field];
                if (preg_match_all($blockMatch, $target, $matches)) {
                    foreach ($matches[1] as $match) {
                        if (!in_array($match, $list['block'])) {
                            $list['block'][] = $match;
                        }
                    }
                }
                if (preg_match_all($varMatch, $target, $matches)) {
                    foreach ($matches[1] as $match) {
                        if (!in_array($match, $list['variable'])) {
                            $list['variable'][] = $match;
                        }
                    }
                }
            }
        }
    }
}
