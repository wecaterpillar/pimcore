<?php

declare(strict_types=1);

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

namespace Pimcore\HttpKernel\BundleCollection;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class BundleCollection
{
    /**
     * @var ItemInterface[]
     */
    private $items = [];

    /**
     * Adds a collection item
     *
     * @return $this
     */
    public function add(ItemInterface $item): static
    {
        $identifier = $item->getBundleIdentifier();

        // a bundle can only be registered once
        if ($this->hasItem($identifier)) {
            return $this;
        }

        $this->items[$identifier] = $item;

        // handle DependentBundleInterface by adding a bundle's dependencies to the collection - dependencies
        // are added AFTER the item was added to the collection to avoid circular reference loops, but the
        // sort order can be influenced by specifying a priority on the item
        $item->registerDependencies($this);

        return $this;
    }

    /**
     * Returns a collection item by identifier
     *
     * @param string $identifier
     *
     * @return ItemInterface
     */
    public function getItem(string $identifier): ItemInterface
    {
        if (!$this->hasItem($identifier)) {
            throw new \InvalidArgumentException(sprintf('Bundle "%s" is not registered', $identifier));
        }

        return $this->items[$identifier];
    }

    /**
     * Checks if a specific item is registered
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function hasItem(string $identifier)
    {
        return isset($this->items[$identifier]);
    }

    /**
     * Returns all collection items ordered by priority and optionally filtered by matching environment
     *
     * @param string|null $environment
     *
     * @return ItemInterface[]
     */
    public function getItems(string $environment = null): array
    {
        $items = array_values($this->items);

        if (null !== $environment) {
            $items = array_filter($items, function (ItemInterface $item) use ($environment) {
                return $item->matchesEnvironment($environment);
            });
        }

        usort($items, function (ItemInterface $a, ItemInterface $b) {
            if ($a->getPriority() === $b->getPriority()) {
                return 0;
            }

            return ($a->getPriority() > $b->getPriority()) ? -1 : 1;
        });

        return $items;
    }

    /**
     * Returns all bundle identifiers
     *
     * @return array
     */
    public function getIdentifiers(string $environment = null): array
    {
        return array_map(function (ItemInterface $item) {
            return $item->getBundleIdentifier();
        }, $this->getItems($environment));
    }

    /**
     * Get bundles matching environment ordered by priority
     *
     * @param string $environment
     *
     * @return BundleInterface[]
     */
    public function getBundles(string $environment): array
    {
        return array_map(function (ItemInterface $item) {
            return $item->getBundle();
        }, $this->getItems($environment));
    }

    /**
     * Adds a bundle
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function addBundle(BundleInterface|string $bundle, int $priority = 0, array $environments = []): static
    {
        if ($bundle instanceof BundleInterface) {
            $item = new Item($bundle, $priority, $environments);
        } elseif (is_string($bundle)) {
            $item = new LazyLoadedItem($bundle, $priority, $environments);
        } else {
            throw new \InvalidArgumentException('Bundle must be either an instance of BundleInterface or a string containing the bundle class name');
        }

        return $this->add($item);
    }

    /**
     * Adds a collection of bundles with the same priority and environments
     *
     * @param BundleInterface[]|string[] $bundles
     *
     * @return $this
     */
    public function addBundles(array $bundles, int $priority = 0, array $environments = []): static
    {
        foreach ($bundles as $bundle) {
            $this->addBundle($bundle, $priority, $environments);
        }

        return $this;
    }
}
