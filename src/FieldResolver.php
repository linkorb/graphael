<?php

namespace Graphael;

use Pwa\TimeElapsed;
use RuntimeException;

class FieldResolver
{
    public function resolve($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info)
    {
        $fieldName = $info->fieldName;
        $property = null;

        $fieldConfig = $info->parentType->getField($fieldName)->config;
        if (isset($fieldConfig['alias'])) {
            $fieldName = $fieldConfig['alias'];
        }

        if (isset($fieldConfig['link'])) {
            $value = $source[$fieldName];
            $linkType = $fieldConfig['type'];
            $linkMethod = $fieldConfig['link'];
            $row = $linkType->{$linkMethod}($value);
            if (!$row) {
                return null;
            }

            return $row;
        }



        if (isset($fieldConfig['list'])) {
            $value = $source[$fieldName];
            $listType = $fieldConfig['type']->getWrappedType();
            $listMethod = $fieldConfig['list'];
            $res = $listType->{$listMethod}($value);
            if (!$res) {
                $res = [];
            }

            return $res;
        }

        if (isset($fieldConfig['getAllBy'])) {
            $value = $source[$fieldName];
            $listType = $fieldConfig['type']->getWrappedType();
            $res = $listType->getAllBy($fieldConfig['getAllBy'], $value);
            if (!$res) {
                $res = [];
            }

            return $res;
        }

        if (isset($fieldConfig['getBy'])) {
            $value = $source[$fieldName];
            $linkType = $fieldConfig['type'];
            $res = $linkType->getBy($fieldConfig['getBy'], $value);
            if (!$res) {
                $res = null;
            }

            return $res;
        }

        if (is_array($source) || $source instanceof \ArrayAccess) {
            if (isset($source[$fieldName])) {
                $property = $source[$fieldName];
            }
        } elseif (is_object($source)) {
            if (isset($source->{$fieldName})) {
                $property = $source->{$fieldName};
            }
        }

        if (isset($fieldConfig['convert']) && !empty($property)) {
            switch ($fieldConfig['convert']) {
                case 'stampToIsoDateTime':
                    $date = new \DateTime();
                    $date->setTimestamp($property);
                    return $date->format('Y-m-d\TH:i:s');
                case 'stampToIsoDate':
                    $date = new \DateTime();
                    $date->setTimestamp($property);
                    return $date->format('Y-m-d');
                case 'stampToElapsed':
                    $date = new \DateTime();
                    $date->setTimestamp($property);
                    $elapsed = new TimeElapsed($date);
                    return $elapsed->getElapsedTime();
                case 'dateTimeToIsoDateTime':
                    $date = new \DateTime($property);
                    return $date->format('Y-m-d\TH:i:s');
                default:
                    throw new RuntimeException("Unsupported conversion: " . $fieldConfig['convert']);
            }
        }

        return $property instanceof \Closure ? $property($source, $args, $context) : $property;
    }
}
