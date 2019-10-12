<?php

namespace Elgentos\Magento\Command\Media\Images;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanCacheCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('media:images:cleancache')
                ->setDescription('Remove catalog product cache directory. [elgentos]');
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
            return 1;
        }

        /** @var \Mage_Catalog_Model_Product_Image $productImageModel */
        $productImageModel = $this->_getModel('catalog/product_image', '\Mage_Catalog_Model_Product_Image');

        $productImageModel->clearCache();

        $output->writeln('Catalog product cache removed.');

        return 0;
    }
}
