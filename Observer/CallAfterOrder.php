<?php
namespace GoEvaCom\Integration\Observer;

use Exception;
use GoEvaCom\Integration\Model\Carrier\EvaDelivery;
use GoEvaCom\Integration\Model\DeliveryNotes;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class CallAfterOrder implements ObserverInterface
{
    protected $helper;
    protected $logger;
    protected $cartRepository;
    protected $phoneNumberUtil;

    public function __construct(
        \GoEvaCom\Integration\Helper\IntegrationManager $helper,
        \Psr\Log\LoggerInterface $logger,
        CartRepositoryInterface $cartRepository
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * Format phone number to international format using libphonenumber
     * 
     * @param string $phoneNumber
     * @param string $countryCode ISO 2-letter country code (e.g., 'US', 'CA', 'GB')
     * @return string
     */
    private function formatPhoneNumber($phoneNumber, $countryCode = 'CA')
    {
        if (empty($phoneNumber)) {
            return '';
        }

        try {
            $phoneNumberObject = $this->phoneNumberUtil->parse($phoneNumber, $countryCode);
            
            if ($this->phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                // Format to international format (e.g., +1 438 123 1234)
                $formattedNumber = $this->phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
                
                $this->logger->info('Eva Delivery: Successfully formatted phone number - Original: ' . $phoneNumber . ' | Formatted: ' . $formattedNumber);
                return $formattedNumber;
            } else {
                $this->logger->warning('Eva Delivery: Invalid phone number detected: ' . $phoneNumber . ' for country: ' . $countryCode);
                return $phoneNumber;
            }
        } catch (NumberParseException $e) {
            $this->logger->error('Eva Delivery: Failed to parse phone number: ' . $phoneNumber . ' for country: ' . $countryCode . ' | Error: ' . $e->getMessage());
            return $phoneNumber;
        } catch (\Exception $e) {
            $this->logger->error('Eva Delivery: Unexpected error formatting phone number: ' . $e->getMessage());
            return $phoneNumber;
        }
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $shippingMethod = $order->getShippingMethod();

        if (strpos($shippingMethod, EvaDelivery::CARRIER_CODE) === 0) {
            try {
                // Quote so we can retrieve the delivery note
                $quoteId = $order->getQuoteId();
                $this->logger->info('Eva Delivery: Processing order ' . $order->getIncrementId() . ' with quote ID: ' . $quoteId);
                
                $quote = $this->cartRepository->get($quoteId);
                $deliveryInstructions = $quote->getData(DeliveryNotes::COLUMN_NAME);

                $this->logger->info('Eva Delivery: Retrieved delivery instructions: ' . ($deliveryInstructions ?: 'NULL/EMPTY'));

                $pickupItems = [];
                foreach ($order->getAllItems() as $item) {
                    if ($item->getParentItem()) {
                        continue;
                    }
                    
                    if (!$item->getName()) {
                        continue;
                    }
                    
                    $pickupItems[] = [
                        'name' => $item->getName(),
                        'category' => 'General',
                        'weight' => (float)$item->getWeight() ?: 0,
                        'length' => (float)($item->getProduct()->getLength() ?: 0),
                        'width' => (float)($item->getProduct()->getWidth() ?: 0),
                        'height' => (float)($item->getProduct()->getHeight() ?: 0),
                        'quantity' => (int)$item->getQtyOrdered()
                    ];
                }
                
                if (empty($pickupItems)) {
                    $this->logger->error('Eva Delivery: No valid pickup items found for order ' . $order->getIncrementId());
                    return;
                }

                $rawPhoneNumber = $order->getShippingAddress()->getTelephone();
                $shippingCountryCode = $order->getShippingAddress()->getCountryId();
                $formattedPhoneNumber = $this->formatPhoneNumber($rawPhoneNumber, $shippingCountryCode);
                
                $this->logger->info('Eva Delivery: Phone number formatting - Original: ' . $rawPhoneNumber . ' | Country: ' . $shippingCountryCode . ' | Formatted: ' . $formattedPhoneNumber);

                $deliveryData = [
                    'pickup_address' => [
                        'street' => $this->helper->getStoreAddress(),
                        'city' => $this->helper->getStoreCity(),
                        'postcode' => $this->helper->getStorePostcode()
                    ],
                    'delivery_address' => [
                        'street' => $order->getShippingAddress()->getStreetLine(1),
                        'city' => $order->getShippingAddress()->getCity(),
                        'postcode' => $order->getShippingAddress()->getPostcode()
                    ],
                    'pickup_items' => $pickupItems,
                    'customer_first_name' => $order->getCustomerFirstName(),
                    'customer_last_name' => $order->getCustomerLastName(),
                    'customer_phone_number' => $formattedPhoneNumber,
                    'customer_email' => $order->getCustomerEmail(),
                    'order_note' => $deliveryInstructions ?: ''
                ];

                $booking = $this->helper->bookDelivery($order->getIncrementId(), $deliveryData);

                $ride = array_filter($booking['rides'], function($ride) use ($order){
                    return $ride['order_number'] == $order->getIncrementId();
                })[0];

                if ($ride && isset($ride['tracking_url'])) {
                    $comment = 'Eva delivery ordered with tracking url: ' . $ride['tracking_url'];
                    if ($deliveryInstructions) {
                        $comment .= ' | Delivery Instructions: ' . $deliveryInstructions;
                    }
                    
                    $order->addStatusHistoryComment($comment);
                    $order->setData('eva_tracking_url', $ride['tracking_url']);
                    $order->setData('eva_ride_id', $ride['ride_id']);
                    $order->setData(DeliveryNotes::COLUMN_NAME, $deliveryInstructions);
                    $order->save();
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to call Eva delivery: ' . $e->getMessage());

                // This is to prevent from ordering, in case the call ride fails, it will error on the checkout to prevent
                // user from ordering it.
                // Though this is dependent on the trigger for the call ride, won't be possible if trigger is status-based.
                throw new \Exception('Failed to call Eva delivery');
            }
        }
    }
}