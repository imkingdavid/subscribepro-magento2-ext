<?php
/**
 * Copyright © Subscribe Pro Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Swarming\SubscribePro\Console\Command;

use Magento\Config\App\Config\Type\System;
use Magento\Config\Console\Command\ConfigSet\ProcessorFacadeFactory;
use Magento\Deploy\Model\DeploymentConfig\ChangeDetector;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Magento\Framework\File\Csv;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/**
 * Import from a CSV file into the vault_payment_token table
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.2.0
 */
class VaultImportCommand extends Command
{
    /**
     * Credit card token factory
     *
     * @var CreditCardTokenFactory
     */
    protected $creditCardTokenFactory;

    /**
     * Payment Token repository
     *
     * @var PaymentTokenRepositoryInterface
     */
    protected $paymentTokenRepository;

    /**
     * Csv file parser
     *
     * @var Csv
     */
    protected $csv;

    /**
     * Application deployment configuration
     *
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param EmulatedAdminhtmlAreaProcessor $emulatedAreaProcessor Emulator adminhtml area for CLI command
     * @param ChangeDetector $changeDetector The config change detector
     * @param ProcessorFacadeFactory $processorFacadeFactory The factory for processor facade
     * @param DeploymentConfig $deploymentConfig Application deployment configuration
     */
    public function __construct(
        CreditCardTokenFactory $creditCardTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Csv $csv,
        DeploymentConfig $deploymentConfig
    ) {
        $this->creditCardTokenFactory = $creditCardTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->csv = $csv;
        $this->deploymentConfig = $deploymentConfig;

        parent::__construct();
    }

    /**
     * @inheritdoc
     * @since 100.2.0
     */
    protected function configure()
    {
        $this->setName('subscribe-pro:vault:import')
            ->setDescription('Import a CSV into the vault_payment_token table.')
            ->setDefinition([
                new InputArgument(
                    'csv-file',
                    InputArgument::REQUIRED,
                    'Configuration path in format section/group/field_name'
                ),
            ]);

        parent::configure();
    }

    /**
     * Creates and run appropriate processor, depending on input options.
     *
     * {@inheritdoc}
     * @since 100.2.0
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->deploymentConfig->isAvailable()) {
            $output->writeln(
                '<error>You cannot run this command because the Magento application is not installed.</error>'
            );
            return Cli::RETURN_FAILURE;
        }

        try {
            $csvData = $this->csv->getData($input->getArgument('csv-file'));

            // Convert array from
            // [ [ 'header', 'row' ], [ 'thing', 'value'] ]
            // To
            // [ [ 'header' => 'thing' ], [ 'row' => 'value' ] ]
            $headerRow = $csvData[0];
            $paymentTokenData = [];
            for ($i = 1; $i < sizeof($csvData); $i++) {
                for ($j = 0; $j < sizeof($csvData[$i]); $j++) {
                    $paymentTokenData[$i][$headerRow[$j]] = $csvData[$i][$j];
                }
            }

            foreach ($paymentTokenData as $rowNum => $csvRow) {
                if (!$rowNum) {
                    $headerRow = $csvRow;
                    continue;
                }
                // Make sure it has all the necessary data
                $requiredFields = [
                    'gateway_token',
                    'customer_id',
                    'details',
                ];
                foreach ($requiredFields as $requiredField) {
                    if (!isset($csvRow[$requiredField])) {
                        $output->writeln('<error>Missing required data: ' . $requiredField . '</error>');
                        return Cli::RETURN_FAILURE;
                    }
                }

                $spProfileId = $csvRow['gateway_token'];
                $tokenDetails = json_decode($csvRow['details'], true);
                $customerId = $csvRow['customer_id'];
                $expirationDate = $csvRow['expires_at'];
                $publicHash = $csvRow['public_hash'];

                // Stuff we don't need to pull from the CSV
                $paymentMethodCode = 'subscribe_pro';
                $paymentTokenType = 'card';
                $isActive = true;
                $isVisible = true;

                $requiredDetails = ['type', 'maskedCC', 'expirationDate', 'paymentToken'];
                foreach ($requiredDetails as $requiredDetail) {
                    if (!isset($tokenDetails[$requiredDetail])) {
                        $output->writeln('<error>Missing required detail: ' . $requiredDetail . '</error>');
                        return Cli::RETURN_FAILURE;
                    }
                }

                $paymentToken = $this->creditCardTokenFactory->create();
                $paymentToken->setExpiresAt($expirationDate);
                $paymentToken->setGatewayToken($spProfileId);
                $paymentToken->setTokenDetails(json_encode($tokenDetails));
                $paymentToken->setType($paymentTokenType);
                $paymentToken->setIsActive($isActive);
                $paymentToken->setIsVisible($isVisible);
                $paymentToken->setPaymentMethodCode($paymentMethodCode);
                $paymentToken->setCustomerId($customerId);
                $paymentToken->setPublicHash($publicHash);

                $this->paymentTokenRepository->save($paymentToken);
            }

            $output->writeln('<info>Completed payment profile import.</info>');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }
    }
}