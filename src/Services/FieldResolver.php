<?php

declare(strict_types=1);

namespace Graphael\Services;

use GraphQL\Type\Definition\ResolveInfo;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pwa\TimeElapsed;

class FieldResolver
{
    public function resolve($source, $args, $context, ResolveInfo $info)
    {
        $fieldName = $info->fieldName;
        $property = null;

        $fieldConfig = $info->parentType->getField($fieldName)->config;

        $authorize = isset($fieldConfig['authorize']) ? $fieldConfig['authorize'] : true;

        if (isset($fieldConfig['alias'])) {
            $fieldName = $fieldConfig['alias'];
        }

        if (!empty($fieldConfig['tripwire'])) {
            $typeName = ucfirst($info->path[0]).'Type';

            $log = new Logger('tripwire');
            $log->pushHandler(new StreamHandler(getenv('TRIPWIRE_LOG')));
            $log->critical('Tripwire trigged:'.$typeName.'.'.$fieldName);

            return null;
        }

        if (isset($fieldConfig['link'])) {
            $value = $source[$fieldName];
            $linkType = $fieldConfig['type'];
            $linkMethod = $fieldConfig['link'];
            $row = $linkType->{$linkMethod}($value, $context, $authorize);
            if (!$row) {
                return null;
            }

            return $row;
        }

        if (isset($fieldConfig['list'])) {
            $value = $source[$fieldName];
            $listType = $fieldConfig['type']->getWrappedType();
            $listMethod = $fieldConfig['list'];
            $res = $listType->{$listMethod}($value, $context, $authorize);
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
                    $date->setTimestamp((int) $property);

                    return $date->format('Y-m-d\TH:i:s');
                case 'stampToIsoDate':
                    $date = new \DateTime();
                    $date->setTimestamp($property);

                    return $date->format('Y-m-d');
                case 'stampToElapsed':
                    $date = new \DateTime();
                    $date->setTimestamp((int) $property);
                    $elapsed = new TimeElapsed($date);

                    return $elapsed->getElapsedTime();
                case 'dateTimeToIsoDateTime':
                    $date = new \DateTime($property);

                    return $date->format('Y-m-d\TH:i:s');
                default:
                    throw new \RuntimeException('Unsupported conversion: '.$fieldConfig['convert']);
            }
        }

        if (!empty($fieldConfig['sanitize'])) {
            $value = $property ?? '';

            return htmlspecialchars($value);
        }

        return $property instanceof \Closure ? $property($source, $args, $context) : $property;
    }
}
