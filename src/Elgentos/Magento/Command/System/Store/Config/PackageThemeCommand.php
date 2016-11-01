<?php
namespace Elgentos\Magento\Command\System\Store\Config;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PackageThemeCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('sys:store:config:package:set')
            ->setDescription('Set package and theme [elgentos]')
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
            $dialog = $this->getHelperSet()->get('dialog');
            $config = $this->_getModel('core/config', 'Mage_Core_Model_Config');

            $packageName = $dialog->ask($output, '<question>New package name?</question> <comment>[ base ] </comment>');

            $config->saveConfig(
                'design/package/name/',
                $packageName,
                'default',
                0
            );

            $themeName = $dialog->ask($output, '<question>New name of the theme?</question> <comment>[ default ] </comment>');

            if ($themeName) {
                foreach (array('default', 'locale', 'template', 'skin', 'layout') as $type) {
                    $config->saveConfig(
                        'design/theme/' . $type,
                        $themeName,
                        'default',
                        0
                    );
                }

                $output->writeln('<info>Package and theme are set.</info>');
            }

        }
    }
}