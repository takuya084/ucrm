<?php
namespace Tests\Support;
final class DenyStripeHttpClient implements \Stripe\HttpClient\ClientInterface
{
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        throw new \LogicException('stripe_transport_denied_in_tests');
    }
}
