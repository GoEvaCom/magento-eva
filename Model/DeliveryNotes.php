<?php
namespace GoEvaCom\Integration\Model;

use GoEvaCom\Integration\Api\DeliveryNotesInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filter\FilterManager;

class DeliveryNotes implements DeliveryNotesInterface
{
    const COLUMN_NAME = 'eva_delivery_note';
    private $cartRepository;
    private $checkoutSession;
    private $quoteIdMaskFactory;
    private $logger;
    private $filter;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        Session $checkoutSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        LoggerInterface $logger,
        FilterManager $filter,
    ) {
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->logger = $logger;
        $this->filter = $filter;
    }

    public function saveForCustomer($deliveryInstructions)
    {
        try {
            $deliveryInstructions = $this->filter->stripTags($deliveryInstructions);
            $this->logger->info('DeliveryNotes: Attempting to save for customer: ' . $deliveryInstructions);
            
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                $this->logger->error('DeliveryNotes: No active quote found in session');
                return false;
            }
            
            $this->logger->info('DeliveryNotes: Found quote ID: ' . $quote->getId());
            
            $quote->setData(self::COLUMN_NAME, $deliveryInstructions);
            $this->cartRepository->save($quote);
            
            $savedValue = $quote->getData(self::COLUMN_NAME);
            $this->logger->info('DeliveryNotes: Saved and verified value: ' . $savedValue);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('DeliveryNotes: Error saving for customer: ' . $e->getMessage());
            return false;
        }
    }

    public function saveForGuest($cartId, $deliveryInstructions)
    {
        try {
            $deliveryInstructions = $this->filter->stripTags($deliveryInstructions);
            $this->logger->info('DeliveryNotes: Attempting to save for guest cart: ' . $cartId . ', instructions: ' . $deliveryInstructions);
            
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            if (!$quoteIdMask->getQuoteId()) {
                $this->logger->error('DeliveryNotes: No quote ID found for masked ID: ' . $cartId);
                return false;
            }
            
            $realQuoteId = $quoteIdMask->getQuoteId();
            $this->logger->info('DeliveryNotes: Found real quote ID: ' . $realQuoteId);
            
            $quote = $this->cartRepository->getActive($realQuoteId);
            if (!$quote || !$quote->getId()) {
                $this->logger->error('DeliveryNotes: No active quote found for ID: ' . $realQuoteId);
                return false;
            }
            
            $quote->setData(self::COLUMN_NAME, $deliveryInstructions);
            $this->cartRepository->save($quote);
            
            $savedValue = $quote->getData(self::COLUMN_NAME);
            $this->logger->info('DeliveryNotes: Saved and verified value: ' . $savedValue);
            
            return true;
        } catch (NoSuchEntityException $e) {
            $this->logger->error('DeliveryNotes: Quote not found: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logger->error('DeliveryNotes: Error saving for guest: ' . $e->getMessage());
            return false;
        }
    }
}