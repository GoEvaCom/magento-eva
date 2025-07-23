<?php
namespace GoEvaCom\Integration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class IntegrationManager extends AbstractHelper
{
    protected $storeManager;
    protected $httpClientFactory;
    protected $productRepository;
    private $logger;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\HTTP\Client\CurlFactory $httpClientFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->httpClientFactory = $httpClientFactory;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function getStoreAddress()
    {
        return $this->scopeConfig->getValue(
            'shipping/origin/street_line1',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getStoreCity()
    {
        return $this->scopeConfig->getValue(
            'shipping/origin/city',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getStorePostcode()
    {
        return $this->scopeConfig->getValue(
            'shipping/origin/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function bookDelivery($orderId, $deliveryData)
    {

        $is_live = $this->scopeConfig->getValue('carriers/evadelivery/islive');
        $apiUrl = $is_live ? $this->scopeConfig->getValue('carriers/evadelivery/produrl') : $this->scopeConfig->getValue('carriers/evadelivery/stagingurl');
        $apiKey = $is_live ? $this->scopeConfig->getValue('carriers/evadelivery/prodtoken') : $this->scopeConfig->getValue('carriers/evadelivery/stagingtoken');
        
        if (!$apiKey) {
            throw new \Exception('[Helper] Eva API key missing');
        }

        if (!isset($deliveryData['pickup_items']) || empty($deliveryData['pickup_items'])) {
            throw new \Exception('[Helper] No pickup items provided');
        }

        $client = $this->httpClientFactory->create();
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];

        $data = [
            'ride_type_id' => (int)3,
            'vehicle_type_id' => (int)1,
            'from_address' => $deliveryData['pickup_address']['street'] . ', ' . 
                            $deliveryData['pickup_address']['city'] . ', ' . 
                            $deliveryData['pickup_address']['postcode'],
            'to_address' => $deliveryData['delivery_address']['street'] . ', ' . 
                          $deliveryData['delivery_address']['city'] . ', ' . 
                          $deliveryData['delivery_address']['postcode'],
            'pickup_items' => $deliveryData['pickup_items'],
            'order_number' => $orderId,
            'customer_first_name' => $deliveryData['customer_first_name'],
            'customer_last_name' => $deliveryData['customer_last_name'],
            'customer_phone_number' => $deliveryData['customer_phone_number'],
            'customer_email' => $deliveryData['customer_email'],
            'order_note' => $deliveryData['order_note']
        ];

        $client->setOption(CURLOPT_HTTPHEADER, $headers);
        $client->setOption(CURLOPT_RETURNTRANSFER, true);
        $client->setOption(CURLOPT_TIMEOUT, 30);
        $client->post($apiUrl . '/api/v3/rides/', json_encode($data));
        
        $response = $client->getBody();
        $httpCode = $client->getStatus();
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        throw new \Exception('Booking failed: ' . $httpCode . ' - ' . $response);
    }

     /**
     * Check if product allows special shipping method
     *
     * @param int|Product $product
     * @return bool
     */
    public function isSpecialShippingAllowed($product)
    {
        try {
            if (is_numeric($product)) {
                $product = $this->productRepository->getById($product);
            }

            if (!$product instanceof Product) {
                return false;
            }

            $attributeValue = $product->getData(AttributeManager::ATTRIBUTE_CODE);
            
            // Convert to boolean - '1' or 1 = true, '0' or 0 or null = false
            return (bool) $attributeValue;

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Product not found: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error checking special shipping attribute: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the special shipping attribute value
     *
     * @param int|Product $product
     * @return int|null
     */
    public function getSpecialShippingAttributeValue($product)
    {
        try {
            if (is_numeric($product)) {
                $product = $this->productRepository->getById($product);
            }

            if (!$product instanceof Product) {
                return null;
            }

            return $product->getData(AttributeManager::ATTRIBUTE_CODE);

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Product not found: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting special shipping attribute: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if multiple products allow special shipping
     *
     * @param array $productIds
     * @return bool
     */
    public function areAllProductsAllowedForSpecialShipping(array $productIds)
    {
        foreach ($productIds as $productId) {
            if (!$this->isSpecialShippingAllowed($productId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get products that don't allow special shipping from a list
     *
     * @param array $productIds
     * @return array
     */
    public function getProductsNotAllowedForSpecialShipping(array $productIds)
    {
        $notAllowedProducts = [];
        
        foreach ($productIds as $productId) {
            if (!$this->isSpecialShippingAllowed($productId)) {
                $notAllowedProducts[] = $productId;
            }
        }
        
        return $notAllowedProducts;
    }
}