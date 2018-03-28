<?php

namespace MENA\Bundle\MENALoadDataBundle\Loads;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\Entity\Repository\CategoryRepository;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadProductCategory extends AbstractLoads implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var Category
     */
    protected $root;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->root = $this->getCategoryRepository($container->get('doctrine.orm.entity_manager'))->getMasterCatalogRoot();
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function load(EntityManager $manager, OutputInterface $output, Product $product, $row)
    {
        $output->writeln('Loading product category: [' . trim($row['category']) . '] for product: ' . trim($row['sku']));

        $output->writeln('---> Root: '. $this->root->getDefaultTitle());

        $category = $this->getCategoryByDefaultTitle($manager, trim($row['category']));
//        file_put_contents('/tmp/product.log', 'subcategory->' . trim($row['subcategory']). PHP_EOL, FILE_APPEND);
        $subcategory = $this->getCategoryByDefaultTitle($manager, trim($row['subcategory']), $category);
        $subcategory->addProduct($product);

        $output->writeln('---> Category: '. $category->getDefaultTitle());
        $output->writeln('---> Subcategory: '. $subcategory->getDefaultTitle());

        $manager->persist($category);
        $manager->persist($subcategory);

        $manager->flush();
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

        $category = $this->getCategoryRepository($manager)->findOneByDefaultTitle($title);

        if (!$category) {
//            file_put_contents('/tmp/product.log', 'category not found: ' . $title . PHP_EOL, FILE_APPEND);
            $category = $this->createCategory($manager, $title);

            if ($parent_category != null) {
//                file_put_contents('/tmp/product.log', 'add to parent category: ' . $parent_category->getDefaultTitle() . PHP_EOL, FILE_APPEND);
                $parent_category->addChildCategory($category);
            } else {
                $this->root->addChildCategory($category);
                $manager->persist($this->root);
            }
        }

        return $category;
    }

    /**
     * @param EntityManager $manager
     * @param $title
     * @return Category $category
     */

    private function createCategory(EntityManager $manager, $title)
    {
        $slugGenerator = $this->container->get('oro_entity_config.slug.generator');

        $categoryTitle = new LocalizedFallbackValue();
        $categoryTitle->setString($title);
        $category = new Category();
        $category->addTitle($categoryTitle);

        $slugPrototype = new LocalizedFallbackValue();
        $slugPrototype->setString($slugGenerator->slugify($title));
        $category->addSlugPrototype($slugPrototype);

        //$manager->persist($category);

        return $category;
    }

    /**
     * @param EntityManager $manager
     *
     * @return CategoryRepository
     */
    protected function getCategoryRepository(EntityManager $manager)
    {
        if (!$this->categoryRepository) {
            $this->categoryRepository = $manager->getRepository('OroCatalogBundle:Category');
        }

        return $this->categoryRepository;
    }
}
