<?php

namespace MENA\Bundle\MENALoadDataBundle\Command;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;

use Doctrine\ORM\EntityRepository;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
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

class LoadProductHelpers extends ContainerAwareCommand
{
    const STATUS_SUCCESS = 0;
    const COMMAND_NAME   = 'oro:helper';
    const MAX_LINE = 20000;
    const PRODUCT_FILE = '@MENALoadDataBundle/Migrations/Data/ORM/data/products.csv';

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
//
//    /**
//     * {@inheritDoc}
//     */
//    public function setContainer(ContainerInterface $container = null)
//    {
//        $this->container = $container;
//    }


    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var string
     */
    const ENUM_CODE_INVENTORY_STATUS = 'prod_inventory_status';
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('load product helper');
    }

    /**
     * @var array
     */
    protected $productUnits = array();

    public function fileCount()
    {
        $locator = $this->getContainer()->get('file_locator');
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
     * @param ObjectManager $manager
     * @return User
     * @throws \LogicException
     */
    public function getFirstUser(ObjectManager $manager)
    {
        $users = $manager->getRepository('OroUserBundle:User')->findBy([], ['id' => 'ASC'], 1);
        if (!$users) {
            throw new \LogicException('There are no users in system');
        }

        return reset($users);
    }

    /**
     * @param EntityManager $manager
     * @param string $sku
     * @return Product|null
     */
    public function getProductBySku(EntityManager $manager, $sku)
    {
        if (!array_key_exists($sku, $this->products)) {
            $this->products[$sku] = $manager->getRepository('OroProductBundle:Product')->findOneBy(array('sku' => $sku));
        }

        return $this->products[$sku];
    }

    /**
     * @param EntityManager $manager
     * @param string $name
     * @return PriceList|null
     */
    public function getPriceList(EntityManager $manager, $name)
    {
        if (!array_key_exists($name, $this->priceLists)) {
            $this->priceLists[$name] = $manager->getRepository('OroPricingBundle:PriceList')
                ->findOneBy(['name' => $name]);
        }

        return $this->priceLists[$name];
    }
}