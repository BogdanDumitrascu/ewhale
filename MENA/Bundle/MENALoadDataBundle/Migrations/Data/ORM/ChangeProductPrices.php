<?php

namespace MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;

class ChangeProductPrices extends AbstractLoadProductPriceData
{

    /**
     * @param PriceList $priceList
     * @param string $productSku
     * @return ProductPrice
     */
    public function findByPriceListAndProductSku(PriceList $priceList, string $productSku)
    {

        $query = $this->container->get('doctrine.orm.entity_manager')->createQueryBuilder()
            ->select('price')
            ->from('OroPricingBundle:ProductPrice', 'price')
            ->andWhere('price.productSku = :productSku')
            ->andWhere('price.priceList = :priceList')
            ->setParameters([
                'productSku' => $productSku,
                'priceList' => $priceList
            ])
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     * @param EntityManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate('@MENALoadDataBundle/Migrations/Data/ORM/data/prices.csv');

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'r');
        $headers = fgetcsv($handler, 20000, ',');

        $priceLists = [
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

        $priceManager = $this->container->get('oro_pricing.manager.price_manager');
        while (($data = fgetcsv($handler, 20000, ',')) !== false) {
            if (trim($data[0]) != '') {
                if (sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $product = $this->getProductBySku($manager, trim($row['sku']));


                    /** @var ProductPrice $product_price */

                    if ($product != null) {

                        foreach ($priceLists as $listName => $listOptions) {
                            $priceList = $this->getPriceList($manager, $listName);

                            $product_price = $this->findByPriceListAndProductSku(
                                $priceList, trim($row['sku']));

                            if ($product_price->getPrice()->getValue() == 0) {
                                file_put_contents('/tmp/list.log', trim($row['sku']) . '-> ' . $product_price->getId() . ' $' . $product_price->getPrice()->getValue() . PHP_EOL, FILE_APPEND);

                                $product_price->getPrice()->setValue($row[$listName]);

                                file_put_contents('/tmp/list.log', trim($row['sku']) . '-> ' . $product_price->getId() . ' $' . $product_price->getPrice()->getValue() . PHP_EOL, FILE_APPEND);
                                $priceManager->persist($product_price);
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
