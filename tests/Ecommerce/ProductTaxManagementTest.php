<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Tests\Ecommerce;

use Codeception\Util\Stub;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractProduct;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\CheckoutableInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\Currency;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\AttributePriceSystem;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\Price;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\TaxManagement\TaxEntry;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\PricingManager;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\PricingManagerLocator;
use Pimcore\Bundle\EcommerceFrameworkBundle\Type\Decimal;
use Pimcore\Model\DataObject\OnlineShopTaxClass;
use Pimcore\Tests\Test\EcommerceTestCase;

class ProductTaxManagementTest extends EcommerceTestCase
{
    /**
     * @param float $grossPrice
     * @param array $taxes
     * @param string $combinationType
     *
     * @return CheckoutableInterface
     */
    private function setUpProduct($grossPrice, $taxes = [], $combinationType = TaxEntry::CALCULATION_MODE_COMBINE): CheckoutableInterface
    {
        $grossPrice = Decimal::create($grossPrice);

        $taxClass = new OnlineShopTaxClass();
        $taxEntries = new \Pimcore\Model\DataObject\Fieldcollection();

        foreach ($taxes as $name => $tax) {
            $entry = new \Pimcore\Model\DataObject\Fieldcollection\Data\TaxEntry();
            $entry->setPercent($tax);
            $entry->setName($name);
            $taxEntries->add($entry);
        }
        $taxClass->setTaxEntries($taxEntries);
        $taxClass->setTaxEntryCombinationType($combinationType);

        $environment = $this->buildEnvironment();

        $pricingManagers = Stub::make(PricingManagerLocator::class, [
            'getPricingManager' => function () {
                return new PricingManager([], []);
            },
        ]);

        $priceSystem = Stub::construct(AttributePriceSystem::class, [$pricingManagers, $environment], [
            'getTaxClassForProduct' => function () use ($taxClass) {
                return $taxClass;
            },
            'getPriceClassInstance' => function (Decimal $amount) {
                return new Price($amount, new Currency('EUR'));
            },
            'calculateAmount' => function () use ($grossPrice): Decimal {
                return $grossPrice;
            },
        ]);

        /** @var AbstractProduct|\PHPUnit_Framework_MockObject_Stub $product */
        $product = Stub::construct(AbstractProduct::class, [], [
            'getId' => function () {
                return 5;
            },
            'getPriceSystemImplementation' => function () use ($priceSystem) {
                return $priceSystem;
            },
            'getCategories' => function () {
                return [];
            },
        ]);

        return $product;
    }

    public function testPriceWithoutTaxEntries()
    {
        $product = $this->setUpProduct(100);
        $price = $product->getOSPrice();

        $this->assertSame('100.0000', $price->getAmount()->asString(), 'Get Price Amount without any tax entries');
        $this->assertSame('100.0000', $price->getNetAmount()->asString(), 'Get net amount without any tax entries');
        $this->assertSame('100.0000', $price->getGrossAmount()->asString(), 'Get gross amount without any tax entries');
    }

    public function testPriceWithTaxEntriesCombine()
    {
        $product = $this->setUpProduct(100, [1 => 10, 2 => 15], TaxEntry::CALCULATION_MODE_COMBINE);
        $price = $product->getOSPrice();

        $this->assertSame('100.0000', $price->getGrossAmount()->asString(), 'Get gross amount with tax 10% + 15% combine');
        $this->assertSame('80.0000', $price->getNetAmount()->asString(), 'Get net amount 10% + 15% combine');
    }

    public function testPriceWithTaxEntriesOneAfterAnother()
    {
        $product = $this->setUpProduct(100, [1 => 10, 2 => 15], TaxEntry::CALCULATION_MODE_ONE_AFTER_ANOTHER);
        $price = $product->getOSPrice();

        $this->assertSame('100.0000', $price->getGrossAmount()->asString(), 'Get gross amount with tax 10% + 15% one-after-another');
        $this->assertSame('79.0514', $price->getNetAmount()->asString(), 'Get net amount 10% + 15% one-after-another');
    }
}
