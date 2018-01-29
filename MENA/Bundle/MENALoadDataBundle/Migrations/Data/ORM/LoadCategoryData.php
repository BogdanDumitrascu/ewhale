<?php

namespace MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM;

class LoadCategoryData extends AbstractCategoryFixture
{

//    /**
//     * {@inheritDoc}
//     */
    //    public function getDependencies()
    //    {
    //        return array(
    //            'MENA\Bundle\MENALoadDataBundle\Migrations\Data\ORM\LoadMasterCatalogRoot',
    //        );
    //    }

    /**
     * @var array
     */
    protected $categories =
        array(
            'Beauty & Fragrance' => array(
                'Air Fresheners' => array(),
                'Bath & Body' => array(),
                'Beauty Tools & Accessories' => array(),
                'Deodorants & Antiperspirants' => array(),
                'Hair Styling Electronics' => array(),
                'Hair Tools & Accessories' => array(),
                'Health and Personal Care' => array(),
                'Makeup' => array(),
                'Perfume' => array(),
                'Shampoos & Conditioners' => array(),
                'Skin Care' => array(),
                'Soap & Shower Gel' => array()),
            'Electronics' => array(
                'Batteries' => array(),
                'Cables' => array(),
                'Car Audio & Video Accessories' => array(),
                'Car Care Products' => array(),
                'Card Readers & Writers' => array(),
                'Chargers' => array(),
                'Coffee & Espresso Makers' => array(),
                'Electric Shavers & Removal' => array(),
                'Electrical & Electronic Accessories' => array(),
                'Memory Cards & USB Flash Drives' => array(),
                'Headphones & Headsets' => array(),
                'Power Banks' => array(),
                'Smart Watches' => array(),
                'Speakers' => array(),
                'Mobile Phone Accessories' => array(),
                'Tablets' => array(),
                'Microphones' => array()),
            'Fashion' => array(
                'Shoes & Accessories' => array(),
                'Swimwear' => array(),
                'Tops' => array(),
                'Watches' => array()),
            'Food & Beverage' => array(
                'Beverages' => array(),
                'Coffee' => array(),
                'Seasoning, Spices & Preservatives' => array(),
                'Snacks' => array()),
            'Home & Office' => array(
                'Chairs & Benches' => array(),
                'Cleaning Products' => array(),
                'Garden decoration' => array(),
                'Mattresses' => array(),
                'Office equipment' => array(),
                'Stationery' => array(),
                'Home Supplies' => array()),
            'Toys' => array()
        );

    /**
     * @var array
     */
    protected $categoryImages =
        array(
            'Beauty & Fragrance' => array('small' => 'beauty'),
            'Perfume' => array('small' => 'perfumes'),
            'Fashion' => array('small' => 'fashion'),
            'Food & Beverage' => array('small' => 'food'),
            'Home & Office' => array('small' => 'home'),
            'Chairs & Benches' => array('small' => 'chairs'),
            'Toys' => array('small' => 'toys'),
            'Office equipment' => array('small' => 'office')
        );
}
