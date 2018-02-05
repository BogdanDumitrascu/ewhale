<?php

namespace MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;

use Doctrine\ORM\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Extend\Entity\EV_Prod_Inventory_Status;
use Oro\Bundle\EntityExtendBundle\Entity\Repository\EnumValueRepository;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\ProductBundle\Entity\Brand;
use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Oro\Bundle\UserBundle\DataFixtures\UserUtilityTrait;

class LoadProductData extends AbstractFixture implements
    ContainerAwareInterface, DependentFixtureInterface
{
    use UserUtilityTrait;

    /**
     * @var string
     */
    const ENUM_CODE_INVENTORY_STATUS = 'prod_inventory_status';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $productUnits = array();

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getDependencies()
    {
        return array(
            LoadCategoryData::class
        );
    }

    //https://mathiasbynens.be/notes/mysql-utf8mb4#utf8-to-utf8mb4
    public function translateChars($string){
        return preg_replace('/[[:^print:]]/', "", $string); //preg_replace("/[^\\x00-\\xFFFF]/", "", $string); //mb_convert_encoding($string, 'UTF-8', 'UTF-8');; //iconv('UTF-8', "UTF-8//IGNORE", utf8_encode($string));
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
    public function load(ObjectManager $manager)
    {
        /** @var EntityManager $manager */
        $user = $this->getFirstUser($manager);
        $businessUnit = $user->getOwner();
        $organization = $user->getOrganization();

        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate('@MENALoadDataBundle/Migrations/Data/ORM/data/products.csv');

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'rb');
        $headers = fgetcsv($handler, 2000, ',');

        $outOfStockStatus = $this->getOutOfStockInventoryStatus($manager);

        $allImageTypes = $this->getImageTypes();
        $this->container->get('oro_layout.loader.image_filter')->load();

        $slugGenerator = $this->container->get('oro_entity_config.slug.generator');
        $loadedProducts = array();

        $AttributeFamily = $this->getAttributeFamily($manager, 'default_family');

        while (($data = fgetcsv($handler, 2000, ',')) !== false) {
            if (sizeof($headers) == sizeof(array_values($data))) {
                $row = array_combine($headers, array_values($data));

                $name = new LocalizedFallbackValue();
                $name->setString($this->translateChars($row['name']));

                $text = '<p  class="product-view-desc">' . $row['description'] . '</p>';

                $description = new LocalizedFallbackValue();
                $description->setText($this->translateChars(nl2br($text)));
                $shortDescription = new LocalizedFallbackValue();
                $shortDescription->setText($this->translateChars($row['description']));
                $brand = $this->getBrand($manager, trim($row['brand']), $organization, $businessUnit);
                $inventory_status = $manager->getRepository(EV_Prod_Inventory_Status::class)->find('in_stock');
                $product = new Product();
                $product->setOwner($businessUnit)
                    ->setOrganization($organization)
                    ->setAttributeFamily($AttributeFamily)
                    ->setSku(trim($row['sku']))
                    ->setInventoryStatus($outOfStockStatus)
                    ->setStatus(Product::STATUS_ENABLED)
                    ->setInventoryStatus($inventory_status)
                    ->addName($name)
                    ->addDescription($description)
                    ->addShortDescription($shortDescription)
                    ->setType('simple')//$row['type'])
                    ->setFeatured($row['featured'])
                    ->setNewArrival($row['new_arrival'])
                    ->setBrand($brand);
                // $manager->persist($product);
                $this->setPageTemplate($product, $row);

                $slugPrototype = new LocalizedFallbackValue();
                $slugPrototype->setString($slugGenerator->slugify($row['name']));
                $product->addSlugPrototype($slugPrototype);

                $productUnit = $this->getProductUnit($manager, 'item'); //$row['unit']);

                $productUnitPrecision = new ProductUnitPrecision();
                $productUnitPrecision
                    ->setProduct($product)
                    ->setUnit($productUnit)
                    ->setPrecision(0)//(int)$row['precision'])
                    ->setConversionRate(1)
                    ->setSell(true);

                $product->setPrimaryUnitPrecision($productUnitPrecision);

                //  $manager->persist($product);

                //   $this->addImageToProduct($product, $manager, $locator, $row['image'], $allImageTypes);
                file_put_contents('/tmp/product.log', 'persisting product: ' . $product->getName() . ' ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                try {
                    $manager->persist($product);
                } catch (ORMInvalidArgumentException $e) {
                    try {
                        foreach ($product->getImages() as $productImage) {
                            $product->removeImage($productImage);
                        }
                    } catch (ORMException $e) {
                        file_put_contents('/tmp/error_product.log', 'ORMException' . PHP_EOL, FILE_APPEND);
                    }
                    file_put_contents('/tmp/error_product.log', trim($row['sku']) . PHP_EOL, FILE_APPEND);
                } catch (ORMException $e) {
                    file_put_contents('/tmp/error_product.log', 'ORMException' . PHP_EOL, FILE_APPEND);
                    file_put_contents('/tmp/error_product.log', trim($row['sku']) . PHP_EOL, FILE_APPEND);
                }

                file_put_contents('/tmp/product.log', 'persisted product : ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                $loadedProducts[] = $product;
            }
        }

            $manager->flush();
            $this->createSlugs($loadedProducts, $manager);
            fclose($handler);

    }

    /**
     * @param ObjectManager $manager
     * @param string $brand_name
     * @param OrganizationInterface $organization
     * @param BusinessUnit $businessUnit
     * @return Brand
     */
    private function getBrand(ObjectManager $manager,
                              $brand_name,
                              $organization,
                              $businessUnit){

        $brand = $this->findBrandByName($manager,$brand_name);

        if(!isset($brand)){
            $brand = new Brand();
            $brand->addName((new LocalizedFallbackValue())->setString($brand_name));
            $brand->setStatus('enabled');
            $brand->setOwner($businessUnit);
            $brand->setOrganization($organization);

            $manager->persist($brand);
            $manager->flush();
        }

        return $brand;
    }

    /**
     * @param array|Product[] $products
     * @param ObjectManager $manager
     */
    private function createSlugs(array $products, ObjectManager $manager)
    {
        $slugRedirectGenerator = $this->container->get('oro_redirect.generator.slug_entity');

        foreach ($products as $product) {
            $slugRedirectGenerator->generate($product, true);
        }

        $this->container->get('oro_redirect.url_storage_cache')->flush();
        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @return AbstractEnumValue|object
     *
     * @throws \InvalidArgumentException
     */
    protected function getOutOfStockInventoryStatus(ObjectManager $manager)
    {
        $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName(self::ENUM_CODE_INVENTORY_STATUS);

        return $manager->getRepository($inventoryStatusClassName)->findOneBy(array(
            'id' => Product::INVENTORY_STATUS_OUT_OF_STOCK
        ));
    }

    /**
     * sku is the directory name. All the jpg images in sku directory will be the product images
     * @param ObjectManager $manager
     * @param FileLocator $locator
     * @param string $sku
     * @param array|null $types
     * @return array
     */
    protected function getProductImageForProductSku(ObjectManager $manager, FileLocator $locator, $sku, $types)
    {
        $productImages = array();

        try {
            $path = $locator->locate(sprintf('@MENALoadDataBundle/Migrations/Data/ORM/images/products/%s/', $sku));
            if (is_array($path)) {
                $path = current($path);
            }

            $files = glob($path . '*.jpg');
            if( sizeof($files) == 0){
                $files = glob($path . '*.png');
            }
            $i = 0;
            foreach ($files as $file) {
                $imagePath = $locator->locate($file);

                if (is_array($imagePath)) {
                    $imagePath = current($imagePath);
                }

                $fileManager = $this->container->get('oro_attachment.file_manager');
                $image = $fileManager->createFileEntity($imagePath);
                $manager->persist($image);
                $manager->flush();

                $productImage = new ProductImage();
                $productImage->setImage($image);
                foreach ($types as $type) {
                    file_put_contents('/tmp/product.log', 'type: '.$type.PHP_EOL, FILE_APPEND);
                    if( $i != 0 && $type == 'main'){
                        continue;
                    }
                    file_put_contents('/tmp/product.log', 'sku:'. $sku. ' image '. $imagePath.' type: '.$type.PHP_EOL, FILE_APPEND);

                    $productImage->addType($type);
                }
                $productImages[]=$productImage;
                $i++;
            }

        } catch (\Exception $e) {
            //image not found
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
     * @param ObjectManager $manager
     * @param string $attribute_code
     * @return AttributeFamily|null
     */
    private function getAttributeFamily(ObjectManager $manager, $attribute_code)
    {
        $familyRepository = $manager->getRepository(AttributeFamily::class);

        return $familyRepository->findOneBy(array('code' => $attribute_code));
    }

    /**
     * @param Product $product
     * @param ObjectManager $manager
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
        if (sizeof($productImages)>0) {

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
     * @param Product $product
     * @param array $row
     * @return LoadProductData
     */
    private function setPageTemplate(Product $product, array $row)
    {
        if (!empty($row['page_template'])) {
            $entityFallbackValue = new EntityFieldFallbackValue();
            $entityFallbackValue->setArrayValue([ProductType::PAGE_TEMPLATE_ROUTE_NAME => $row['page_template']]);

            $product->setPageTemplate($entityFallbackValue);
        }

        return $this;
    }

    /**
     * Search for the Brand entity by the brand name. Return Brand if found else null
     * @param ObjectManager $manager
     * @param string $brand_name
     * @return null|Brand
     */
    private function findBrandByName(ObjectManager $manager, $brand_name){

        $brands = $manager->getRepository('OroProductBundle:Brand')->findAll();

        foreach ($brands as $brand) {
            if( $brand_name == $brand->getName()->getString())
                return $brand;
        }
        return null;
    }

    private function setShoesAttributes($product, $row){
        /*
             * Custom fields for Shoes attribute family
             */
        if ( array_key_exists('weight',$row) ) {
            $product->setWeight($row['weight']);
        }

        if ( array_key_exists('style',$row) ) {
            $product->setStyle($row['style']);
        }

        if ( array_key_exists('supplier_name',$row) ) {
            $product->setSupplierName($row['supplier_name']);
        }

        if ( array_key_exists('supplier_sku',$row) ) {
            $product->setSupplierSku($row['supplier_sku']);
        }

        if ( array_key_exists('souq_sku',$row) ) {
            $product->setSouqSku($row['souq_sku']);
        }

        if ( array_key_exists('supplier_id ',$row) ) {
            $product->setSupplierId($row['supplier_id']);
        }

        if ( array_key_exists('souq_ean',$row) ) {
            $product->setSouqEan($row['souq_ean']);
        }

        if ( array_key_exists('souq_account_name ',$row) ) {
            $product->setSouqAccountName($row['souq_account_name']);
        }

        if ( array_key_exists('colors',$row) ) {
            $className = ExtendHelper::buildEnumValueClassName('Product_Colors_Fe29f05a');
            /** @var EnumValueRepository $enumRepo */
            $enumRepo = $manager->getRepository($className);
            $product->setColors($enumRepo->find($row['colors']));
        }

        if ( array_key_exists('occasion',$row) ) {
            $className = ExtendHelper::buildEnumValueClassName('Product_Occasion_7a4dc039');
            /** @var EnumValueRepository $enumRepo */
            $enumRepo = $manager->getRepository($className);
            $product->setOccasion($enumRepo->find($row['occasion']));
        }

        if ( array_key_exists('targeted_group',$row) ) {
            $className = ExtendHelper::buildEnumValueClassName('Product_Targeted_Group_A4b5cd4c');
            /** @var EnumValueRepository $enumRepo */
            $enumRepo = $manager->getRepository($className);
            $product->setTargetedGroup($enumRepo->find($row['targeted_group']));
        }

        if ( array_key_exists('shoes_eu_size',$row) ) {
            $className = ExtendHelper::buildEnumValueClassName('Product_Shoes_Eu_Size_F169111');
            /** @var EnumValueRepository $enumRepo */
            $enumRepo = $manager->getRepository($className);
            $product->setShoesEuSize($enumRepo->find($row['shoes_eu_size']));
        }

        return $product;

    }

    private function setCoffeeAttributes($product, $row){
        /*
             * Custom fields for fmcg_coffee attribute family
             */

        if ( array_key_exists('size',$row) ) {
            $product->setSize($row['size']);
        }

        if ( array_key_exists('caffeine_type',$row) ) {
            $classNameCaffeineType = ExtendHelper::buildEnumValueClassName('Product_Caffeine_Type_E30b870a');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoCaffeineType = $manager->getRepository($classNameCaffeineType);
            $product->setCaffeineType($enumRepoCaffeineType->find($row['caffeine_type']));
        }

        if ( array_key_exists('coffee_format',$row) ) {
            $classNameCoffeeFormat = ExtendHelper::buildEnumValueClassName('Product_Coffee_Format_73197207');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoCoffeeFormat = $manager->getRepository($classNameCoffeeFormat);
            $product->setCoffeeFormat($enumRepoCoffeeFormat->find($row['coffee_format']));
        }

        if ( array_key_exists('packaging',$row) ) {
            $classNamePackaging = ExtendHelper::buildEnumValueClassName('Product_Packaging_B2ef3ac5');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoPackaging = $manager->getRepository($classNamePackaging);
            $product->setPackaging($enumRepoPackaging->find($row['packaging']));
        }

        if ( array_key_exists('number_of_capsules',$row) ) {
            $product->setNumberCapsules($row['number_of_capsules']);
        }

        if ( array_key_exists('flavor',$row) ) {
            $product->setFlavor($row['flavor']);
        }

        if ( array_key_exists('intensity',$row) ) {
            $classNameIntensity = ExtendHelper::buildEnumValueClassName('Product_Intensity_E4d68fd2');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoIntensity = $manager->getRepository($classNameIntensity);
            $product->setIntensity($enumRepoIntensity->find($row['intensity']));
        }

        if ( array_key_exists('made_in',$row) ) {
            $classNameMadeIn = ExtendHelper::buildEnumValueClassName('Product_Made_In_4608ac28');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoMadeIn = $manager->getRepository($classNameMadeIn);
            $product->setMadeIn($enumRepoMadeIn->find($row['made_in']));
        }

        if ( array_key_exists('coffee_type',$row) ) {
            $classNameCoffeeType = ExtendHelper::buildEnumValueClassName('Product_Coffee_Type_650a85d0');
            /** @var EnumValueRepository $enumRepo */
            $enumRepoCoffeeType = $manager->getRepository($classNameCoffeeType);
            $product->setCoffeeType($enumRepoCoffeeType->find($row['coffee_type']));
        }

        return $product;
    }
}
