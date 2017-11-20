<?php

namespace Graphael;

class FieldResolver
{
    function resolve($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info)
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


        if (is_array($source) || $source instanceof \ArrayAccess) {
            if (isset($source[$fieldName])) {
                $property = $source[$fieldName];
            }
        } else if (is_object($source)) {
            if (isset($source->{$fieldName})) {
                $property = $source->{$fieldName};
            }
        }

        if (isset($fieldConfig['convert'])) {
            switch ($fieldConfig['convert']) {
                case 'stamp2dt':
                    return date('Y-m-d', $property) . 'T' . date('H:i:s', $property) ;
            }
        }

        return $property instanceof \Closure ? $property($source, $args, $context) : $property;
    }
}
