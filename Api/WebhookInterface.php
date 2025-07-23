<?php
namespace GoEvaCom\Integration\Api;

interface WebhookInterface
{
    /**
     * Receive webhook notifications from Eva API
     *
     * @param mixed $data
     * @return array
     */
    public function receive($data);
}
?>