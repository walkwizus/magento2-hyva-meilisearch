<?php

declare(strict_types=1);

namespace Hyva\WalkwizusMeilisearch\ViewModel;

use Magento\Catalog\Block\Product\ListProduct as MagentoProductListBlock;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Hyva\Theme\ViewModel\ProductPage;
use Hyva\Theme\ViewModel\CurrentCategory;
use Hyva\Theme\ViewModel\BlockCache;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface as StoreConfig;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Pricing\Render;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;

class ProductListItem implements ArgumentInterface
{
    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var ProductPage
     */
    private $productViewModel;

    /**
     * @var CurrentCategory
     */
    private $currentCategory;

    /**
     * @var BlockCache
     */
    private $blockCache;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var StoreConfig
     */
    private $storeConfig;

    /**
     * @var HttpContext
     */
    private $httpContext;

    public function __construct(
        LayoutInterface $layout,
        ProductPage $productViewModel,
        CurrentCategory $currentCategory,
        BlockCache $blockCache,
        CustomerSession $customerSession,
        StoreConfig $storeConfig,
        HttpContext $httpContext
    ) {
        $this->layout = $layout;
        $this->productViewModel = $productViewModel;
        $this->currentCategory = $currentCategory;
        $this->blockCache = $blockCache;
        $this->customerSession = $customerSession;
        $this->storeConfig = $storeConfig;
        $this->httpContext = $httpContext;
    }

    public function getProductPriceHtml(
        Product $product,
        array $priceRendererBlockArgs = []
    ) {
        $priceType = FinalPrice::PRICE_CODE;

        $arguments = array_merge([
            'include_container'     => true,
            'display_minimal_price' => true,
            'list_category_page'    => true,
            'zone'                  => Render::ZONE_ITEM_LIST,
        ], $priceRendererBlockArgs);

        return $this->getPriceRendererBlock()->render($priceType, $product, $arguments);
    }

    /**
     * @return Render
     */
    private function getPriceRendererBlock()
    {
        /** @var Render $priceRender */
        $priceRender = $this->layout->getBlock('product.price.render.default');
        return $priceRender ?: $this->layout->createBlock(
            Render::class,
            'product.price.render.default',
            ['data' => ['price_render_handle' => 'catalog_product_prices']]
        );
    }

    private function getCurrencyCode(): string
    {
        return $this->productViewModel->getCurrencyData()['code'];
    }

    private function isCategoryInProductUrl(): bool
    {
        return $this->productViewModel->isProductUrlIncludesCategory() && $this->currentCategory->exists();
    }

    public function getItemCacheKeyInfo(
        Product $product,
        AbstractBlock $block,
        string $viewMode,
        string $templateType
    ): array {
        return [
            $product->getId(),
            $viewMode,
            $templateType,
            $block->getTemplate(),
            $product->getStoreId(),
            $this->getCurrencyCode(),
            (int) $block->getData('hideDetails'), // Keep for BC
            (int) $block->getData('hide_details'),
            (int) $block->getData('hide_rating_summary'),
            $this->isCategoryInProductUrl()
                ? $this->currentCategory->get()->getId()
                : '0',
            (int) $this->customerSession->getCustomerGroupId(),
            (string) $block->getData('image_display_area'),
            json_encode($product->getData('image_custom_attributes') ?? []),
            json_encode($this->httpContext->getValue('tax_rates') ?? []),
        ];
    }

    public function getItemHtmlWithRenderer(
        AbstractBlock $itemRendererBlock,
        Product $product,
        AbstractBlock $parentBlock,
        string $viewMode,
        string $templateType,
        string $imageDisplayArea,
        bool $showDescription
    ): string {
        return $this->withParentChildLayoutRelationshipExecute($parentBlock, $itemRendererBlock, [$this, 'renderItemHtml'], func_get_args());
    }

    private function renderItemHtml(
        AbstractBlock $itemRendererBlock,
        Product $product,
        AbstractBlock $parentBlock,
        string $viewMode,
        string $templateType,
        string $imageDisplayArea,
        bool $showDescription
    ): string {

        // Initialise the special price map in Magento 2.4.8 and newer (no need to check the parent block type).
        /** @var MagentoProductListBlock $parentBlock */
        $parentBlock->getProductPrice($product);

        $cacheLifetime = ($this->storeConfig->getValue('hyva_theme_catalog/developer/cache/product_list_item_block_cache_enabled') ?? true)
            ? (int) ($this->storeConfig->getValue('hyva_theme_catalog/developer/cache/product_list_item_block_cache_lifetime') ?? 3600)
            : false;

        // Careful! Temporal coupling!
        // First the values on the block need to be set, then the cache key info array can be created.
        $itemRendererBlock->setData('product', $product)
            ->setData('view_mode', $viewMode)
            ->setData('item_relation_type', $parentBlock->getData('item_relation_type'))
            ->setData('image_display_area', $imageDisplayArea)
            ->setData('show_description', $showDescription)
            ->setData('position', $parentBlock->getPositioned())
            ->setData('pos', $parentBlock->getPositioned())
            ->setData('template_type', $templateType)
            ->setData('cache_lifetime', $cacheLifetime)
            ->setData('cache_tags', $product->getIdentities())
            ->setData('hideDetails', $parentBlock->getData('hideDetails')) // Keep for BC
            ->setData('hide_details', $parentBlock->getData('hide_details'))
            ->setData('hide_rating_summary', $parentBlock->getData('hide_rating_summary'));

        $itemCacheKeyInfo = $this->getItemCacheKeyInfo($product, $itemRendererBlock, $viewMode, $templateType);
        $itemRendererBlock->setData('cache_key', $this->blockCache->hashCacheKeyInfo($itemCacheKeyInfo));

        foreach (($itemRendererBlock->getData('additional_item_renderer_processors') ?? []) as $processor) {
            if (method_exists($processor, 'beforeListItemToHtml')) {
                //phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                call_user_func([$processor, 'beforeListItemToHtml'], $itemRendererBlock, $product);
            }
        }

        return $itemRendererBlock->toHtml();
    }

    /**
     * Temporarily set the parent - child relationship in the layout structure and execute the given callback
     *
     * @param AbstractBlock $newParent
     * @param AbstractBlock $child
     * @param callable $fn
     * @param mixed[] $args
     * @return mixed
     */
    private function withParentChildLayoutRelationshipExecute(AbstractBlock $newParent, AbstractBlock $child, callable $fn, array $args = [])
    {
        $childName = $child->getNameInLayout();
        $origParentBlock = $child->getParentBlock();
        $origAlias = $this->layout->getElementAlias($childName);
        $newParent->getLayout()->setChild($newParent->getNameInLayout(), $childName, $childName);

        //phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $result = call_user_func_array($fn, $args);

        if ($origParentBlock) {
            $newParent->getLayout()->setChild($origParentBlock->getNameInLayout(), $childName, $origAlias ?: $childName);
        }

        return $result;
    }

    public function getItemHtml(
        Product $product,
        AbstractBlock $parentBlock,
        string $viewMode,
        string $templateType,
        string $imageDisplayArea,
        bool $showDescription
    ): string {
        /** @var AbstractBlock $itemRendererBlock */
        $itemRendererBlock = $parentBlock->getLayout()->getBlock('product_list_item');

        if (!$itemRendererBlock) {
            return '';
        }

        return $this->getItemHtmlWithRenderer(
            $itemRendererBlock,
            $product,
            $parentBlock,
            $viewMode,
            $templateType,
            $imageDisplayArea,
            $showDescription
        );
    }
}
