<?php
namespace Limesharp\StockImport\Cron;

use \Magento\Cron\Model\Schedule;

class Import {

    /**
     * CRON CONFIG FOR STOCK PRODUCT IMPORT
     */
    const CRON_ACTIVE_CONFIG = 'limesharp/stockimport/cron_active';

    const CRON_JOB_CODE = 'limesharp_stockimport';

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
     * @var \Magento\Cron\Model\ScheduleFactory
     */
    protected $_scheduleFactory;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeInterface
     * @param \Limesharp\StockImport\Helper\Import $helperImport
     * @param \Magento\Cron\Model\ScheduleFactory $scheduleFactory
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeInterface,
        \Limesharp\StockImport\Helper\Import $helperImport,
        \Magento\Cron\Model\ScheduleFactory $scheduleFactory
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeInterface;
        $this->importer = $helperImport;
        $this->scheduleFactory = $scheduleFactory;
    }

    public function execute() {
        // Get if import is active from configuration
        $isActive = $this->scopeConfig->getValue(
            self::CRON_ACTIVE_CONFIG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Check if cron is active and if there is more than 1 cron running
        if ($isActive == 1 && $this->getCronImportsRunning()->getSize() <= 1) {
            $this->logger->info('Limesharp_StockImport: Cron Import Started');

            // Instance Logic into Helper to can use it without the Cron
            $this->importer->execute();

            $this->logger->info('Limesharp_StockImport: Cron Import Finished');
        } else {
            if ($this->getCronImportsRunning()->getSize()){
                $this->logger->info('Limesharp_StockImport: Another Stock Import cron is running');
            }
        }

        return $this;
    }

    /**
     * Return job collection from data base with status 'running'
     *
     * @return $pendingImportJobs \Magento\Cron\Model\ResourceModel\Schedule\Collection
     */
    private function getCronImportsRunning()
    {
        $pendingImportJobs = $this->scheduleFactory->create()->getCollection();
        $pendingImportJobs->addFieldToFilter('job_code', self::CRON_JOB_CODE);
        $pendingImportJobs->addFieldToFilter('status', Schedule::STATUS_RUNNING);
        return $pendingImportJobs;
    }
}