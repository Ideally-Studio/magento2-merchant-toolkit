<?php

namespace IdeallyStudio\MerchantToolkit\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Issues and validates signed preview tokens for product storefront previews.
 */
class ProductPreviewTokenManager
{
    private const DEFAULT_TTL = 3600;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param EncryptorInterface $encryptor
     * @param Json $serializer
     * @param int|null $ttl
     */
    public function __construct(
        EncryptorInterface $encryptor,
        Json $serializer,
        ?int $ttl = null
    ) {
        $this->encryptor = $encryptor;
        $this->serializer = $serializer;
        $this->ttl = $ttl ?? self::DEFAULT_TTL;
    }

    /**
     * Generate a short-lived token for the provided product/store combination.
     *
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    public function generate(int $productId, int $storeId): string
    {
        $payload = [
            'product_id' => $productId,
            'store_id' => $storeId,
            'expires_at' => time() + $this->ttl,
        ];

        $data = $this->serializer->serialize($payload);

        return $this->encryptor->encrypt($data);
    }

    /**
     * Validate the incoming token against the current product/store context.
     *
     * @param string $token
     * @param int $productId
     * @param int $storeId
     * @return bool
     */
    public function isValid(string $token, int $productId, int $storeId): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $decoded = $this->encryptor->decrypt($token);
        } catch (\Throwable $exception) {
            return false;
        }

        try {
            $payload = $this->serializer->unserialize($decoded);
        } catch (\InvalidArgumentException $exception) {
            return false;
        }

        if (!is_array($payload)) {
            return false;
        }

        $expectedIds = [
            'product_id' => $productId,
            'store_id' => $storeId,
        ];

        foreach ($expectedIds as $key => $expectedValue) {
            if (!isset($payload[$key]) || (int)$payload[$key] !== $expectedValue) {
                return false;
            }
        }

        if (!isset($payload['expires_at'])) {
            return false;
        }

        return (int)$payload['expires_at'] >= time();
    }
}
