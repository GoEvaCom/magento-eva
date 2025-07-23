<?php
namespace GoEvaCom\Integration\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ValidateToken extends Action
{
    protected $jsonFactory;
    protected $httpClientFactory;
    protected $urlBuilder;
    protected $storeManager;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CurlFactory $httpClientFactory,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
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

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            $token = $this->getRequest()->getParam('token');
            $isLive = $this->getRequest()->getParam('is_live');
            
            if (!$token) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Token is required.')
                ]);
            }
            
            $this->validateToken($token, $isLive);
            
            return $result->setData([
                'success' => true,
                'message' => __('Token is valid!')
            ]);
            
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function validateToken($token, $isLive)
    {
        if (empty($token)) {
            throw new \Exception('Token is required.');
        }

        try {
            $client = $this->httpClientFactory->create();
            
            $apiUrl = $isLive ? $this->scopeConfig->getValue('carriers/evadelivery/produrl') : $this->scopeConfig->getValue('carriers/evadelivery/stagingurl');
            
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
                'message' => 'Token is valid and webhook registered successfully!',
                'data' => $responseData,
                'webhook_url' => $webhookUrl
            ];
            
        } catch (\Exception $e) {
            
            throw new \Exception(
                sprintf('Unable to validate API token: %s', $e->getMessage())
            );
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Shipping::carriers');
    }
}
?>