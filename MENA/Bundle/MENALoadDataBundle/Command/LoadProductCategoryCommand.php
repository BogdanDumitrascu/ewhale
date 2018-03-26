<?php

namespace MENA\Bundle\MENALoadDataBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MENA\Bundle\MENALoadDataBundle\Command\LoadProductHelpers;

class LoadProductCategoryCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const STATUS_SUCCESS = 0;
    const COMMAND_NAME   = 'oro:loadproductpricelist';
    const MAX_LINE = LoadProductHelpers::MAX_LINE;
    const PRODUCT_FILE = LoadProductHelpers::PRODUCT_FILE;

    /** @var LoadProductHelpers */
    protected $helper;

    /**
     * @var array
     */
    protected $categories = [];


    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;


    /**
     * @var Category
     */
    private $root;

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
        $this->helper = new LoadProductHelpers();

        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('load product price list');
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

   /*
     * Transitions: Unassigned to Assigned
     * Condition:   Owner role is Full-timer
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Load product price list');

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
        $locator = $this->container->get('file_locator');
        $filePath = $locator->locate(self::PRODUCT_FILE);

        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'rb');
        $headers = fgetcsv($handler, self::MAX_LINE, ',');

        $j = 0;

        $num = $line_count;
        $this->root = $category = $this->getCategoryRepository($manager)->findOneBy(array('id'=>1));

        while (($data = fgetcsv($handler, self::MAX_LINE, ',')) !== false) {
            if ($j == $i && trim($data[0]) != '') {
                if ( sizeof($headers) == sizeof(array_values($data))) {
                    $row = array_combine($headers, array_values($data));

                    $output->writeln($i.' of '. $num. ', completed:'.round($i/$num*100,2) .'% product: '. trim($row['sku']));

                    if (trim($row['sku']) != '') {
                        $product = $this->helper->getProductBySku($manager, trim($row['sku']));
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
            $j++;
        }
        $manager->flush();
        fclose($handler);
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

        return $category;
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

    /**
     * @param EntityManager $manager
     *
     * @return \Oro\Bundle\CatalogBundle\Entity\Repository\CategoryRepository
     */
    protected function getCategoryRepository(EntityManager $manager)
    {
        if (!$this->categoryRepository) {
            $this->categoryRepository = $manager->getRepository('OroCatalogBundle:Category');
        }

        return $this->categoryRepository;
    }
}
