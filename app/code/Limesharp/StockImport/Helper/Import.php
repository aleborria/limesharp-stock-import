<?php
namespace Limesharp\StockImport\Helper;

class Import extends \Magento\Framework\App\Helper\AbstractHelper
{

    const FILENAME = 'stock.csv';

    const VAR_CSV_PATH = 'import';

    const VAR_CSV_PROCESSED_PATH = 'import/processed';

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $io;

    /**
     * Filesystem Directory List
     *
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * CSV Processor
     *
     * @var \Magento\Framework\File\Csv
     */
    protected $csvProcessor;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    protected $indexerFactory;

    /**
     * @var \Magento\CatalogInventory\Model\Indexer\Stock
     */
    protected $stockIndexer;


    protected $updatedProducts = [];

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem\Io\File $io
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\File\Csv $csvProcessor
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Framework\App\Cache\TypeListInterface $typeListInterface
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\CatalogInventory\Model\Indexer\Stock $stockIndexer
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\App\Cache\TypeListInterface $typeListInterface,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\CatalogInventory\Model\Indexer\Stock $stockIndexer
    ) {
        parent::__construct($context);
        $this->io = $io;
        $this->directoryList = $directoryList;
        $this->csvProcessor = $csvProcessor;
        $this->stockRegistry = $stockRegistry;
        $this->cacheTypeList = $typeListInterface;
        $this->cache = $cache;
        $this->stockIndexer = $stockIndexer;
    }

    /**
     * Execute The Stock Import
     * @return $this
     */
    public function execute() {

        $varPath = $this->directoryList->getPath('var');
        $filePath = $varPath . DIRECTORY_SEPARATOR . self::VAR_CSV_PATH . DIRECTORY_SEPARATOR . self::FILENAME;

        if (file_exists($filePath)) {

            // Load CSV data
            $fileData = $this->csvProcessor->getData($filePath);

            if ($fileData && count($fileData)){

                // Process CSV Data
                foreach($fileData as $rowIndex => $row){
                    $explodedRow = explode('|', str_replace(' ', '', $row[0]));
                    if (count($explodedRow) == 2 && $explodedRow[0] !== '' && $explodedRow[1] !== ''){
                        $this->updateProductStock($explodedRow[0], $explodedRow[1]);
                    } else {
                        $this->_logger->error('Limesharp_StockImport: Row #' . $rowIndex . ' has wrong format.');
                    }
                }

                // Only clear Cache and reindex If some product was updated
                if (count($this->updatedProducts)){
                    $this->clearCache();
                    $this->reIndexStock();
                }

                // Check if exists and create var/import/processed directory
                // If the folder cannot be created, throw an exception.
                $processedFolderPath = $varPath . DIRECTORY_SEPARATOR . self::VAR_CSV_PROCESSED_PATH;
                $this->io->checkAndCreateFolder($processedFolderPath);

                // Generate new filename and add Date to file.
                $processedFilePath = $processedFolderPath . DIRECTORY_SEPARATOR;
                $date = date('Y-m-d--H-i-s');
                $processedFileName = $processedFilePath . $date . '__' . self::FILENAME;

                // Move and rename the file from var/import to var/import/processed
                $this->io->mv($filePath,$processedFileName);

            } else {
                $this->_logger->info('Limesharp_StockImport: '.self::FILENAME.' file is empty.');
            }
        } else {
            $this->_logger->error('Limesharp_StockImport: '.self::FILENAME.' file doesn\'t exists.');
        }

        return $this;
    }


    /**
     * Execute Catalog Inventory Reindex method
     * @return void
     */
    protected function reIndexStock(){
        $this->stockIndexer->executeFull();
    }

    /**
     * Clear Magento Cache for Category and Product Tags, Collections and Full Page Cache Types
     * @return void
     */
    protected function clearCache(){
        $this->cache->clean([\Magento\Catalog\Model\Category::CACHE_TAG,\Magento\Catalog\Model\Product::CACHE_TAG]);
        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Collection::TYPE_IDENTIFIER);
        $this->cacheTypeList->cleanType(\Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER);
    }


    /**
     * Update the Product Stock loaded By Sku
     *
     * @param string $sku
     * @param string $qty
     *
     * @return void
     */
    protected function updateProductStock($sku, $qty){
        try {
            $qty = (int)$qty;
            $stockItem = $this->stockRegistry->getStockItemBySku($sku); // load stock of that product

            // Check if the status is not the same
            // to avoid to clear cache and reindex is not necessary
            if ($stockItem->getQty() * 1 != $qty ||
                $stockItem->getIsInStock() != ($qty > 0) ||
                $stockItem->getIsInStock() != 1
            ){
                $stockItem->setIsInStock(($qty > 0));
                $stockItem->setQty($qty);
                $stockItem->setManageStock(1);
                $stockItem->save();

                $this->updatedProducts[] = $sku;
                $this->_logger->info('Limesharp_StockImport: Product SKU:' .$sku.' Stock Updated to '. $qty);
            } else {
                $this->_logger->info('Limesharp_StockImport: Product SKU:' .$sku.' Stock is the same');
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e){
            $this->_logger->warning('Limesharp_StockImport: Product SKU:' .$sku.' doesn\'t exists');
        }
    }
}