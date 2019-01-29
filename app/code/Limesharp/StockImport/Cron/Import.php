<?php
namespace Limesharp\StockImport\Cron;

class Import {

    /**
     * CRON CONFIG FOR STOCK PRODUCT IMPORT
     */
    const CRON_ACTIVE_CONFIG = 'limesharp/stockimport/cron_active';

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Limesharp Stock Import Logic
     *
     * @var \Limesharp\StockImport\Helper\Import
     */
    protected $importer;

    /**
     * Scope Config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;


    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeInterface
     * @param \Limesharp\StockImport\Helper\Import $helperImport
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeInterface,
        \Limesharp\StockImport\Helper\Import $helperImport
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeInterface;
        $this->importer = $helperImport;
    }

    public function execute() {
        // Get if import is active from configuration
        $isActive = $this->scopeConfig->getValue(
            self::CRON_ACTIVE_CONFIG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($isActive == 1) {
            $this->logger->info('Limesharp_StockImport: Cron Import Started');

            // Instance Logic into Helper to can use it without the Cron
            $this->importer->execute();

            $this->logger->info('Limesharp_StockImport: Cron Import Finished');
        }

        return $this;
    }
}