<?php

namespace GoEvaCom\Integration\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class TokenValidator extends Value
{
    protected $httpClientFactory;
    protected $scopeConfig;
    protected $storeManager;
    protected $urlBuilder;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        CurlFactory $httpClientFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->scopeConfig = $config;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        $type = $this->getField();
        
        if (!empty($value)) {
            $this->validateToken($value, $type);
        }
        
        return parent::beforeSave();
    }

    private function getWebhookUrl()
    {
        try {
            $store = $this->storeManager->getStore();
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            
            $webhookUrl = $baseUrl . 'rest/V1/evadelivery/webhook';
            
            return $webhookUrl;
            
        } catch (\Exception $e) {
            throw new \Exception(
                sprintf(
                'Unable to generate webhook URL: %s', $e->getMessage()
                )
            );
        }
    }

    public function validateToken($token, $type)
    {
        if (empty($token)) {
            throw new \Exception('Token is required.');
        }

        try {
            $client = $this->httpClientFactory->create();
            
            $is_live = $type == "prodtoken";
            $apiUrl = $is_live ? $this->scopeConfig->getValue('carriers/evadelivery/produrl') : $this->scopeConfig->getValue('carriers/evadelivery/stagingurl');
            
            $webhookUrl = $this->getWebhookUrl();
            
            $headers = [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ];

            $payload = [
                'url' => $webhookUrl
            ];

            $client->setOption(CURLOPT_HTTPHEADER, $headers);
            $client->setOption(CURLOPT_RETURNTRANSFER, true);
            $client->setOption(CURLOPT_TIMEOUT, 10);
            
            $client->post($apiUrl . '/api/v3/partners/application/webhooks', json_encode($payload));
            
            $httpCode = $client->getStatus();
            $response = $client->getBody();
            
            if ($httpCode !== 200) {
                throw new \Exception(
                    sprintf('Failed to register webhook. HTTP Status: %s. Response: %s', $httpCode, $response)
                );
            }
            
            $responseData = json_decode($response, true);
            
            if (!$responseData) {
                throw new \Exception(
                    'Invalid response from webhook registration.'
                );
            }
            
            return [
                'valid' => true,
                'message' => __('Token is valid and webhook registered successfully!'),
                'data' => $responseData,
                'webhook_url' => $webhookUrl
            ];
            
        } catch (\Exception $e) {
            
            throw new \Exception(
                sprintf(
                'Unable to validate API token: %s', $e->getMessage()
                )
            );
        }
    }
}
?>