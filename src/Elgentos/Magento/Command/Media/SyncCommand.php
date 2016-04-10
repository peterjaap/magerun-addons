<?php

namespace Elgentos\Magento\Command\Media;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

class SyncCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('media:sync')
            ->setDescription('Sync media files from remote server [elgentos]')
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Mode (SSH or FTP)')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port for SSH connection')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host for SSH/FTP connection')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username for SSH/FTP connection')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password (required when using FTP)')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Path to sync from (/media will be appended)')
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Excluded paths')
            ->addOption('ignore-permissions', null, InputOption::VALUE_OPTIONAL, 'Ignore file permissions');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);

        if (!$this->initMagento()) {
            return;
        }

        $options = array(
            'port' => '',
            'host' => '',
            'username' => '',
            'password' => '',
            'path' => '',
            'exclude' => array(),
            'ignore-permissions' => ''
        );

        $requiredOptions = array(
            'ftp' => array('host', 'username', 'password'),
            'ssh' => array('host', 'username'),
        );

        $config = \Mage::app()->getConfig();
        $dialog = $this->getHelperSet()->get('dialog');

        $mode = $input->getOption('mode');
        $interactiveExecution = $mode === null; // if no mode is set, this run is interactive

        if ($interactiveExecution) {
            $mode = strtolower($dialog->ask($output,
                '<question>Mode (SSH or FTP)</question> <comment>[ssh]</comment>: ', 'ssh'));
        }

        if (!array_key_exists($mode, $requiredOptions)) {
            $output->writeln('<error>Only SSH or FTP is supported.</error>');
        }

        if ($mode === 'ssh') {
            // rsync doesn't allow SSH password (easily) due to security concerns
            // assume key auth
            unset($options['password']);
        }

        foreach ($options as $option => $defaultValue) {
            // Fetch config value from app/etc/local.xml (if available)
            $node = $config->getNode('global/production/' . $mode . '/' . $option);
            $configValue = (string)$node[0];

            if (!empty($configValue)) {
                // use the configuration value automatically when running non-interactively, otherwise allow confirmation or change
                if (!$interactiveExecution) {
                    $optionValue = $configValue;
                } else {
                    $optionValue = $dialog->ask($output, '<question>' . strtoupper($mode) . ' ' . ucwords($option) . ' </question> <comment>[' . $configValue . ']</comment>: ', $configValue);
                }
            } else {
                // no configuration value, get option value if running non-interactively, otherwise ask interactively
                if (!$interactiveExecution) {
                    $optionValue = $input->getOption($option);
                } else {
                    $optionValue = $dialog->ask($output, '<question>' . strtoupper($mode) . ' ' . ucwords($option) . '</question> <comment>[' . $defaultValue . ']</comment>: ', $defaultValue);
                }
            }

            if (in_array($option, $requiredOptions[$mode]) && empty($optionValue)) {
                $output->writeln('<error>Option ' . $option . ' cannot be empty!</error>');
                exit;
            }

            $options[$option] = $optionValue;
        }

        if (!is_array($options['exclude'])) {
            // If this value is set interactively, it is supposed to come in as (comma-separated) string
            $options['exclude'] = array_filter(explode(',', $options['exclude']));
        }

        $options['path'] = trim($options['path'], DIRECTORY_SEPARATOR);

        if ($mode === 'ssh') {
            // Syncing over SSH using rsync
            $package = exec('which rsync');

            if (empty($package)) {
                $output->writeln('<error>Package rsync is not installed!</error>');
                exit;
            }

            $exec = 'rsync -avz ';

            if (!empty($options['port'])) {
                $exec .= '-e "ssh -p ' . $options['port'] . '" ';
            }

            if (!empty($options['ignore-permissions'])) {
                $exec .= '--no-perms --no-owner --no-group ';
            }

            $exec .= '--ignore-existing --exclude=*cache* ';

            if (!empty($options['exclude'])) {
                foreach ($options['exclude'] as $exclude) {
                    $exec .= '--exclude=' . $exclude . ' ';
                }
            }
            $exec .= $options['username'] . '@' . $options['host'] . ':' . $options['path'] . DS . 'media/* ' . $this->getApplication()->getMagentoRootFolder() . '/media';
        } elseif ($mode == 'ftp') {
            // Syncing over FTP using ncftpget
            $package = exec('which ncftpget');

            if (empty($package)) {
                $output->writeln('<error>Package ncftpget is not installed!</error>');
                exit;
            }

            // Unfortunately no exclude option with ncftpget so cache files are also synced
            $exec = 'ncftpget -R -v -u "' . $options['username'] . '" -p "' . $options['password'] . '" ' . $options['host'] . ' ' . $options['path'] . DS . 'media' . DS . '* ' . $this->getApplication()->getMagentoRootFolder() . DS . 'media' . DS;
        }

        $output->writeln($exec);
        $output->writeln('<info>Syncing media files to local server...</info>');

        $process = new Process($exec);
        $process->setTimeout(3600 * 10);
        $process->setIdleTimeout(3600);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERROR > ' . $buffer;
            } else {
                echo $buffer;
            }
        });

        $output->writeln('<info>Finished</info>');
    }
}
