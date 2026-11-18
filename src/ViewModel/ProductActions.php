<?php

declare(strict_types=1);

namespace Hyva\WalkwizusMeilisearch\ViewModel;

use Hyva\Theme\ViewModel\ProductCompare;
use Hyva\Theme\ViewModel\Wishlist;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ProductActions implements ArgumentInterface
{
    public const PRODUCT_ID_PLACEHOLDER = '__product_id__';

    private const CONFIGURE_ON_PRODUCT_PAGE_TYPES = ['bundle', 'grouped'];

    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param Wishlist $wishlist
     * @param ProductCompare $productCompare
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request,
        private readonly Wishlist $wishlist,
        private readonly ProductCompare $productCompare
    ) {
    }

    /**
     * @param array $hit
     * @return bool
     */
    public function isSaleable(array $hit): bool
    {
        return (bool)($hit['stock']['is_in_stock'] ?? false);
    }

    /**
     * @param array $hit
     * @return bool
     */
    public function mustBeConfiguredOnProductPage(array $hit): bool
    {
        return in_array($hit['type_id'] ?? '', self::CONFIGURE_ON_PRODUCT_PAGE_TYPES, true);
    }

    /**
     * @param int|string $productId
     * @return string
     */
    public function getAddToCartUrl(int|string $productId): string
    {
        return $this->urlBuilder->getUrl('checkout/cart/add', [
            ActionInterface::PARAM_NAME_URL_ENCODED => '%uenc%',
            'product' => $productId,
            '_secure' => $this->request->isSecure(),
        ]);
    }

    /**
     * @return string
     */
    public function getAddToCartUrlTemplate(): string
    {
        return $this->getAddToCartUrl(self::PRODUCT_ID_PLACEHOLDER);
    }

    /**
     * @return string[]
     */
    public function getConfigureOnProductPageTypes(): array
    {
        return self::CONFIGURE_ON_PRODUCT_PAGE_TYPES;
    }

    /**
     * @return bool
     */
    public function isWishlistEnabled(): bool
    {
        return $this->wishlist->isEnabled();
    }

    /**
     * @return bool
     */
    public function isCompareEnabled(): bool
    {
        return $this->productCompare->showInProductList();
    }
}
