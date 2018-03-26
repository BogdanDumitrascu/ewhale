<?php

namespace MENA\Bundle\MENALoadDataBundle\Command;

use Doctrine\ORM\EntityManager;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Config\FileLocator;

use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadProductImageCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const STATUS_SUCCESS = 0;
    const COMMAND_NAME   = 'oro:loadproductimages';
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
            ->setDescription('load product images');
    }

    /*
     * Transitions: Unassigned to Assigned
     * Condition:   Owner role is Full-timer
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Load product images');

      //  $i = $input->getArgument('product_line_number');
        $helper = new LoadProductHelpers();
        $line_count = $helper->fileCount();

        $i =0;
        while( $i< $line_count ) {

            $this->load($this->container->get('doctrine.orm.entity_manager'), $i, $output,$line_count);
            $output->writeln('All good!');
            $i++;
        }
        return self::STATUS_SUCCESS;
    }

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var string
     */
    const ENUM_CODE_INVENTORY_STATUS = 'prod_inventory_status';


    /**
     * @var array
     */
    protected $productUnits = array();

    private function fileCount()
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
     *
     * name
     * description
     * specifications
     * information
     * product_family_code
     * type
     * featured
     * precision
     * unit
     * new_arrival
     * brand_id
     * page_template
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function load(EntityManager $manager,$i, OutputInterface $output, $line_count)
    {

        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate(self::PRODUCT_FILE);

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }


        $handler = fopen($filePath, 'rb');
        $headers = fgetcsv($handler, self::MAX_LINE, ',');


        $allImageTypes = $this->getImageTypes();
        $this->container->get('oro_layout.loader.image_filter')->load();

        $loadedProducts = array();
        $j = 0;

        $num = $line_count;
        while (($data = fgetcsv($handler, self::MAX_LINE, ',')) !== false) {

            if ($j == $i && trim($data[0])!='') {
                if ( sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $output->writeln($i.' of '. $num. ', completed:'.round($i/$num*100,2) .'% product: '. trim($row['sku']));

                    $product = $this->getProductBySku($manager, trim($row['sku']));

                    if ($product != null) {
                        $this->addImageToProduct($product, $manager, $locator, preg_replace('/\s+|-/', '', $row['image']), $allImageTypes);
                        file_put_contents('/tmp/product.log', 'persisting product: ' . $product->getName() . ' ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                        $manager->persist($product);
                        file_put_contents('/tmp/product.log', 'persisted product : ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                        $loadedProducts[] = $product;
                    } else {
                        file_put_contents('/tmp/error_product.log', trim($row['sku']) . PHP_EOL, FILE_APPEND);
                    }
                }
            }
            $j++;
        }

        $manager->flush();
        $this->createSlugs($loadedProducts, $manager);
        fclose($handler);

    }


    /**
     * @param array|Product[] $products
     * @param EntityManager $manager
     */
    private function createSlugs(array $products, EntityManager $manager)
    {
        $slugRedirectGenerator = $this->container->get('oro_redirect.generator.slug_entity');

        foreach ($products as $product) {
            $slugRedirectGenerator->generate($product, true);
        }

        $this->container->get('oro_redirect.url_storage_cache')->flush();
        $manager->flush();
    }

    /**
     * @param EntityManager $manager
     * @return AbstractEnumValue|object
     *
     * @throws \InvalidArgumentException
     */
    protected function getOutOfStockInventoryStatus(EntityManager $manager)
    {
        $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName(self::ENUM_CODE_INVENTORY_STATUS);

        return $manager->getRepository($inventoryStatusClassName)->findOneBy(array(
            'id' => Product::INVENTORY_STATUS_OUT_OF_STOCK
        ));
    }

    /**
     * sku is the directory name. All the jpg images in sku directory will be the product images
     * @param EntityManager $manager
     * @param FileLocator $locator
     * @param string $sku
     * @param array|null $types
     * @return array
     */
    protected function getProductImageForProductSku(EntityManager $manager, FileLocator $locator, $sku, $types)
    {
        $productImages = array();

        try {
            $path = $locator->locate(sprintf('@MENALoadDataBundle/Migrations/Data/ORM/images/products/%s/', $sku));
            if (is_array($path)) {
                $path = current($path);
            }

            $files = glob($path . '*.jpg');
            if (sizeof($files) == 0) {
                $files = glob($path . '*.png');
            }
            if (sizeof($files) == 0) {
                $files = glob($path . '*.JPG');
            }
            if (sizeof($files) == 0) {
                $files = glob($path . '*.PNG');
            }
            if (sizeof($files) == 0) {
                $files = glob($path . '*.jpeg');
            }
            if (sizeof($files) == 0) {
                $files = glob($path . '*.JPEG');
            }
//            if (sizeof($files) == 0) {
//                $files = glob($path . '*.tif');
//            }
//            if (sizeof($files) == 0) {
//                $files = glob($path . '*.TIF');
//            }
            $i = 0;

            if ( sizeof ($files) == 0 ){
                file_put_contents('/tmp/error_product.log', 'sku:' . $sku . 'not matches image does not exist'. PHP_EOL, FILE_APPEND);
                return $productImages;
            }

            foreach ($files as $file) {
                $imagePath = $locator->locate($file);

                if (is_array($imagePath)) {
                    $imagePath = current($imagePath);
                }

                if(!exif_imagetype($imagePath)) {
                    file_put_contents('/tmp/error_product.log', 'sku:' . $sku . ' image is invalid'. PHP_EOL, FILE_APPEND);
                    continue;
                }
                $fileManager = $this->container->get('oro_attachment.file_manager');
                $image = $fileManager->createFileEntity($imagePath);
                $manager->persist($image);


                $productImage = new ProductImage();
                $productImage->setImage($image);
                foreach ($types as $type) {
                    file_put_contents('/tmp/product.log', 'type: ' . $type . PHP_EOL, FILE_APPEND);
                    if ($i != 0 && $type == 'main') {
                        continue;
                    }
                    file_put_contents('/tmp/product.log', 'sku:' . $sku . ' image ' . $imagePath . ' type: ' . $type . PHP_EOL, FILE_APPEND);

                    $productImage->addType($type);
                }

                $productImages[] = $productImage;
                $i++;
                $manager->flush();
            }

        } catch (\Exception $e) {
            //image not found
            file_put_contents('/tmp/error_product.log', 'sku:' . $sku . ' image error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        return $productImages;
    }

    /**
     * @return array
     */
    protected function getImageTypes()
    {
        $imageTypeProvider = $this->container->get('oro_layout.provider.image_type');

        return array_keys($imageTypeProvider->getImageTypes());
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
     * @param Product $product
     * @param EntityManager $manager
     * @param FileLocator $locator
     * @param string $sku
     * @param array $allImageTypes
     */
    private function addImageToProduct(
        $product,
        $manager,
        $locator,
        $sku,
        $allImageTypes
    )
    {
        $imageResizer = $this->container->get('oro_attachment.image_resizer');
        $attachmentManager = $this->container->get('oro_attachment.manager');
        $mediaCacheManager = $this->container->get('oro_attachment.media_cache_manager');
        $productImages = $this->getProductImageForProductSku($manager, $locator, $sku, $allImageTypes);
        $imageDimensionsProvider = $this->container->get('oro_product.provider.product_images_dimensions');
        if (sizeof($productImages) > 0) {

            foreach ($productImages as $productImage) {
                $product->addImage($productImage);

                foreach ($imageDimensionsProvider->getDimensionsForProductImage($productImage) as $dimension) {
                    $image = $productImage->getImage();
                    $filterName = $dimension->getName();
                    $imagePath = $attachmentManager->getFilteredImageUrl($image, $filterName);

                    if ($filteredImage = $imageResizer->resizeImage($image, $filterName)) {
                        $mediaCacheManager->store($filteredImage->getContent(), $imagePath);
                    }
                }
            }
        }
    }

    /**
     * @param EntityManager $manager
     * @param string $sku
     *
     * @return Product|null
     */
    protected function getProductBySku(EntityManager $manager, $sku)
    {
        return $this->getProductRepository($manager)->findOneBy(array('sku' => $sku));
    }

    /**
     * @param EntityManager $manager
     *
     * @return EntityRepository
     */
    protected function getProductRepository(EntityManager $manager)
    {
        if (!$this->productRepository) {
            $this->productRepository = $manager->getRepository('OroProductBundle:Product');
        }

        return $this->productRepository;
    }
}
