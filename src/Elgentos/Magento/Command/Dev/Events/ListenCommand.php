<?php

namespace Elgentos\Magento\Command\Dev\Events;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ListenCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('dev:events:listen')
            ->setDescription('Listen to events being dispatched live [elgentos]')
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        declare(ticks = 1);

        $this->output = $output;

        // Register shutdown function
        register_shutdown_function(array($this, 'stopCommand'));

        // Register SIGTERM/SIGINT catch if script is killed by user
        if(function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, array($this, 'stopCommand'));
            pcntl_signal(SIGINT, array($this, 'stopCommand'));
        } else {
            $this->output->writeln('<options=bold>Note:</> The PHP function pcntl_signal isn\'t defined, which means you\'ll have to do some manual clean-up after using this command.');
            $this->output->writeln('Remove the file \'app/Mage.php.rej\' and the line \'Mage::log($name, null, \'n98-magerun-events.log\');\' from app/Mage.php after you\'re done.');
        }

        $this->detectMagento($output);
        if ($this->initMagento()) {
            $currentMagerunDir = dirname(__FILE__);
            $patch = $currentMagerunDir . '/0001-Added-logging-of-events.patch';
            // Enable logging & apply patch
            $command = $this->getApplication()->find('dev:log');
            $arguments = array(
                'command' => 'dev:log',
                '--on' => true,
                '--global' => true
            );
            $input = new ArrayInput($arguments);
            $returnCode = $command->run($input, new NullOutput);
            shell_exec('cd ' . \Mage::getBaseDir() . ' && patch -p1 < ' . $patch);
            $output->writeln('Tailing events... ');
            // Listen to log file
            shell_exec('echo "" > ' . \Mage::getBaseDir() . '/var/log/n98-magerun-events.log');
            $handle = popen('tail -f ' . \Mage::getBaseDir() . '/var/log/n98-magerun-events.log 2>&1', 'r');
            while(!feof($handle)) {
                $buffer = fgets($handle);
                $output->write($buffer);
                flush();
            }
            pclose($handle);
        }
    }

    public function stopCommand()
    {
        $currentMagerunDir = dirname(__FILE__);
        $revertPatch = $currentMagerunDir . '/0001-Revert-Added-logging-of-events.patch';
        // Revert patch
        shell_exec('cd ' . \Mage::getBaseDir() . ' && patch -p1 < ' . $revertPatch);
        if(file_exists(\Mage::getBaseDir() . '/app/Mage.php.rej')) {
            unlink(\Mage::getBaseDir() . '/app/Mage.php.rej');
        }
        $this->output->writeln(PHP_EOL . 'Cleaning up and exiting...');
    }
}
