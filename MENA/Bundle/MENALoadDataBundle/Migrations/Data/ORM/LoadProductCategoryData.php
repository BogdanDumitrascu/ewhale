<?php

namespace MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\Entity\Repository\CategoryRepository;

class LoadProductCategoryData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $products = [];

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * @var array
     */
    protected $subcategories = [];

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var Category
     */
    private $root;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function getDependencies()
    {
        return array(
            'MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM\LoadProductData',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate('@MENALoadDataBundle/Migrations/Data/ORM/data/products.csv');

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'r');
        $headers = fgetcsv($handler, 20000, ',');

        $this->root = $category = $this->getCategoryRepository($manager)->findOneBy(array('id'=>1));

        while (($data = fgetcsv($handler, 20000, ',')) !== false) {

            if (trim($data[0])!='') {
                if (sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $product = $this->getProductBySku($manager, trim($row['sku']));
                    if ($product != null && isset($row['category'])) {
                        $category = $this->getCategoryByDefaultTitle($manager, trim($row['category']));
                        file_put_contents('/tmp/product.log', 'subcategory->', FILE_APPEND);
                        $subcategory = $this->getCategoryByDefaultTitle($manager, trim($row['subcategory']), $category);
                        $subcategory->addProduct($product);

                        $manager->persist($category);
                        $manager->persist($subcategory);
                        $manager->persist($this->root);
                    }
                }
            }
        }

        fclose($handler);

        $manager->flush();
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
     * @param string $title
     * @param Category $parent_category
     *
     * @return Category|null
     */
    protected function getCategoryByDefaultTitle(EntityManager $manager,
                                                 $title,
                                                 Category $parent_category = null)
    {

        if (array_key_exists($title, $this->categories)) {
            return $this->categories[$title];
        }

        $category = $this->getCategoryRepository($manager)->findOneByDefaultTitle($title);

        if (!$category) {
            file_put_contents('/tmp/product.log', 'category not found: ' . $title . PHP_EOL, FILE_APPEND);

            $category = $this->createCategory($manager, $title);

            if (isset($parent_category)) {
                file_put_contents('/tmp/product.log', 'add to parent category: ' . $parent_category->getDefaultTitle() . PHP_EOL, FILE_APPEND);
                $parent_category->addChildCategory($category);
            } else {
                $this->root->addChildCategory($category);
            }
        }

        $this->categories[$title] = $category;

        return $category;
    }

    /**
     * @param ObjectManager $manager
     * @param $title
     * @return Category $category
     */

    private function createCategory(ObjectManager $manager, $title)
    {
        $slugGenerator = $this->container->get('oro_entity_config.slug.generator');

        $categoryTitle = new LocalizedFallbackValue();
        $categoryTitle->setString($title);
        $category = new Category();
        $category->addTitle($categoryTitle);

        $slugPrototype = new LocalizedFallbackValue();
        $slugPrototype->setString($slugGenerator->slugify($title));
        $category->addSlugPrototype($slugPrototype);

        return $category;
    }

    /**
     * @param ObjectManager $manager
     *
     * @return EntityRepository
     */
    protected function getProductRepository(ObjectManager $manager)
    {
        if (!$this->productRepository) {
            $this->productRepository = $manager->getRepository('OroProductBundle:Product');
        }

        return $this->productRepository;
    }

    /**
     * @param ObjectManager $manager
     *
     * @return CategoryRepository
     */
    protected function getCategoryRepository(ObjectManager $manager)
    {
        if (!$this->categoryRepository) {
            $this->categoryRepository = $manager->getRepository('OroCatalogBundle:Category');
        }

        return $this->categoryRepository;
    }
}
