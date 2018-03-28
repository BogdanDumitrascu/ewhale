<?php

namespace MENA\Bundle\MENALoadDataBundle\Loads;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Config\FileLocator;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadProductImages extends AbstractLoads implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $imageTypes;
    private $fileLocator;
    private $fileManager;
    private $imageResizer;
    private $attachmentManager;
    private $mediaCacheManager;
    private $imageDimensionsProvider;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->imageTypes =  array_keys($this->container->get('oro_layout.provider.image_type')->getImageTypes());
        $this->container->get('oro_layout.loader.image_filter')->load();
        $this->fileLocator = $this->container->get('file_locator');
        $this->fileManager = $this->container->get('oro_attachment.file_manager');
        $this->imageResizer = $this->container->get('oro_attachment.image_resizer');
        $this->attachmentManager = $this->container->get('oro_attachment.manager');
        $this->mediaCacheManager = $this->container->get('oro_attachment.media_cache_manager');
        $this->imageDimensionsProvider = $this->container->get('oro_product.provider.product_images_dimensions');
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function load(EntityManager $manager, OutputInterface $output, Product $product, $row)
    {
        $output->writeln('Loading product images: ' . trim($row['sku']));

        $this->addImageToProduct(
            $product,
            $manager,
            $this->fileLocator,
            preg_replace('/\s+|-/', '', $row['image']),
            $this->imageTypes,
            $output);

//        file_put_contents('/tmp/product.log', 'persisting product: ' . $product->getName() . ' ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);
        $manager->persist($product);
//        file_put_contents('/tmp/product.log', 'persisted product : ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

        $manager->flush();
       // $output->writeln('Loading product images loaded');
    }

    /**
     * sku is the directory name. All the jpg images in sku directory will be the product images
     * @param EntityManager $manager
     * @param FileLocator $locator
     * @param string $sku
     * @param array|null $types
     * @param OutputInterface $output
     * @return array
     */
    protected function getProductImageForProductSku(EntityManager $manager, FileLocator $locator, $sku, $types, OutputInterface $output)
    {
        $productImages = [];

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

            if (sizeof($files) == 0) {
                file_put_contents('/tmp/error_product.log', 'sku:' . $sku . 'not matches image does not exist' . PHP_EOL, FILE_APPEND);
                return $productImages;
            }

            foreach ($files as $file) {
                $imagePath = $locator->locate($file);

                if (is_array($imagePath)) {
                    $imagePath = current($imagePath);
                }

                if (!exif_imagetype($imagePath)) {
                    file_put_contents('/tmp/error_product.log', 'sku:' . $sku . ' image is invalid' . PHP_EOL, FILE_APPEND);
                    continue;
                }

                $image = $this->fileManager->createFileEntity($imagePath);
                $manager->persist($image);

                $productImage = new ProductImage();
                $productImage->setImage($image);
                foreach ($types as $type) {
//                    file_put_contents('/tmp/product.log', 'type: ' . $type . PHP_EOL, FILE_APPEND);
                    if ($i != 0 && $type == 'main') {
                        continue;
                    }
//                    file_put_contents('/tmp/product.log', 'sku:' . $sku . ' image ' . $imagePath . ' type: ' . $type . PHP_EOL, FILE_APPEND);
                    $productImage->addType($type);
                }

                $productImages[] = $productImage;
                $i++;
                $manager->flush();
                $output->writeln('---> Loaded image: ' . $imagePath );

            }

            unset($imagePath);
            unset($image);
            unset($files);

        } catch (\Exception $e) {
            //image not found
            $output->writeln('---> Loading image ERROR: ['.$e->getMessage() .']');
            file_put_contents('/tmp/error_product.log', 'sku:' . $sku . ' image error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        return $productImages;
    }

    /**
     * @return array
     */
    protected function getImageTypes()
    {
//        $imageTypeProvider = $this->container->get('oro_layout.provider.image_type');
//
//        return array_keys($imageTypeProvider->getImageTypes());
        return $this->imageTypes;
    }

    /**
     * @param Product $product
     * @param EntityManager $manager
     * @param FileLocator $locator
     * @param string $sku
     * @param array $allImageTypes
     * @param OutputInterface $output
     *
     * @return int number of product images
     */
    private function addImageToProduct(
        $product,
        $manager,
        $locator,
        $sku,
        $allImageTypes,
        $output
    )
    {

        $productImages = $this->getProductImageForProductSku($manager, $locator, $sku, $allImageTypes,$output);

        if (sizeof($productImages) > 0) {

            foreach ($productImages as $productImage) {
                $product->addImage($productImage);

                foreach ($this->imageDimensionsProvider->getDimensionsForProductImage($productImage) as $dimension) {
                    $image = $productImage->getImage();
                    $filterName = $dimension->getName();
                    $imagePath = $this->attachmentManager->getFilteredImageUrl($image, $filterName);

                    if ($filteredImage = $this->imageResizer->resizeImage($image, $filterName)) {
                        $this->mediaCacheManager->store($filteredImage->getContent(), $imagePath);
                    }
                }
            }
        }

        return sizeof($productImages) ;
    }
}
