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

namespace Pimcore\Model\DataObject\Traits;

use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
trait ElementWithMetadataComparisonTrait
{
    /**
     * @param mixed $array1
     * @param mixed $array2
     *
     * @return bool
     */
    public function isEqual($array1, $array2): bool
    {
        $count1 = is_array($array1) ? count($array1) : 0;
        $count2 = is_array($array2) ? count($array2) : 0;

        if ($count1 !== $count2) {
            return false;
        }

        $values1 = array_filter(array_values(is_array($array1) ? $array1 : []));
        $values2 = array_filter(array_values(is_array($array2) ? $array2 : []));

        for ($i = 0; $i < $count1; $i++) {
            /** @var ElementMetadata|null $container1 */
            $container1 = $values1[$i];
            /** @var ElementMetadata|null $container2 */
            $container2 = $values2[$i];

            if (!$container1 || !$container2) {
                return !$container1 && !$container2;
            }

            /** @var ElementInterface $el1 */
            $el1 = $container1->getElement();
            /** @var ElementInterface $el2 */
            $el2 = $container2->getElement();

            if (! ($el1->getType() == $el2->getType() && ($el1->getId() == $el2->getId()))) {
                return false;
            }

            $data1 = $container1->getData();
            $data2 = $container2->getData();
            if ($data1 != $data2) {
                return false;
            }
        }

        return true;
    }
}
