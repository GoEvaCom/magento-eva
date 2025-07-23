<?php
namespace GoEvaCom\Integration\Model;

use GoEvaCom\Integration\Api\WebhookInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Webhook implements WebhookInterface
{
    protected $orderRepository;
    protected $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function receive($data)
    {
        try {
            $this->logger->info('Eva Webhook received: ' . json_encode($data));
            
            $webhookData = json_decode($data, true);
            
            if (!$webhookData || !isset($webhookData['tracking_id'])) {
                throw new \Exception('Invalid webhook data received');
            }
            
            $trackingId = $webhookData['tracking_id'];
            $eventId = $webhookData['event_id'] ?? null;
            $eventDescription = $webhookData['event_description'] ?? null;
            
            $this->logger->info('Processing webhook for tracking_id: ' . $trackingId);
            
            $order = $this->findOrderByTrackingId($trackingId);
            
            if (!$order) {
                $this->logger->warning('Order not found for tracking_id: ' . $trackingId);
                return [
                    'success' => false,
                    'message' => 'Order not found'
                ];
            }
            
            $this->processWebhookEvent($order, $eventId, $eventDescription, $webhookData);
            
            return [
                'success' => true,
                'message' => 'Webhook processed successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Eva Webhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function findOrderByTrackingId($trackingId)
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('eva_ride_id', $trackingId)
                ->create();
            
            $orders = $this->orderRepository->getList($searchCriteria);
            
            if ($orders->getTotalCount() > 0) {
                return $orders->getFirstItem();
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error finding order by tracking ID: ' . $e->getMessage());
            return null;
        }
    }

    private function processWebhookEvent($order, $eventId, $eventDescription, $webhookData)
    {
        $comment = sprintf(
            'Eva Delivery Update - Event: %s (%s)',
            $eventDescription ?: 'Unknown',
            $eventId ?: 'N/A'
        );
        
        $order->addCommentToStatusHistory($comment);
        
        switch ($eventId) {
            case 'pickup_confirmed':
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus('processing');
                break;
                
            case 'in_transit':
                break;
                
            case 'delivered':
                $order->setState(Order::STATE_COMPLETE);
                $order->setStatus('complete');
                break;
                
            case 'delivery_failed':
                $order->addCommentToStatusHistory(
                    'Delivery failed - ' . ($eventDescription ?: 'No details provided')
                );
                break;
                
            default:
                $this->logger->info('Unknown Eva webhook event: ' . $eventId);
                break;
        }
        
        $this->orderRepository->save($order);
        $this->logger->info('Order updated successfully for tracking_id: ' . $webhookData['tracking_id']);
    }
}
?>