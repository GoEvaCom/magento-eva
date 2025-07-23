<?php
namespace GoEvaCom\Integration\Api;

interface DeliveryNotesInterface
{
    /**
     * Save delivery instructions for customer cart
     *
     * @param string $deliveryInstructions
     * @return bool
     */
    public function saveForCustomer($deliveryInstructions);

    /**
     * Save delivery instructions for guest cart
     *
     * @param string $cartId
     * @param string $deliveryInstructions
     * @return bool
     */
    public function saveForGuest($cartId, $deliveryInstructions);
}
?>