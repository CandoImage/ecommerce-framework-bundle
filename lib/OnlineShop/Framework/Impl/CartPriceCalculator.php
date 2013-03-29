<?php

class OnlineShop_Framework_Impl_CartPriceCalculator implements OnlineShop_Framework_ICartPriceCalculator {

    protected $isCalculated = false;

    /**
     * @var OnlineShop_Framework_ICart
     */
    protected $cart;

    public function __construct($config, OnlineShop_Framework_ICart $cart) {
        $this->modificators = array();
        if(!empty($config->modificators) && is_object($config->modificators)) {
            foreach($config->modificators as $modificator) {
                $step = new $modificator->class();
                $this->modificators[] = $step;
            }
        }

        $this->cart = $cart;
        $this->isCalculated = false;
    }

    /**
     * @return void
     */
    public function calculate() {

        $subTotal = 0;
        $currency = null;
        foreach($this->cart->getItems() as $item) {
            if($item->getPrice()) {
                if(!$currency) {
                    $currency = $item->getPrice()->getCurrency();
                }

                if($currency->getName() != $item->getPrice()->getCurrency()->getName()) {
                    throw new OnlineShop_Framework_Exception_UnsupportedException("Different currencies within one cart are not supported");
                }

                $subTotal += $item->getTotalPrice()->getAmount();
            }
        }
        if(!$currency) {
            $currency = $this->getDefaultCurrency();
        }
        $this->subTotal = new OnlineShop_Framework_Impl_Price($subTotal, $currency);

        $modificationAmount = 0;
        $currentSubTotal = new OnlineShop_Framework_Impl_Price($subTotal, $currency);

        $this->modifications = array();
        foreach($this->modificators as $modificator) {
            $modification = $modificator->modify($currentSubTotal, $this->cart);
            if($modification !== null) {
                $this->modifications[$modificator->getName()] = $modification;
                $currentSubTotal->setAmount($currentSubTotal->getAmount() + $modification->getAmount());
            }
        }


        $this->gradTotal = $currentSubTotal;

        $this->isCalculated = true;

        // TODO: Implement calculate() method.
    }

    /**
     * @return Zend_Currency
     */
    protected function getDefaultCurrency() {
        return new Zend_Currency(Zend_Registry::get("Zend_Locale"));
    }

    /**
     * @return OnlineShop_Framework_IPrice $price
     */
    public function getGrandTotal() {
        if(!$this->isCalculated) {
            $this->calculate();
        }
        return $this->gradTotal;
    }

    /**
     * @return OnlineShop_Framework_IPrice[] $priceModification
     */
    public function getPriceModifications() {
        if(!$this->isCalculated) {
            $this->calculate();
        }

        return $this->modifications;
    }

    /**
     * @return OnlineShop_Framework_IPrice $price
     */
    public function getSubTotal() {
        if(!$this->isCalculated) {
            $this->calculate();
        }
        
        return $this->subTotal;
    }

    /**
     * @return void
     */
    public function reset() {
        $this->isCalculated = false;
    }
}
