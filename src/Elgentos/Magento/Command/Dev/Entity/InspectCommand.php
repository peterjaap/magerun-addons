<?php

namespace Elgentos\Magento\Command\Dev\Entity;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class InspectCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('dev:entity:inspect')
            ->setDescription('Fetch database info for given entity')
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Which order do you to inspect?', null)
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Any regex filters on the parameters?', null)
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
        if (!$this->initMagento()) {
            $output->writeln('<error>Could not initialize Magento.</error>');
        }

        $table = new Table($output);
        $table->setHeaders(array('Entity Type', 'Entity ID', 'Increment ID', 'Parameter', 'Value'));

        $order = $input->getOption('order');
        $filters = array($input->getOption('filter'));

        $rows = [];
        if ($this->getOrderInfo($order)) {
            $rows = array_merge($rows, $this->getOrderInfo($order));
        }

        if ($this->getQuoteInfoFromOrder($order)) {
            $rows = array_merge($rows, $this->getQuoteInfoFromOrder($order));
        }

        if ($this->getCustomerInfoFromOrder($order)) {
            $rows = array_merge($rows, $this->getCustomerInfoFromOrder($order));
        }

        if ($this->getInvoiceInfoFromOrder($order)) {
            $rows = array_merge($rows, $this->getInvoiceInfoFromOrder($order));
        }

        if ($this->getCreditmemoInfoFromOrder($order)) {
            $rows = array_merge($rows, $this->getCreditmemoInfoFromOrder($order));
        }

        if (count($rows)) {
            if ($filters) {
                foreach ($rows as $rowKey => $row) {
                    foreach ($filters as $filter) {
                        if (!preg_match('/' . $filter . '/i', $row[3])) {
                            unset($rows[$rowKey]);
                        }
                    }
                }
            }

            $table->setRows($rows);
            $table->render();
        } else {
            $output->writeln('<info>Could not find order information for ' . $order . '</info>');
        }
    }

    protected function getOrderInfo($order)
    {
        $orderObject = $this->getOrderObject($order);

        if (!$orderObject) {
            return false;
        }

        $rows = [];

        foreach ($orderObject->getData() as $parameter => $value) {
            $rows[] = ['Order', $orderObject->getId(), $orderObject->getIncrementId(), $parameter, $value];
        }

        foreach ($orderObject->getAllItems() as $itemObject) {
            foreach ($itemObject->getData() as $parameter => $value) {
                $rows[] = ['Order Item', $itemObject->getId(), $itemObject->getIncrementId(), $parameter, substr($value, 0, 100) . (strlen($value) > 100 ? '...' : null)];
            }
        }

        return $rows;
    }

    protected function getCustomerInfoFromOrder($order)
    {
        if (!$order instanceof Mage_Sales_Model_Order) {
            $order = $this->getOrderObject($order);
        }

        $customerObject = \Mage::getModel('customer/customer')->load($order->getCustomerId());

        $rows = [];
        if ($customerObject->getId()) {
            foreach ($customerObject->getData() as $parameter => $value) {
                $rows[] = ['Customer', $customerObject->getId(), null, $parameter, $value];
            }

            foreach ($customerObject->getAddresses() as $addressObject) {
                foreach ($addressObject->getData() as $parameter => $value) {
                    if (
                        $addressObject->getId() == $customerObject->getDefaultShipping()
                        &&
                        $addressObject->getId() == $customerObject->getDefaultBilling()
                    ) {
                        $addressType = 'Default Shipping & Billing';
                    } elseif ($addressObject->getId() == $customerObject->getDefaultShipping()) {
                        $addressType = 'Default Shipping';
                    } elseif ($addressObject->getId() == $customerObject->getDefaultBilling()) {
                        $addressType = 'Default Billing';
                    } else {
                        $addressType = null;
                    }
                    $rows[] = ['Address', $addressType, $addressObject->getId(), $parameter, $value];
                }
            }
        }

        return $rows;
    }

    protected function getQuoteInfoFromOrder($order)
    {
        if (!$order instanceof Mage_Sales_Model_Order) {
            $order = $this->getOrderObject($order);
        }

        $quoteObject = \Mage::getModel('sales/quote')->setStore(\Mage::getSingleton('core/store')->load($order->getStoreId()))->load($order->getQuoteId());

        $rows = [];
        if ($quoteObject->getId()) {
            foreach ($quoteObject->getData() as $parameter => $value) {
                $rows[] = ['Quote', $quoteObject->getId(), null, $parameter, $value];
            }
        }

        return $rows;
    }

    protected function getInvoiceInfoFromOrder($order)
    {
        if (!$order instanceof Mage_Sales_Model_Order) {
            $order = $this->getOrderObject($order);
        }

        $rows = [];
        foreach ($order->getInvoiceCollection() as $invoiceObject) {
            foreach ($invoiceObject->getData() as $parameter => $value) {
                $rows[] = ['Invoice', $invoiceObject->getId(), $invoiceObject->getIncrementId(), $parameter, $value];
            }

            foreach ($invoiceObject->getAllItems() as $itemObject) {
                foreach ($itemObject->getData() as $parameter => $value) {
                    $rows[] = ['Invoice Item', $itemObject->getId(), $itemObject->getIncrementId(), $parameter, substr($value, 0, 100) . (strlen($value) > 100 ? '...' : null)];
                }
            }
        }

        return $rows;
    }

    protected function getCreditmemoInfoFromOrder($order)
    {
        if (!$order instanceof Mage_Sales_Model_Order) {
            $order = $this->getOrderObject($order);
        }

        $rows = [];
        foreach ($order->getCreditmemosCollection() as $creditmemoObject) {
            foreach ($creditmemoObject->getData() as $parameter => $value) {
                $rows[] = ['Creditmemo', $creditmemoObject->getId(), $creditmemoObject->getIncrementId(), $parameter, $value];
            }

            foreach ($creditmemoObject->getAllItems() as $itemObject) {
                foreach ($itemObject->getData() as $parameter => $value) {
                    $rows[] = ['Creditmemo Item', $itemObject->getId(), $itemObject->getIncrementId(), $parameter, substr($value, 0, 100) . (strlen($value) > 100 ? '...' : null)];
                }
            }
        }

        return $rows;
    }

    protected function getOrderObject($order)
    {
        $orderObject = \Mage::getModel('sales/order')->load($order);

        if (!$orderObject->getId()) {
            $orderObject = \Mage::getModel('sales/order')->load($order, 'increment_id');
        }

        if (!$orderObject->getId()) {
            return false;
        }

        return $orderObject;
    }
}
