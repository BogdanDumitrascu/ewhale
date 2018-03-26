<?php

namespace MENA\Bundle\MENALoadDataBundle\Loads;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;

abstract class AbstractLoads implements
    ContainerAwareInterface//,
    // DependentFixtureInterface
{


      /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $products = array();

    /**
     * @var array
     */
    protected $productUnis = array();

    /**
     * @var array
     */
    protected $priceLists = array();

    /**
     * @var array
     */
    protected $loadedProducts = array();

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function __construct($container)
    {
        $this->setContainer($container);
    }

    /**
     * {@inheritdoc}
     * @param EntityManager $manager
     * @param OutputInterface $output
     * @param Product $product
     * @param $row
     * @return Product
     */
    abstract public function load(EntityManager $manager, OutputInterface $output, Product $product, $row);
    /**
     * @param EntityManager $manager
     * @param string $sku
     * @return Product|null
     */
    protected function getProductBySku(EntityManager $manager, $sku)
    {
        if (!array_key_exists($sku, $this->products)) {
            $this->products[$sku] = $manager->getRepository('OroProductBundle:Product')->findOneBy(array('sku' => $sku));
        }

        return $this->products[$sku];
    }

    /**
     * @param EntityManager $manager
     * @param string $code
     * @return ProductUnit|null
     */
    protected function getProductUnit(EntityManager $manager, $code)
    {
        if (!array_key_exists($code, $this->productUnis)) {
            $this->productUnis[$code] = $manager->getRepository('OroProductBundle:ProductUnit')->find($code);
        }

        return $this->productUnis[$code];
    }

    protected function translateChars($string)
    {
        return preg_replace('/[[:^print:]]/', "", $string); //preg_replace("/[^\\x00-\\xFFFF]/", "", $string); //mb_convert_encoding($string, 'UTF-8', 'UTF-8');; //iconv('UTF-8', "UTF-8//IGNORE", utf8_encode($string));
    }

    /**
     * @param EntityManager $manager
     * @return User
     * @throws \LogicException
     */
    protected function getFirstUser(EntityManager $manager)
    {
        $users = $manager->getRepository('OroUserBundle:User')->findBy([], ['id' => 'ASC'], 1);
        if (!$users) {
            throw new \LogicException('There are no users in system');
        }

        return reset($users);
    }


}
