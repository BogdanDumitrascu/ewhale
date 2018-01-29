<?php

namespace MENA\Bundle\MENAThemeBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\CMSBundle\Entity\TextContentVariant;
use Oro\Bundle\UserBundle\DataFixtures\UserUtilityTrait;
use Oro\Bundle\UserBundle\Migrations\Data\ORM\LoadAdminUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class ReplaceHomePageSlider extends AbstractFixture implements DependentFixtureInterface, ContainerAwareInterface
{
    use UserUtilityTrait;

    const HOME_PAGE_SLIDER_ALIAS = 'home-page-slider';

    /** @var  EntityManager */
    protected $em;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadAdminUserData::class
        ];
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $slider = $this->em->getRepository('OroCMSBundle:ContentBlock')->findOneBy(['alias'=>self::HOME_PAGE_SLIDER_ALIAS]);
        $slider->removeContentVariant($slider->getContentVariants()[0]);

        $html = file_get_contents(__DIR__.'/data/frontpage_slider.html');
        $variant = new TextContentVariant();
        $variant->setDefault(true);
        $variant->setContent($html);

        $slider->addContentVariant($variant);

        $manager->persist($slider);
        $manager->flush($slider);
    }
}
