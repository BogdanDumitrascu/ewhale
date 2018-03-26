<?php

namespace MENA\Bundle\MENALoadDataBundle\Loads;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Manager\PriceManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class LoadProductPriceList extends AbstractLoads implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected $priceListsConfig = [
        'Sample Price' => [
            'discount' => 0,
            'qty' => 1,
        ],
        'Wholesale Price List 10 Items' => [
            'discount' => 0,
            'qty' => 10,
        ],
        'Wholesale Price List 50 Items' => [
            'discount' => 0,
            'qty' => 50,
        ],
        'Wholesale Price List 100 Items' => [
            'discount' => 0,
            'qty' => 100,
        ],
        'Cost Price' => [
            'discount' => 0,
            'qty' => 0,
        ],
    ];

    /**
     * @var array
     */
    protected $productUnits = [];

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function load(EntityManager $manager, OutputInterface $output, Product $product, $row)
    {

        $output->writeln('Loading price list for product: ' . trim($row['sku']));

        /** @var PriceManager $priceManager */
        $priceManager = $this->container->get('oro_pricing.manager.price_manager');

        if ($product != null) {
            $productUnit = $this->getProductUnit($manager, 'item'); //$row['unit']);

            foreach ($this->priceListsConfig as $listName => $listOptions) {
                $priceList = $this->getPriceList($manager, $listName);
                foreach ($priceList->getCurrencies() as $currency) {
                    $price_amount = $row[$listName];
                    $price = Price::create($price_amount, $currency);

                    $productPrice = new ProductPrice();
                    $productPrice
                        ->setProduct($product)
                        ->setUnit($productUnit)
                        ->setPriceList($priceList)
                        ->setQuantity($listOptions['qty'])
                        ->setPrice($price);

                    $priceManager->persist($productPrice);
                }
            }
        }

        $manager->flush();

        $output->writeln('Price list loaded');
    }

    /**
     * @param EntityManager $manager
     * @param string $code
     * @return ProductUnit|null
     */
    protected function getProductUnit(EntityManager $manager, $code)
    {
        if (!array_key_exists($code, $this->productUnits)) {
            $this->productUnits[$code] = $manager->getRepository('OroProductBundle:ProductUnit')->find($code);
        }

        return $this->productUnits[$code];
    }

    /**
     * @param EntityManager $manager
     * @param string $name
     * @return PriceList|null
     */
    protected function getPriceList(EntityManager $manager, $name)
    {
        if (!array_key_exists($name, $this->priceLists)) {
            $this->priceLists[$name] = $manager->getRepository('OroPricingBundle:PriceList')
                ->findOneBy(['name' => $name]);
        }

        return $this->priceLists[$name];
    }
}
