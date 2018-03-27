<?php

namespace MENA\Bundle\MENALoadDataBundle\Loads;

use Doctrine\ORM\EntityManager;
use Extend\Entity\EV_Prod_Inventory_Status;
use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\InventoryBundle\Entity\InventoryLevel;
use Oro\Bundle\InventoryBundle\Inventory\InventoryManager;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\ProductBundle\Entity\Brand;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class LoadProductData extends AbstractLoads implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    const PRODUCT_INVENTORY_QUANTITY = 1000;
    /**
     * @var array
     */
    protected $productUnits = array();

    /**
     * @var string
     */
    const ENUM_CODE_INVENTORY_STATUS = 'prod_inventory_status';

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function load(EntityManager $manager, OutputInterface $output, Product $product, $row)
    {

        $output->writeln('Loading product data: ' . trim($row['sku']));

        $user = $this->getFirstUser($manager);


        $businessUnit = $user->getOwner();
        $organization = $user->getOrganization();

        $inStockStatus = $this->getInStockInventoryStatus($manager);

        $slugGenerator = $this->container->get('oro_entity_config.slug.generator');
        $AttributeFamily = $this->getAttributeFamily($manager, 'default_family');
        $name = new LocalizedFallbackValue();

        $name->setString($this->translateChars($row['name']));
        $text = '<p  class="product-view-desc">' . $row['description'] . '</p>';
        $description = new LocalizedFallbackValue();
        $description->setText($this->translateChars(nl2br($text)));
        $shortDescription = new LocalizedFallbackValue();
        $shortDescription->setText($this->translateChars($row['description']));
        $brand = $this->getBrand($manager, trim($row['brand']), $organization, $businessUnit);
        $product = new Product();
        $product->setOwner($businessUnit)
            ->setOrganization($organization)
            ->setAttributeFamily($AttributeFamily)
            ->setSku(trim($row['sku']))
            ->setInventoryStatus($inStockStatus)
            ->setStatus(Product::STATUS_ENABLED)
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

        file_put_contents('/tmp/product.log', 'persisting product: ' . $product->getName() . ' ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);
        $manager->persist($product);
        file_put_contents('/tmp/product.log', 'persisted product : ' . trim($row['sku']) . PHP_EOL, FILE_APPEND);

        $loadedProducts[] = $product;

        $manager->flush();
        $this->createSlugs($loadedProducts, $manager);

        $output->writeln('Product data loaded');

        return $product;
}

/**
 * @param EntityManager $manager
 * @param string $brand_name
 * @param OrganizationInterface $organization
 * @param BusinessUnit $businessUnit
 * @return Brand
 */
private
function getBrand(EntityManager $manager,
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
private
function createSlugs(array $products, EntityManager $manager)
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
protected
function getInStockInventoryStatus(EntityManager $manager)
{
    $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName(self::ENUM_CODE_INVENTORY_STATUS);

    return $manager->getRepository($inventoryStatusClassName)->findOneBy([
        'id' => Product::INVENTORY_STATUS_IN_STOCK
    ]);
}


/**
 * @param EntityManager $manager
 * @param string $attribute_code
 * @return AttributeFamily|null
 */
private
function getAttributeFamily(EntityManager $manager, $attribute_code)
{
    $familyRepository = $manager->getRepository(AttributeFamily::class);

    return $familyRepository->findOneBy(['code' => $attribute_code]);
}

/**
 * @param Product $product
 * @param array $row
 * @return LoadProductData
 */
private
function setPageTemplate(Product $product, array $row)
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
private
function findBrandByName(EntityManager $manager, $brand_name)
{

    $brands = $manager->getRepository('OroProductBundle:Brand')->findAll();

    foreach ($brands as $brand) {
        if ($brand_name == $brand->getName()->getString())
            return $brand;
    }
    return null;
}
}
