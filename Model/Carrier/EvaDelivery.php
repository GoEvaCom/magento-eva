<?php
namespace GoEvaCom\Integration\Model\Carrier;

use Exception;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use GoEvaCom\Integration\Helper\AttributeManager;

class EvaDelivery extends AbstractCarrier implements CarrierInterface
{
    const CARRIER_CODE = 'evadelivery';
    protected $_code = self::CARRIER_CODE;
    protected $rateResultFactory;
    protected $rateMethodFactory;
    protected $httpClientFactory;
    protected $helper;
    protected $quote;
    protected $attributeManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\HTTP\Client\CurlFactory $httpClientFactory,
        \GoEvaCom\Integration\Helper\IntegrationManager $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\Quote $quote,
        AttributeManager $attributeManager,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->quote = $quote;
        $this->attributeManager = $attributeManager;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        if (!$this->hasEligibleProducts($request)) {
            return false;
        }

        $result = $this->rateResultFactory->create();
        
        try {
            $quote = $this->getQuoteFromAPI($request);
            
            if ($quote && isset($quote['price'])) {
                $method = $this->rateMethodFactory->create();
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData('title'));
                $method->setMethod($this->_code);
                $method->setMethodTitle($this->getConfigData('name'));
                $method->setPrice($quote['price']);
                $method->setCost($quote['price']);
                
                $result->append($method);
            }
        } catch (\Exception $e) {
            $this->_logger->error('Eva Delivery Integration Error: ' . $e->getMessage());
            return false;
        }

        return $result;
    }

    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    private function getQuoteFromAPI(RateRequest $request)
    {
        $is_live = $this->getConfigData('islive');
        $apiUrl = $is_live ? $this->getConfigData('produrl') : $this->getConfigData('stagingurl');
        $apiKey = $is_live ? $this->getConfigData('prodtoken') : $this->getConfigData('stagingtoken');
    
        if (!$apiKey) {
            throw new \Exception('[Carrier] Eva API key missing');
        }

        $pickupItems = [];
        $allItems = $request->getAllItems();

        if ($allItems) {
            foreach ($allItems as $item) {

                
                if ($item->getParentItem()) {
                    continue;
                }
                
                if (!$item->getName()) {
                    continue;
                }
                
                $pickupItems[] = [
                    'name' => $item->getName(),
                    'category' => 'General',
                    'weight' => (float)$item->getWeight(),
                    'length' => (float)($item->getProduct()->getLength() ?: 0),
                    'width' => (float)($item->getProduct()->getWidth() ?: 0),
                    'height' => (float)($item->getProduct()->getHeight() ?: 0),
                    'quantity' => (int)$item->getQty()
                ];
            }
        }
        
        if (empty($pickupItems)) {
            throw new \Exception('[Carrier] No valid pickup items found');
        }

        $storeAddress = $this->helper->getStoreAddress();
        $storeCity = $this->helper->getStoreCity();
        $storePostcode = $this->helper->getStorePostcode();
        
        if (!$storeAddress || !$storeCity || !$storePostcode) {
            throw new \Exception('[Carrier] Store address information incomplete');
        }
        
        if (!$request->getDestStreet() || !$request->getDestCity() || !$request->getDestPostcode()) {
            throw new \Exception('[Carrier] Destination address information incomplete');
        }

        $client = $this->httpClientFactory->create();
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];

        $data = [
            'ride_type_id' => 2,
            'vehicle_type_id' => 1,
            'from_address' => $storeAddress . ', ' . $storeCity . ', ' . $storePostcode,
            'to_address' => $request->getDestStreet() . ', ' . $request->getDestCity() . ', ' . $request->getDestPostcode(),
            'pickup_items' => $pickupItems
        ];

        $client->setOption(CURLOPT_HTTPHEADER, $headers);
        $client->setOption(CURLOPT_RETURNTRANSFER, true);
        $client->setOption(CURLOPT_TIMEOUT, 30);
        $client->post($apiUrl . '/api/v3/rides/quotes', json_encode($data));
        
        $response = $client->getBody();
        $httpCode = $client->getStatus();
        
        if ($httpCode === 200) {
            $eva_quote = json_decode($response, true);

            return array('price' => $eva_quote['total_price'] / 100);
        }
        
        throw new \Exception('API request failed: ' . $httpCode . ' - ' . $response);
    }

    private function hasEligibleProducts(RateRequest $request)
    {
        $attributeCode = $this->attributeManager->getAttributeCode();
        
        foreach ($request->getAllItems() as $item) {
            $product = $item->getProduct();
            
            if ($product->getData($attributeCode) == 0) {
                return false;
            }
        }
        
        return true;
    }
}