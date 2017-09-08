<?php

namespace DigitalPianism\ChangePassword\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Customer;
use Magento\Framework\Stdlib\StringUtils as StringHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State as AppState;

/**
 * Class ChangePassword
 * @package DigitalPianism\ChangePassword\Console\Command
 */
class ChangePassword extends Command
{

    /**#@+
     * Data keys
     */
    const KEY_CUSTOMER_ID = 'customer-id';
    const KEY_CUSTOMER_PASSWORD = 'customer-password';

    /**
     * @var CustomerRegistry
     */
    private $_customerRegistry;

    /**
     * @var Customer
     */
    private $_customer;

    /**
     * @var StringHelper
     */
    private $_stringHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * @var AppState
     */
    private $_appState;

    /**
     * @param CustomerRegistry $customerRegistry
     * @param StringHelper $stringHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param AppState $appState
     */
    public function __construct(
        CustomerRegistry $customerRegistry,
        StringHelper $stringHelper,
        ScopeConfigInterface $scopeConfig,
        AppState $appState
    ) {
        $this->_appState = $appState;
        $this->_scopeConfig = $scopeConfig;
        $this->_stringHelper = $stringHelper;
        $this->_customerRegistry = $customerRegistry;
        parent::__construct();
    }

    /**
     * Initialization of the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('customer:changepassword')
            ->setDescription('Change customer password')
            ->setDefinition($this->getOptionsList());
        parent::configure();
    }

    /**
     * Get list of arguments for the command
     *
     * @return InputOption[]
     */
    public function getOptionsList()
    {
        return [
            new InputOption(self::KEY_CUSTOMER_ID, null, InputOption::VALUE_REQUIRED, '(Required) Customer ID'),
            new InputOption(self::KEY_CUSTOMER_PASSWORD, null, InputOption::VALUE_REQUIRED, '(Required) Customer password')
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->_appState->getAreaCode()) {
            $this->_appState->setAreaCode('adminhtml');
        }
        $errors = $this->validate($input);
        if ($errors) {
            $output->writeln('<error>' . implode('</error>' . PHP_EOL .  '<error>', $errors) . '</error>');
            // we must have an exit code higher than zero to indicate something was wrong
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
        $this->_customer
            ->changePassword($input->getOption(self::KEY_CUSTOMER_PASSWORD))
            ->save();

        $output->writeln(
            '<info>Password for customer #' . $input->getOption(self::KEY_CUSTOMER_ID) . ' has been successfully changed</info>'
        );
    }

    /**
     * Check if all admin options are provided
     *
     * @param InputInterface $input
     * @return string[]
     */
    public function validate(InputInterface $input)
    {
        $errors = [];

        try {
            $this->checkPasswordStrength($input->getOption(self::KEY_CUSTOMER_PASSWORD));
            /** @var Customer $customer */
            $this->_customer = $this->_customerRegistry->retrieve($input->getOption(self::KEY_CUSTOMER_ID));
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Make sure that password complies with minimum security requirements.
     *
     * @param string $password
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function checkPasswordStrength($password)
    {
        $length = $this->_stringHelper->strlen($password);
        if ($length > \Magento\Customer\Model\AccountManagement::MAX_PASSWORD_LENGTH) {
            throw new \Magento\Framework\Exception\InputException(
                __(
                    'Please enter a password with at most %1 characters.',
                    \Magento\Customer\Model\AccountManagement::MAX_PASSWORD_LENGTH
                )
            );
        }
        $configMinPasswordLength = $this->getMinPasswordLength();
        if ($length < $configMinPasswordLength) {
            throw new \Magento\Framework\Exception\InputException(
                __(
                    'Please enter a password with at least %1 characters.',
                    $configMinPasswordLength
                )
            );
        }
        if ($this->_stringHelper->strlen(trim($password)) != $length) {
            throw new \Magento\Framework\Exception\InputException(__('The password can\'t begin or end with a space.'));
        }

        $requiredCharactersCheck = $this->makeRequiredCharactersCheck($password);
        if ($requiredCharactersCheck !== 0) {
            throw new \Magento\Framework\Exception\InputException(
                __(
                    'Minimum of different classes of characters in password is %1.' .
                    ' Classes of characters: Lower Case, Upper Case, Digits, Special Characters.',
                    $requiredCharactersCheck
                )
            );
        }
    }

    /**
     * Retrieve minimum password length
     *
     * @return int
     */
    protected function getMinPasswordLength()
    {
        return $this->_scopeConfig->getValue(\Magento\Customer\Model\AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH);
    }

    /**
     * Check password for presence of required character sets
     *
     * @param string $password
     * @return int
     */
    protected function makeRequiredCharactersCheck($password)
    {
        $counter = 0;
        $requiredNumber = $this->_scopeConfig->getValue(\Magento\Customer\Model\AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER);
        $return = 0;

        if (preg_match('/[0-9]+/', $password)) {
            $counter ++;
        }
        if (preg_match('/[A-Z]+/', $password)) {
            $counter ++;
        }
        if (preg_match('/[a-z]+/', $password)) {
            $counter ++;
        }
        if (preg_match('/[^a-zA-Z0-9]+/', $password)) {
            $counter ++;
        }

        if ($counter < $requiredNumber) {
            $return = $requiredNumber;
        }

        return $return;
    }
}
