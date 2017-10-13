<?php
namespace Bt\Threshold\Observer;
 
use Magento\Framework\Event\ObserverInterface;
 
class ThresholdQty implements ObserverInterface
{
	protected $_objectManager;
    protected $cart;
    protected $_productFactory;

	public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\App\ResponseInterface $response,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_objectManager = $objectManager;
        $this->_productFactory = $productFactory;
        $this->cart = $cart;
        $this->_url = $url;
        $this->_response = $response;
        $this->messageManager = $messageManager;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $stockState = $objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        $productObj = $this->_productFactory->create();
        $error = false;
        $allCartProducts = $this->cart->getQuote()->getAllVisibleItems();
        foreach($allCartProducts as $product){            
            $productId = $product->getProductId();
            $productData = $product->getData();
            $products = $productObj->load($productId);
            $sku = $products->getSku();
            $cartQty = $product->getQty();
            $stockQty = $stockState->getStockQty($productId);
            $thresholdQty = $products->getThresholdQty();
            $updateQty = $stockQty - $cartQty;
            $availableQty = $stockQty - $thresholdQty;
            if($updateQty < $thresholdQty){
                $error = true;
                $message = "For SKU '".$sku."' you can not checkout with qty ".$cartQty.", please add less than equal to ".$availableQty;
                $this->messageManager->addError($message);  
            }
        }      
        if ($error == true) {
            $cartUrl = $this->_url->getUrl('checkout/cart/index');
            $this->_response->setRedirect($cartUrl)->sendResponse();
        }
    }
}