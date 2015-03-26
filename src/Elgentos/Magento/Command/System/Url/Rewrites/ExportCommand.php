<?php

namespace Elgentos\Magento\Command\System\Url\Rewrites;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExportCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('sys:store:url:rewrites:export')
            ->setDescription('Export custom rewrite URLs for .htaccess [elgentos]');
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
            $dialog = $this->getHelperSet()->get('dialog');

            $redirectType = $dialog->select(
                $output,
                'Which redirect type?',
                array('301 Permanent', '302 Temporary'),
                0
            );

            $onlyProductRewrites = $dialog->askConfirmation($output,
                '<question>Only export product rewrites?</question> <comment>[yes]</comment> ', true);

            $skipCategoryRewrites = $dialog->askConfirmation($output,
                '<question>Skip category rewrites?</question> <comment>[no]</comment> ', false);

            $deleteRewrites = $dialog->askConfirmation($output,
                '<question>Delete rewrites after output? (Caution! This is irreversible!)</question> <comment>[no]</comment> ',
                false);

            $filename = $dialog->ask($output, '<question>Filename for output (leave empty for stdout): </question>');

            $outputType = $dialog->select(
                $output,
                'Which output format?',
                array('Apache (.htaccess)', 'nginx'),
                0
            );
            $outputType = ($outputType ? 'nginx' : 'apache');

            if ($outputType == 'nginx') {
                $redirectType = ($redirectType ? 'redirect' : 'permanent');
            } elseif ($outputType == 'apache') {
                $redirectType = ($redirectType ? 302 : 301);
            }

            $resource = \Mage::getModel('core/resource');
            $db = $resource->getConnection('core_write');

            $customCoreUrlRewritesSelect = $db->select()
                ->from($resource->getTableName('core_url_rewrite'))
                ->where('is_system = ?', 0);

            if ($onlyProductRewrites) {
                $customCoreUrlRewritesSelect->where('product_id IS NOT NULL');
            }

            if ($skipCategoryRewrites) {
                $customCoreUrlRewritesSelect->where('category_id IS NULL');
            }
            $customCoreUrlRewrites = $db->fetchAll($customCoreUrlRewritesSelect);

            $outputRedirects = '';

            $rewriteIds = array();

            foreach ($customCoreUrlRewrites as $rewrite) {
                $rewriteIds[] = $rewrite['url_rewrite_id'];
                if (!isset($baseUrlCache[$rewrite['store_id']])) {
                    $secure = \Mage::getStoreConfig('web/secure/use_in_frontend', $rewrite['store_id']);
                    $baseUrl = \Mage::getStoreConfig('web/' . ($secure ? '' : 'un') . 'secure/base_url', $rewrite['store_id']);
                    $baseUrlCache[$rewrite['store_id']] = $baseUrl;
                } else {
                    $baseUrl = $baseUrlCache[$rewrite['store_id']];
                }
                if ($outputType == 'apache') {
                    $outputRedirects .= 'Redirect ' . $redirectType . ' /' . $rewrite['request_path'] . ' ' . $baseUrl . $rewrite['target_path'] . PHP_EOL;
                } elseif ($outputType == 'nginx') {
                    $outputRedirects .= 'location /' . $rewrite['request_path'] . ' {' . PHP_EOL;
                    $outputRedirects .= '    rewrite ^(.*)$ ' . $baseUrl . $rewrite['target_path'] . ' ' . $redirectType . ';' . PHP_EOL;
                    $outputRedirects .= '}';
                    $outputRedirects .= PHP_EOL . PHP_EOL;
                }
            }

            if ($filename && !file_exists($filename)) {
                if (file_put_contents($filename, $outputRedirects)) {
                    $output->writeln('Succesfully written redirects to ' . $filename);
                } else {
                    $output->writeln('Could not write to file ' . $filename . '; check write permissions?');
                }
            } elseif ($filename) {
                $output->writeln('File ' . $filename . ' already exists; aborting.');
            } else {
                $output->writeln($outputRedirects);
            }

            if ($deleteRewrites) {
                $db->delete($resource->getTableName('core_url_rewrite'), array('url_rewrite_id IN (?)' => $rewriteIds));
                $output->writeln(count($rewriteIds) . ' rewrites have been deleted.');
            }
        }
    }
}
