<?php

declare(strict_types=1);

namespace JMS\Serializer\Exclusion;

use JMS\Serializer\Context;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;

final class GroupsExclusionStrategy implements ExclusionStrategyInterface
{
    public const DEFAULT_GROUP = 'Default';

    /**
     * @var array
     */
    private $groups = [];

    /**
     * @var bool
     */
    private $nestedGroups = false;

    public function __construct(array $groups)
    {
        if (empty($groups)) {
            $groups = [self::DEFAULT_GROUP];
        }

        foreach ($groups as $group) {
            if (is_array($group)) {
                $this->nestedGroups = true;
                break;
            }
        }

        if ($this->nestedGroups) {
            $this->groups = $groups;
        } else {
            foreach ($groups as $group) {
                $this->groups[$group] = true;
            }
        }
    }

    public function shouldSkipClass(ClassMetadata $metadata, Context $navigatorContext): bool
    {
        return false;
    }

    public function getPropertyGroups(PropertyMetadata $property, Context $navigatorContext)
    {
        if (empty($property->groups)) {
            $groups = [self::DEFAULT_GROUP];
        } else {
            $groups = [];
            foreach ($property->groups as $key => $group) {
                if (is_array($group)) {
                    $groups[] = $key;
                } else {
                    $groups[] = $group;
                }
            }
        }

        /** @var PropertyMetadata $metadata */
        foreach ($navigatorContext->getMetadataStack() as $metadata) {
            if ($metadata === $property) {
                continue;
            }

            if (!$metadata instanceof PropertyMetadata) {
                continue;
            }

            if (empty($metadata->groups)) {
                continue;
            }

            $newGroups = null;
            foreach ($metadata->groups as $key => $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (is_null($newGroups)) {
                    $newGroups = [];
                }

                foreach ($value as $group) {
                    if (in_array($group, $groups)) {
                        $newGroups[$key] = true;
                        break;
                    }
                }
            }

            if (!is_null($newGroups)) {
                $groups = array_keys($newGroups);
            }
        }

        return $groups;
    }

    public function shouldSkipProperty(PropertyMetadata $property, Context $navigatorContext): bool
    {
        $propertyGroups = $this->getPropertyGroups($property, $navigatorContext);

        if ($this->nestedGroups) {
            $groups = $this->getGroupsFor($navigatorContext);

            if (!$propertyGroups) {
                return !in_array(self::DEFAULT_GROUP, $propertyGroups);
            }

            return $this->shouldSkipUsingGroups($propertyGroups, $groups);
        } else {
            if (!$propertyGroups) {
                return !isset($this->groups[self::DEFAULT_GROUP]);
            }

            foreach ($propertyGroups as $group) {
                if (isset($this->groups[$group])) {
                    return false;
                }
            }

            return true;
        }
    }

    private function shouldSkipUsingGroups(array $propertyGroups, array $groups): bool
    {
        foreach ($propertyGroups as $group) {
            if (in_array($group, $groups)) {
                return false;
            }
        }

        return true;
    }

    public function getGroupsFor(Context $navigatorContext): array
    {
        if (!$this->nestedGroups) {
            return array_keys($this->groups);
        }

        $paths = $navigatorContext->getCurrentPath();
        $groups = $this->groups;
        foreach ($paths as $index => $path) {
            if (!array_key_exists($path, $groups)) {
                if ($index > 0) {
                    $groups = [self::DEFAULT_GROUP];
                } else {
                    $groups = array_filter($groups, 'is_string') ?: [self::DEFAULT_GROUP];
                }

                break;
            }

            $groups = $groups[$path];
            if (!array_filter($groups, 'is_string')) {
                $groups += [self::DEFAULT_GROUP];
            }
        }

        return $groups;
    }
}
