<?php

namespace MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Manager\PriceManager;

class LoadProductPriceData extends AbstractLoadProductPriceData implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {//'MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM\
        return array(LoadProductData::class);
    }

    /**
     * {@inheritdoc}
     * @param EntityManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate('@MENALoadDataBundle/Migrations/Data/ORM/data/products.csv');

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'r');
        $headers = fgetcsv($handler, 2000, ',');

        $priceLists = [
            'Sample Price' => [
                'discount' => 0,
                'qty'=> 1,
            ],
            'Wholesale Price List 10 Items' => [
                'discount' => 0,
                'qty'=> 10,
            ],
            'Wholesale Price List 50 Items' => [
                'discount' => 0,
                'qty'=> 50,
            ],
            'Wholesale Price List 100 Items' => [
                'discount' => 0,
                'qty'=> 100,
            ],
            'Cost Price' => [
                'discount' => 0,
                'qty'=> 0,
            ],
        ];

        $priceManager = $this->container->get('oro_pricing.manager.price_manager');
        while (($data = fgetcsv($handler, 1000, ',')) !== false) {
            if (trim($data[0])!='') {
                if ( sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $product = $this->getProductBySku($manager, trim($row['sku']));
                    if ($product != null) {
                        $productUnit = $this->getProductUnit($manager, 'item'); //$row['unit']);

                        foreach ($priceLists as $listName => $listOptions) {
                            $priceList = $this->getPriceList($manager, $listName);
                            foreach ($priceList->getCurrencies() as $currency) {

                                $price = Price::create($row[$listName], $currency);

                                $productPrice = new ProductPrice();
                                $productPrice
                                    ->setProduct($product)
                                    ->setUnit($productUnit)
                                    ->setPriceList($priceList)
                                    ->setQuantity($listOptions['qty'])
                                    ->setPrice($price);

                                $priceManager->persist($productPrice);

                                //$this->createPriceTiers($priceManager, $productPrice, $price);
                            }
                        }
                    }
                }
            }
        }

        fclose($handler);

        $manager->flush();
    }

    /**
     * @param PriceManager $priceManager
     * @param ProductPrice $productPrice
     * @param Price $unitPrice
     */
    protected function createPriceTiers(
        PriceManager $priceManager,
        ProductPrice $productPrice,
        Price $unitPrice
    ) {
        $tiers = [
            10  => 0.05,
            20  => 0.10,
            50  => 0.15,
            100 => 0.20,
        ];

        foreach ($tiers as $qty => $discount) {
            $price = clone $productPrice;
            $currentPrice = clone $unitPrice;
            $price
                ->setQuantity($qty)
                ->setPrice($currentPrice->setValue(round($unitPrice->getValue() * (1 - $discount), 2)));
            $priceManager->persist($price);
        }
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
