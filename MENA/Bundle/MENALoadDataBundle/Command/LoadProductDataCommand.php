<?php

namespace MENA\Bundle\MENALoadDataBundle\Command;

use ClassesWithParents\E;
use Doctrine\ORM\EntityManager;
use Extend\Entity\EV_Prod_Inventory_Status;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMInvalidArgumentException;
use MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM\LoadProductData;
use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\ProductBundle\Entity\Brand;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
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
use MENA\Bundle\MENALoadDataBundle\Command\LoadProductHelpers;

class LoadProductDataCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const STATUS_SUCCESS = 0;
    const COMMAND_NAME   = 'oro:loadproductdata';
    const MAX_LINE = LoadProductHelpers::MAX_LINE;
    const PRODUCT_FILE = LoadProductHelpers::PRODUCT_FILE;

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
            ->setDescription('load product data');
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

    //https://mathiasbynens.be/notes/mysql-utf8mb4#utf8-to-utf8mb4
    public function translateChars($string)
    {
        return preg_replace('/[[:^print:]]/', "", $string); //preg_replace("/[^\\x00-\\xFFFF]/", "", $string); //mb_convert_encoding($string, 'UTF-8', 'UTF-8');; //iconv('UTF-8', "UTF-8//IGNORE", utf8_encode($string));
    }

    /**
     * @param EntityManager $manager
     * @return User
     * @throws \LogicException
     */
    public function getFirstUser(EntityManager $manager)
    {
        $users = $manager->getRepository('OroUserBundle:User')->findBy([], ['id' => 'ASC'], 1);
        if (!$users) {
            throw new \LogicException('There are no users in system');
        }

        return reset($users);
    }


    /*
     * Transitions: Unassigned to Assigned
     * Condition:   Owner role is Full-timer
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Load product data');

        //$helper = new LoadProductHelpers();
        $line_count = $this->fileCount();

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
        $helper = new LoadProductHelpers();
        /** @var EntityManager $manager */
        $user = $helper->getFirstUser($manager);
        $businessUnit = $user->getOwner();
        $organization = $user->getOrganization();

        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate(self::PRODUCT_FILE);

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'rb');
        $headers = fgetcsv($handler, self::MAX_LINE, ',');

        $outOfStockStatus = $this->getOutOfStockInventoryStatus($manager);

        $this->container->get('oro_layout.loader.image_filter')->load();

        $slugGenerator = $this->container->get('oro_entity_config.slug.generator');
        $loadedProducts = [];

        $AttributeFamily = $this->getAttributeFamily($manager, 'default_family');

        $j = 0;

        $num = $line_count;

        while (($data = fgetcsv($handler, self::MAX_LINE, ',')) !== false) {
            if ($j == $i && trim($data[0]) != '') {
                if ( sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $output->writeln($i.' of '. $num. ', completed:'.round($i/$num*100,2) .'% product: '. trim($row['sku']));

                    if (trim($row['sku']) != '') {
                        $name = new LocalizedFallbackValue();
                        $name->setString($helper->translateChars($row['name']));

                        $text = '<p  class="product-view-desc">' . $row['description'] . '</p>';

                        $description = new LocalizedFallbackValue();
                        $description->setText($helper->translateChars(nl2br($text)));
                        $shortDescription = new LocalizedFallbackValue();
                        $shortDescription->setText($helper->translateChars($row['description']));
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

//                    $this->addImageToProduct($product, $manager, $locator, $row['image'], $allImageTypes);
                        file_put_contents('/tmp/product.log', 'persisting product: ' . $product->getName() . ' ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                        try {
                            $manager->persist($product);
                        } catch (ORMInvalidArgumentException $e) {

                            file_put_contents('/tmp/error_product.log', 'ORMException' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                            file_put_contents('/tmp/error_product.log', trim($row['sku']) . PHP_EOL, FILE_APPEND);
                        }

                        file_put_contents('/tmp/product.log', 'persisted product : ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

                        $loadedProducts[] = $product;
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
     * @param EntityManager $manager
     * @param string $brand_name
     * @param OrganizationInterface $organization
     * @param BusinessUnit $businessUnit
     * @return Brand
     */
    private function getBrand(EntityManager $manager,
                              $brand_name,
                              $organization,
                              $businessUnit)
    {

        $brand = $this->findBrandByName($manager, $brand_name);

        if (!isset($brand)) {
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

        return $manager->getRepository($inventoryStatusClassName)->findOneBy([
            'id' => Product::INVENTORY_STATUS_OUT_OF_STOCK
        ]);
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
     * @param string $attribute_code
     * @return AttributeFamily|null
     */
    private function getAttributeFamily(EntityManager $manager, $attribute_code)
    {
        $familyRepository = $manager->getRepository(AttributeFamily::class);

        return $familyRepository->findOneBy(['code' => $attribute_code]);
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
     * @param EntityManager $manager
     * @param string $brand_name
     * @return null|Brand
     */
    private function findBrandByName(EntityManager $manager, $brand_name)
    {

        $brands = $manager->getRepository('OroProductBundle:Brand')->findAll();

        foreach ($brands as $brand) {
            if ($brand_name == $brand->getName()->getString())
                return $brand;
        }
        return null;
    }
}
