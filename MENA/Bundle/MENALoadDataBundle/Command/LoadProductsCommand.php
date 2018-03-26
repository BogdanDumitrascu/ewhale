<?php

namespace MENA\Bundle\MENALoadDataBundle\Command;

use MENA\Bundle\MENALoadDataBundle\Loads\LoadProductData;
use MENA\Bundle\MENALoadDataBundle\Loads\LoadProductImages;
use MENA\Bundle\MENALoadDataBundle\Loads\LoadProductPriceList;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadProductsCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const STATUS_SUCCESS = 0;
    const COMMAND_NAME   = 'oro:load_products';
    const MAX_LINE = 20000;
    const PRODUCT_FILE = '@MENALoadDataBundle/Migrations/Data/ORM/data/products.csv';

    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('load products');
    }

    public function fileCount()
    {
        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate(self::PRODUCT_FILE);

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'rb');
        $j = 0;
        while (($data = fgetcsv($handler, self::MAX_LINE, ',')) !== false) {
            $j++;
        }
        return $j-1;
    }

   /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Load products');

        $productLoader = new LoadProductData($this->container);
        $priceListLoader = new LoadProductPriceList($this->container);
        $imageLoader = new LoadProductImages($this->container);

        $line_count = $this->fileCount();

        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate(self::PRODUCT_FILE);

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'rb');
        $headers = fgetcsv($handler, self::MAX_LINE, ',');

        $i = 1;

        $num = $line_count;

        while (($data = fgetcsv($handler, self::MAX_LINE, ',')) !== false) {
            if (trim($data[0]) != '' &&  (sizeof($headers) == sizeof(array_values($data)))) {
                $row = array_combine($headers, array_values($data));
                /** @var Product $product */
                $product = $productLoader->load($this->container->get('doctrine.orm.entity_manager'), $output, new Product() ,$row);
                $priceListLoader->load($this->container->get('doctrine.orm.entity_manager'), $output,$product,$row);
                $imageLoader->load($this->container->get('doctrine.orm.entity_manager'), $output,$product,$row);

                $output->writeln($i . ' of ' . $num . ', completed:' . round($i / $num * 100, 2) . '% product: ' . trim($row['sku']));
                $i++;
                if( $i==2)
                    break;
            }
        }
        return self::STATUS_SUCCESS;
    }
}
