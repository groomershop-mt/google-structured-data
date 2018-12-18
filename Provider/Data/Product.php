<?php
namespace MageSuite\GoogleStructuredData\Provider\Data;

class Product
{
    const IN_STOCK = 'InStock';
    const OUT_OF_STOCK = 'OutOfStock';
    const TYPE_CONFIGURABLE = 'configurable';

    protected $product = false;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Review\Model\ReviewFactory
     */
    private $reviewFactory;
    /**
     * @var \Magento\Review\Model\ResourceModel\Review\CollectionFactory
     */
    private $reviewCollectionFactory;
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var \MageSuite\GoogleStructuredData\Repository\ProductReviews
     */
    private $productReviews;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Review\Model\ResourceModel\Review\CollectionFactory $reviewCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \MageSuite\GoogleStructuredData\Repository\ProductReviews $productReviews
    )
    {
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->reviewFactory = $reviewFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->productRepository = $productRepository;
        $this->productReviews = $productReviews;
    }

    public function getProduct()
    {
        $product = $this->registry->registry('current_product');

        if(!$product){
            return false;
        }

        $this->product = $product;

        return $this->product;
    }

    public function getProductStructuredData($product = null)
    {
        if(!$product) {
            $product = $this->getProduct();
        }

        if (!$product) {
            return [];
        }

        $product = $this->productRepository->get($product->getSku());

        $productData = $this->getBaseProductData($product);

        $offerData = $this->getOffers();

        $reviewsData = $this->productReviews->getReviewsData($productData);


        return array_merge($productData, $offerData, $reviewsData);
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @return array
     */
    public function getBaseProductData($product)
    {
        $structuredData = [
            "@context" => "http://schema.org/",
            "@type" => "Product",
            "name" => $product->getName(),
            "image" => $this->getProductImages($product),
            "description" => $product->getDescription(),
            "sku" => $product->getSku(),
            "url" => $product->getProductUrl()
        ];

        return $structuredData;
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @return array
     */
    public function getProductImages($product)
    {
        $mediaGallery = $product->getMediaGalleryImages();

        $images = [];

        foreach ($mediaGallery as $image) {
            $images[] = $image->getUrl();
        }

        return $images;
    }

    public function getOffers()
    {
        $product = $this->getProduct();

        if (!$product) {
           return [];
        }

        $data = [];
        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
        if ($product->getTypeId() == self::TYPE_CONFIGURABLE) {
            $simpleProducts = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($simpleProducts as $simpleProduct) {
                $data['offers'][] = $this->getOfferData($simpleProduct, $currency);
            }
        } else {
            $data['offers'] = $this->getOfferData($product, $currency);
        }

        return $data;
    }

    public function getOfferData($product, $currency)
    {
        return [
            "@type" => "Offer",
            "sku" => $product->getSku(),
            "price" => number_format($this->getProductPrice($product), 2),
            "priceCurrency" => $currency,
            "availability" => $product->getIsSalable() ? self::IN_STOCK : self::OUT_OF_STOCK,
            "url" => $product->getProductUrl()
        ];
    }

    public function getProductPrice($product)
    {
        return $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
    }
}