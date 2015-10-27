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
        'general/store_information/address',
        'general/store_information/name',
        'general/store_information/phone',
        'trans_email/ident_custom1/email',
        'trans_email/ident_custom1/name',
        'trans_email/ident_custom2/email',
        'trans_email/ident_custom2/name',
        'trans_email/ident_general/email',
        'trans_email/ident_general/name',
        'trans_email/ident_sales/email',
        'trans_email/ident_sales/name',
        'trans_email/ident_support/email',
        'trans_email/ident_support/name',
        'web/secure/base_url',
        'web/unsecure/base_url',
    );

    private static $blocksWhitelist = array(
        'core/template',
        'catalog/product_new'
    );

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
            $cmsBlockTable =  $resource->getTableName('cms/block');
            $cmsPageTable =  $resource->getTableName('cms/page');
            $emailTemplate =  $resource->getTableName('core/email_template');

            $sql = "SELECT %s FROM %s WHERE %s LIKE '%%{{config %%' OR  %s LIKE '%%{{block %%'";

            $list = ['block' => [], 'variable' => []];
            $cmsCheck = sprintf($sql, 'content', $cmsBlockTable, 'content', 'content');
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content', $list);

            $cmsCheck = sprintf($sql, 'content', $cmsPageTable, 'content', 'content');
            $result = $db->fetchAll($cmsCheck);
            $this->check($result, 'content', $list);

            $emailCheck = sprintf($sql, 'template_text', $emailTemplate, 'template_text', 'template_text');
            $result = $db->fetchAll($emailCheck);
            $this->check($result, 'template_text', $list);

            $localeDir = \Mage::getBaseDir('locale');
            $scan = scandir($localeDir);
            $this->walkDir($scan, $localeDir, $list);

            $nonWhitelistedBlocks = array_diff($list['block'], self::$blocksWhitelist);
            $nonWhitelistedVars = array_diff($list['variable'], self::$varsWhitelist);

            // Todo; add custom whitelisted blocks/vars to the above whitelists

            if(count($nonWhitelistedBlocks) > 0) {
                $output->writeln('Found blocks that are not whitelisted by default; ');
                foreach ($nonWhitelistedBlocks as $blockName) {
                    $output->writeln($blockName);
                }
                $output->writeln('');
            }

            if(count($nonWhitelistedVars) > 0) {
                echo 'Found template/block variables that are not whitelisted by default; ' . PHP_EOL;
                foreach ($nonWhitelistedVars as $varName) {
                    $output->writeln($varName);
                }
            }

            if(count($nonWhitelistedBlocks) == 0 && count($nonWhitelistedVars) == 0) {
                $output->writeln('Yay! All blocks and variables are whitelisted.');
            }

        }
    }

    private function walkDir(array $dir, $path = '', &$list) {
        foreach ($dir as $subdir) {
            if (strpos($subdir, '.') !== 0) {
                if(is_dir($path . DS . $subdir)) {
                    $this->walkDir(scandir($path . DS . $subdir), $path . DS . $subdir, $list);
                } elseif (is_file($path . DS . $subdir) && pathinfo($subdir, PATHINFO_EXTENSION) !== 'csv') {
                    $this->check([file_get_contents($path . DS . $subdir)], null, $list);
                }
            }
        }
    }

    private function check($result, $field = 'content', &$list) {
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