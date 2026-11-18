<?php

declare(strict_types=1);

namespace Hyva\WalkwizusMeilisearch\ViewModel;

use Hyva\Theme\ViewModel\SwatchRenderer;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\ViewModel\Product\Renderer\Configurable as ConfigurableViewModel;

class ProductSwatches implements ArgumentInterface
{
    /**
     * @param SwatchRenderer $swatchRenderer
     * @param ConfigurableViewModel $configurableViewModel
     */
    public function __construct(
        private readonly SwatchRenderer $swatchRenderer,
        private readonly ConfigurableViewModel $configurableViewModel
    ) {
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'showTooltip' => (bool)$this->configurableViewModel->getShowSwatchTooltip(),
            'imageWidth' => $this->swatchRenderer->getSwatchDimension('swatch_image', 'width', 30),
            'imageHeight' => $this->swatchRenderer->getSwatchDimension('swatch_image', 'height', 30),
            'thumbWidth' => $this->swatchRenderer->getSwatchDimension('swatch_thumb', 'width', 90),
            'thumbHeight' => $this->swatchRenderer->getSwatchDimension('swatch_thumb', 'height', 90),
        ];
    }
}
